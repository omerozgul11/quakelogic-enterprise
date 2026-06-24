<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\QuickBooks\QuickBooksClientInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moves expense data between QuickBooks Online and the tracker:
 *   - pull(): imports Purchases/Bills as expenses (idempotent on quickbooks_id),
 *     auto-mapping QBO accounts to expense categories.
 *   - push(): sends approved, locally-created expenses into QuickBooks (only
 *     when the connection has push_enabled).
 * Imported expenses are recorded as already-approved spend (source=quickbooks).
 */
class QuickBooksSyncService
{
    /**
     * True while this process is importing/exporting. The ExpenseObserver checks
     * it so sync-driven writes never bounce straight back out as another push,
     * and so QuickBooks-sourced rows are never echoed back to QuickBooks.
     */
    public static bool $isSyncing = false;

    /** @var array<string,int> */
    private array $categoryCache = [];

    public function __construct(
        private readonly QuickBooksClientInterface $client,
        private readonly ExpenseNumberService $numbers,
    ) {}

    /** Run $callback with the sync guard raised (re-entrant safe). */
    public static function withoutEcho(callable $callback): mixed
    {
        $previous = static::$isSyncing;
        static::$isSyncing = true;
        try {
            return $callback();
        } finally {
            static::$isSyncing = $previous;
        }
    }

    /** Run a full sync for one connection and record the outcome. @return array<string,int> */
    public function syncOrganization(QuickBooksConnection $connection): array
    {
        try {
            return self::withoutEcho(function () use ($connection) {
                $this->ensureFreshToken($connection);
                $pulled = $this->pull($connection);
                $pushed = $connection->push_enabled ? $this->push($connection) : 0;

                $connection->forceFill([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'ok',
                    'last_sync_message' => "Imported {$pulled['imported']}, updated {$pulled['updated']}, pushed {$pushed}.",
                ])->save();

                return [...$pulled, 'pushed' => $pushed];
            });
        } catch (Throwable $e) {
            Log::warning('QuickBooks sync failed', ['organization_id' => $connection->organization_id, 'error' => $e->getMessage()]);
            $connection->forceFill([
                'last_sync_status' => 'error',
                'last_sync_message' => substr($e->getMessage(), 0, 500),
            ])->save();

            return ['imported' => 0, 'updated' => 0, 'pushed' => 0];
        }
    }

    /** Import expense transactions from QuickBooks. @return array{imported:int,updated:int} */
    public function pull(QuickBooksConnection $connection): array
    {
        $rows = $this->client->fetchExpenses($connection, $connection->last_synced_at);
        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (empty($row['quickbooks_id'])) {
                continue;
            }

            $categoryId = $this->categoryFor($connection, $row['account_name'] ?? null);
            $paymentMethod = ($row['payment_method'] ?? null) && PaymentMethod::tryFrom($row['payment_method'])
                ? $row['payment_method'] : null;

            $existing = Expense::where('organization_id', $connection->organization_id)
                ->where('quickbooks_id', $row['quickbooks_id'])->first();

            if ($existing) {
                $existing->update([
                    'vendor' => $row['vendor'] ?? $existing->vendor,
                    'description' => $row['description'] ?? $existing->description,
                    'amount' => $row['amount'],
                    'currency' => $row['currency'] ?? 'USD',
                    'payment_method' => $paymentMethod,
                    'expense_date' => $row['txn_date'],
                    'expense_category_id' => $categoryId ?? $existing->expense_category_id,
                    'quickbooks_synced_at' => now(),
                ]);
                $updated++;

                continue;
            }

            Expense::create([
                'organization_id' => $connection->organization_id,
                'created_by' => $connection->connected_by,
                'owner_id' => $connection->connected_by,
                'expense_category_id' => $categoryId,
                'number' => $this->numbers->generate($connection->organization_id),
                'vendor' => $row['vendor'] ?? null,
                'description' => $row['description'] ?? null,
                'amount' => $row['amount'],
                'currency' => $row['currency'] ?? 'USD',
                'payment_method' => $paymentMethod,
                'status' => ExpenseStatus::Approved->value,   // already booked in QuickBooks
                'approved_at' => now(),
                'expense_date' => $row['txn_date'],
                'source' => 'quickbooks',
                'quickbooks_id' => $row['quickbooks_id'],
                'quickbooks_synced_at' => now(),
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated];
    }

    /** Push every approved, locally-created, not-yet-synced expense. Returns the count pushed. */
    public function push(QuickBooksConnection $connection): int
    {
        $pending = Expense::where('organization_id', $connection->organization_id)
            ->where('status', ExpenseStatus::Approved->value)
            ->where('source', 'manual')
            ->whereNull('quickbooks_id')
            ->with('category:id,name')
            ->get();

        $pushed = 0;
        foreach ($pending as $expense) {
            if ($this->pushExpense($connection, $expense)) {
                $pushed++;
            }
        }

        return $pushed;
    }

    /**
     * Push (create or update) a single expense to QuickBooks immediately. Used by
     * the real-time observer/job the moment an expense is approved or edited.
     * Imported (source=quickbooks) and non-approved expenses are never pushed.
     */
    public function pushExpense(QuickBooksConnection $connection, Expense $expense): bool
    {
        if (! $connection->push_enabled || $expense->source !== 'manual' || ! $expense->status->countsAsSpend()) {
            return false;
        }

        return self::withoutEcho(function () use ($connection, $expense) {
            $expense->loadMissing('category:id,name');

            $payload = [
                'amount' => (float) $expense->amount,
                'txn_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'vendor' => $expense->vendor,
                'description' => $expense->description,
                'currency' => $expense->currency,
                'account_name' => $expense->category?->name,
            ];

            $remoteId = $expense->quickbooks_id
                ? $this->client->updatePurchase($connection, $expense->quickbooks_id, $payload)
                : $this->client->createPurchase($connection, $payload);

            if ($remoteId) {
                // saveQuietly so persisting the sync stamp doesn't re-fire the observer.
                $expense->forceFill(['quickbooks_id' => $remoteId, 'quickbooks_synced_at' => now()])->saveQuietly();

                return true;
            }

            return false;
        });
    }

    /** Refresh the access token when expired (live connections only). */
    public function ensureFreshToken(QuickBooksConnection $connection): void
    {
        if ($connection->is_demo || ! $connection->tokenExpired() || ! $connection->refresh_token) {
            return;
        }

        $tokens = $this->client->refreshTokens($connection->refresh_token);
        $connection->forceFill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'refresh_token_expires_at' => now()->addSeconds($tokens['refresh_token_expires_in']),
        ])->save();
    }

    private function categoryFor(QuickBooksConnection $connection, ?string $name): ?int
    {
        $name = $name ? trim($name) : null;
        if (! $name) {
            return null;
        }

        $key = $connection->organization_id.'|'.$name;
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $category = ExpenseCategory::firstOrCreate(
            ['organization_id' => $connection->organization_id, 'name' => $name],
            ['created_by' => $connection->connected_by, 'budget_period' => 'monthly', 'currency' => 'USD', 'is_active' => true],
        );

        return $this->categoryCache[$key] = $category->id;
    }
}
