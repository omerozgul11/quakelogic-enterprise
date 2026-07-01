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
        // When central sending is enforced, every email goes out through the
        // system mailer (the platform's single verified address) — the per-user
        // "send from your own work mailbox" override is bypassed.
        if (config('integrations.mail.force_central_sender', true)) {
            return new SystemMailGateway();
        }

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
