<?php

namespace App\Services\Mail;

use App\Mail\OutboundEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Default transport: sends through the application's configured SMTP mailer
 * (Mailpit in local, the org's SMTP in production). Used until a user connects
 * their own work mailbox.
 */
class SystemMailGateway implements MailGateway
{
    public function send(string $toEmail, ?string $toName, string $subject, string $body, array $options = []): bool
    {
        try {
            Mail::to($toEmail, $toName)->send(new OutboundEmail($subject, $body, $options['reply_to'] ?? null));
            return true;
        } catch (\Throwable $e) {
            Log::warning('SystemMailGateway send failed', ['to' => $toEmail, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function label(): string
    {
        return 'System mailer';
    }
}
