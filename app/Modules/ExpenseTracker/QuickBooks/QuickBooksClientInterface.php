<?php

namespace App\Modules\ExpenseTracker\QuickBooks;

use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use Carbon\CarbonInterface;

/**
 * Boundary to Intuit QuickBooks Online. The real implementation talks to the
 * Accounting API over OAuth 2.0; the fake drives dev/tests and "demo" mode with
 * deterministic data and never hits the network. Resolved by QuickBooksClientFactory.
 */
interface QuickBooksClientInterface
{
    /** True only when sync is enabled AND Intuit app credentials are present. */
    public function isLive(): bool;

    /** The Intuit OAuth2 authorize URL to redirect the user to when connecting. */
    public function authorizationUrl(string $state): string;

    /**
     * Exchange an OAuth authorization code for tokens.
     *
     * @return array{access_token:string, refresh_token:string, expires_in:int, refresh_token_expires_in:int}
     */
    public function exchangeCode(string $code): array;

    /**
     * Refresh an access token using a refresh token. Same shape as exchangeCode().
     *
     * @return array{access_token:string, refresh_token:string, expires_in:int, refresh_token_expires_in:int}
     */
    public function refreshTokens(string $refreshToken): array;

    /**
     * Pull expense transactions (Purchases + Bills) updated since $since (null = all).
     *
     * @return array<int,array{quickbooks_id:string, kind:string, vendor:?string, amount:float, currency:string, txn_date:string, description:?string, account_name:?string, payment_method:?string}>
     */
    public function fetchExpenses(QuickBooksConnection $connection, ?CarbonInterface $since): array;

    /**
     * Create a Purchase (expense) in QuickBooks. Returns the new remote id.
     *
     * @param  array{amount:float, txn_date:string, vendor:?string, description:?string, currency:string, account_name:?string}  $payload
     */
    public function createPurchase(QuickBooksConnection $connection, array $payload): ?string;

    /**
     * Update an existing Purchase in QuickBooks (sparse update). Returns the id.
     *
     * @param  array{amount:float, txn_date:string, vendor:?string, description:?string, currency:string, account_name:?string}  $payload
     */
    public function updatePurchase(QuickBooksConnection $connection, string $quickbooksId, array $payload): ?string;
}
