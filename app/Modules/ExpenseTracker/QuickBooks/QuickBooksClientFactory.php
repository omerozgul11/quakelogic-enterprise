<?php

namespace App\Modules\ExpenseTracker\QuickBooks;

/**
 * Resolves the active QuickBooks client. The real client is used only when
 * QUICKBOOKS_SYNC_ENABLED=true AND the Intuit app credentials are present;
 * otherwise the fake drives dev/tests and demo mode. Mirrors the UPS/JB Hunt
 * tracking factory and the Finance payment factory.
 */
class QuickBooksClientFactory
{
    public static function make(): QuickBooksClientInterface
    {
        $cfg = config('services.quickbooks');

        if (($cfg['sync_enabled'] ?? false) && ($cfg['client_id'] ?? null) && ($cfg['client_secret'] ?? null)) {
            return new RealQuickBooksClient(
                $cfg['client_id'],
                $cfg['client_secret'],
                $cfg['redirect_uri'] ?? url('/expenses/quickbooks/callback'),
                $cfg['environment'] ?? 'production',
                $cfg['authorize_url'],
                $cfg['token_url'],
                $cfg['scope'],
            );
        }

        return new FakeQuickBooksClient();
    }

    public static function default(): QuickBooksClientInterface
    {
        return static::make();
    }
}
