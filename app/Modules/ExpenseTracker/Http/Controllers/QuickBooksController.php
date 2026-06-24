<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\QuickBooks\QuickBooksClientInterface;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuickBooksController extends Controller
{
    public function __construct(private readonly QuickBooksClientInterface $client) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view expenses'), 403);
        $connection = $this->connection($request);

        return Inertia::render('Expenses/QuickBooks/Index', [
            'live' => $this->client->isLive(),
            'realtime' => [
                'push' => true, // app→QuickBooks is event-driven (observer + queue worker)
                'webhook_url' => url('/api/quickbooks/webhook'),
                'webhook_ready' => (bool) config('services.quickbooks.webhook_token'),
            ],
            'connection' => $connection ? [
                'realm_id' => $connection->realm_id,
                'environment' => $connection->environment,
                'is_demo' => $connection->is_demo,
                'push_enabled' => $connection->push_enabled,
                'connected_by' => $connection->connectedBy?->name,
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'last_sync_status' => $connection->last_sync_status,
                'last_sync_message' => $connection->last_sync_message,
            ] : null,
            'imported_count' => $connection
                ? \App\Modules\ExpenseTracker\Models\Expense::where('organization_id', $request->user()->organization_id)
                    ->where('source', 'quickbooks')->count()
                : 0,
            'can' => ['manage' => $request->user()->can('manage expenses')],
        ]);
    }

    /** Begin the connect flow. Live → redirect to Intuit; demo → create a local connection. */
    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage expenses'), 403);

        if (! $this->client->isLive()) {
            $this->store($request, realmId: 'DEMO-'.Str::upper(Str::random(6)), demo: true);

            return redirect()->route('expenses.quickbooks.index')
                ->with('success', 'Connected in demo mode — Sync now to import sample expenses. Add Intuit credentials to go live.');
        }

        $state = Str::random(40);
        $request->session()->put('quickbooks_oauth_state', $state);

        return redirect()->away($this->client->authorizationUrl($state));
    }

    /** OAuth redirect target — exchange the code and persist the connection. */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage expenses'), 403);

        $expected = $request->session()->pull('quickbooks_oauth_state');
        abort_unless($request->query('state') === $expected, 403, 'Invalid OAuth state.');

        $realmId = (string) $request->query('realmId');
        $tokens = $this->client->exchangeCode((string) $request->query('code'));

        $this->store($request, $realmId, demo: false, tokens: $tokens);

        return redirect()->route('expenses.quickbooks.index')->with('success', 'QuickBooks connected.');
    }

    public function sync(Request $request, QuickBooksSyncService $sync): RedirectResponse
    {
        abort_unless($request->user()->can('manage expenses'), 403);
        $connection = $this->connection($request);
        abort_unless($connection, 404);

        $result = $sync->syncOrganization($connection);

        return back()->with('success', "Sync complete — imported {$result['imported']}, updated {$result['updated']}, pushed {$result['pushed']}.");
    }

    public function togglePush(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage expenses'), 403);
        $connection = $this->connection($request);
        abort_unless($connection, 404);

        $connection->update(['push_enabled' => ! $connection->push_enabled]);

        return back()->with('success', $connection->push_enabled
            ? 'Push to QuickBooks enabled — approved expenses will sync to your books.'
            : 'Push to QuickBooks disabled.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage expenses'), 403);
        $this->connection($request)?->delete();

        return back()->with('success', 'QuickBooks disconnected.');
    }

    private function connection(Request $request): ?QuickBooksConnection
    {
        return QuickBooksConnection::where('organization_id', $request->user()->organization_id)
            ->with('connectedBy:id,name')->first();
    }

    /** @param array{access_token:string,refresh_token:string,expires_in:int,refresh_token_expires_in:int}|null $tokens */
    private function store(Request $request, string $realmId, bool $demo, ?array $tokens = null): void
    {
        QuickBooksConnection::updateOrCreate(
            ['organization_id' => $request->user()->organization_id],
            [
                'connected_by' => $request->user()->id,
                'realm_id' => $realmId,
                'environment' => config('services.quickbooks.environment', 'production'),
                'is_demo' => $demo,
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null,
                'refresh_token_expires_at' => isset($tokens['refresh_token_expires_in']) ? now()->addSeconds($tokens['refresh_token_expires_in']) : null,
            ],
        );
    }
}
