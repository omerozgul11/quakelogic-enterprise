<?php

namespace App\Modules\ExpenseTracker\QuickBooks;

use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use Carbon\CarbonInterface;

/**
 * Deterministic stand-in for QuickBooks Online used in dev, tests and "demo"
 * mode (no Intuit credentials). It never touches the network: connecting yields
 * canned tokens, pulling yields a fixed set of sample expenses (stable ids so
 * re-syncing is idempotent), and pushing echoes a deterministic remote id.
 */
class FakeQuickBooksClient implements QuickBooksClientInterface
{
    public function isLive(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state): string
    {
        // Demo connect is handled in-app (no redirect to Intuit); this is only a
        // placeholder so the contract is satisfied.
        return url('/expenses/quickbooks/callback?code=demo&realmId=DEMO&state='.$state);
    }

    public function exchangeCode(string $code): array
    {
        return $this->cannedTokens();
    }

    public function refreshTokens(string $refreshToken): array
    {
        return $this->cannedTokens();
    }

    public function fetchExpenses(QuickBooksConnection $connection, ?CarbonInterface $since): array
    {
        // A small, stable sample book so the importer is exercisable end-to-end.
        return [
            ['quickbooks_id' => 'FAKE-PUR-1001', 'kind' => 'Purchase', 'vendor' => 'Amazon Web Services', 'amount' => 432.18, 'currency' => 'USD', 'txn_date' => now()->subDays(9)->toDateString(), 'description' => 'Cloud hosting', 'account_name' => 'Software & SaaS', 'payment_method' => 'card'],
            ['quickbooks_id' => 'FAKE-PUR-1002', 'kind' => 'Purchase', 'vendor' => 'Delta Air Lines', 'amount' => 615.00, 'currency' => 'USD', 'txn_date' => now()->subDays(6)->toDateString(), 'description' => 'Flight to client site', 'account_name' => 'Travel', 'payment_method' => 'card'],
            ['quickbooks_id' => 'FAKE-BILL-2001', 'kind' => 'Bill', 'vendor' => 'WeWork', 'amount' => 2200.00, 'currency' => 'USD', 'txn_date' => now()->subDays(3)->toDateString(), 'description' => 'Office rent', 'account_name' => 'Rent & Utilities', 'payment_method' => null],
        ];
    }

    public function createPurchase(QuickBooksConnection $connection, array $payload): ?string
    {
        // Deterministic per-payload id so a double-push maps to the same record.
        return 'FAKE-CREATED-'.substr(md5(json_encode($payload)), 0, 12);
    }

    public function updatePurchase(QuickBooksConnection $connection, string $quickbooksId, array $payload): ?string
    {
        return $quickbooksId;
    }

    /** @return array{access_token:string, refresh_token:string, expires_in:int, refresh_token_expires_in:int} */
    private function cannedTokens(): array
    {
        return [
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 8726400,
        ];
    }
}
