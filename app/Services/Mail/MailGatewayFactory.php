<?php

namespace App\Services\Mail;

use App\Models\User;

class MailGatewayFactory
{
    /**
     * Pick the right transport for a user: their connected Gmail account when
     * one is available and Google Workspace is configured, otherwise the system
     * mailer. This is the single switch that "turns on" per-user sending once
     * the Workspace credentials land — no caller changes required.
     */
    public function forUser(?User $user): MailGateway
    {
        $account = $user?->emailAccount;

        if ($account
            && $account->provider === 'google'
            && $account->isConnected()
            && config('services.google.client_id')) {
            return new GmailMailGateway($account);
        }

        return new SystemMailGateway();
    }
}
