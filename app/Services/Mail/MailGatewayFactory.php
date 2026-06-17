<?php

namespace App\Services\Mail;

use App\Models\User;

class MailGatewayFactory
{
    /**
     * Pick the right transport for a user: their own connected work mailbox
     * (per-user SMTP, or Gmail once Workspace OAuth is configured) when one is
     * available, otherwise the system mailer. This is the single switch that
     * "turns on" per-user sending — no caller changes required.
     */
    public function forUser(?User $user): MailGateway
    {
        $account = $user?->emailAccount;

        if ($account && $account->isConnected()) {
            if ($account->provider === 'smtp') {
                return new SmtpMailGateway($account);
            }
            if ($account->provider === 'google' && config('services.google.client_id')) {
                return new GmailMailGateway($account);
            }
        }

        return new SystemMailGateway();
    }
}
