<?php

namespace App\Modules\ExpenseTracker\QuickBooks;

use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live QuickBooks Online client (Accounting API v3, OAuth 2.0). Bound only when
 * QUICKBOOKS_SYNC_ENABLED=true and the Intuit app credentials are present; until
 * then the fake drives everything. Endpoint shapes follow the Intuit developer
 * docs so the contract can be matched without code changes once a company is
 * connected.
 */
class RealQuickBooksClient implements QuickBooksClientInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly string $environment,
        private readonly string $authorizeUrl,
        private readonly string $tokenUrl,
        private readonly string $scope,
    ) {}

    public function isLive(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->authorizeUrl.'?'.http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => $this->scope,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);
    }

    public function refreshTokens(string $refreshToken): array
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function fetchExpenses(QuickBooksConnection $connection, ?CarbonInterface $since): array
    {
        $clause = $since ? " WHERE MetaData.LastUpdatedTime > '".$since->toIso8601String()."'" : '';

        return array_merge(
            $this->queryExpenses($connection, 'Purchase', $clause),
            $this->queryExpenses($connection, 'Bill', $clause),
        );
    }

    public function createPurchase(QuickBooksConnection $connection, array $payload): ?string
    {
        $id = $this->postPurchase($connection, $this->purchaseBody($connection, $payload));

        return $id;
    }

    public function updatePurchase(QuickBooksConnection $connection, string $quickbooksId, array $payload): ?string
    {
        // Sparse update needs the current SyncToken — read it, then patch.
        $current = Http::withToken($connection->access_token)->acceptJson()
            ->get($this->apiBase()."/v3/company/{$connection->realm_id}/purchase/{$quickbooksId}", ['minorversion' => 70]);

        if ($current->failed()) {
            throw new RuntimeException('QuickBooks purchase lookup failed: '.$current->body());
        }

        $body = array_merge($this->purchaseBody($connection, $payload), [
            'Id' => $quickbooksId,
            'SyncToken' => (string) data_get($current->json(), 'Purchase.SyncToken', '0'),
            'sparse' => true,
        ]);

        return $this->postPurchase($connection, $body);
    }

    /** @param array<string,mixed> $body */
    private function postPurchase(QuickBooksConnection $connection, array $body): ?string
    {
        $response = Http::withToken($connection->access_token)
            ->acceptJson()
            ->post($this->apiBase()."/v3/company/{$connection->realm_id}/purchase?minorversion=70", $body);

        if ($response->failed()) {
            throw new RuntimeException('QuickBooks rejected the purchase: '.$response->body());
        }

        return (string) data_get($response->json(), 'Purchase.Id');
    }

    /** @param array{amount:float,txn_date:string,vendor:?string,description:?string,currency:string,account_name:?string} $payload @return array<string,mixed> */
    private function purchaseBody(QuickBooksConnection $connection, array $payload): array
    {
        if (! $connection->push_account_id) {
            throw new RuntimeException('Set a QuickBooks payment account before pushing expenses.');
        }

        return [
            'PaymentType' => 'Cash',
            'AccountRef' => ['value' => $connection->push_account_id],
            'TxnDate' => $payload['txn_date'],
            'TotalAmt' => $payload['amount'],
            'PrivateNote' => $payload['description'] ?? $payload['vendor'] ?? null,
            'Line' => [[
                'Amount' => $payload['amount'],
                'DetailType' => 'AccountBasedExpenseLineDetail',
                'AccountBasedExpenseLineDetail' => array_filter([
                    'AccountRef' => $connection->push_expense_account_id ? ['value' => $connection->push_expense_account_id] : null,
                ]),
            ]],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function queryExpenses(QuickBooksConnection $connection, string $entity, string $clause): array
    {
        $query = "SELECT * FROM {$entity}{$clause} ORDERBY MetaData.LastUpdatedTime STARTPOSITION 1 MAXRESULTS 200";

        $response = Http::withToken($connection->access_token)
            ->acceptJson()
            ->get($this->apiBase()."/v3/company/{$connection->realm_id}/query", [
                'query' => $query,
                'minorversion' => 70,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("QuickBooks {$entity} query failed: ".$response->body());
        }

        $rows = data_get($response->json(), "QueryResponse.{$entity}", []);

        return array_map(fn (array $row) => $this->normalize($entity, $row), $rows);
    }

    /** @return array<string,mixed> */
    private function normalize(string $entity, array $row): array
    {
        $accountName = data_get($row, 'Line.0.AccountBasedExpenseLineDetail.AccountRef.name')
            ?? data_get($row, 'AccountRef.name');

        return [
            'quickbooks_id' => (string) ($row['Id'] ?? ''),
            'kind' => $entity,
            'vendor' => data_get($row, 'EntityRef.name') ?? data_get($row, 'VendorRef.name'),
            'amount' => (float) ($row['TotalAmt'] ?? 0),
            'currency' => data_get($row, 'CurrencyRef.value', 'USD'),
            'txn_date' => $row['TxnDate'] ?? now()->toDateString(),
            'description' => $row['PrivateNote'] ?? null,
            'account_name' => $accountName,
            'payment_method' => $this->mapPaymentType($row['PaymentType'] ?? null),
        ];
    }

    private function mapPaymentType(?string $type): ?string
    {
        return match ($type) {
            'Cash' => 'cash',
            'Check' => 'check',
            'CreditCard' => 'card',
            default => $type ? 'other' : null,
        };
    }

    /** @param array<string,mixed> $form @return array{access_token:string, refresh_token:string, expires_in:int, refresh_token_expires_in:int} */
    private function token(array $form): array
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->post($this->tokenUrl, $form);

        if ($response->failed()) {
            throw new RuntimeException('QuickBooks token request failed: '.$response->body());
        }

        $json = $response->json();

        return [
            'access_token' => $json['access_token'],
            'refresh_token' => $json['refresh_token'],
            'expires_in' => (int) ($json['expires_in'] ?? 3600),
            'refresh_token_expires_in' => (int) ($json['x_refresh_token_expires_in'] ?? 8726400),
        ];
    }

    private function apiBase(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://sandbox-quickbooks.api.intuit.com'
            : 'https://quickbooks.api.intuit.com';
    }
}
