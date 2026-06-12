<?php

namespace App\Services\Mail;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through a user's own SMTP server (e.g. a Gmail/Workspace app
 * password or Office 365), so proposal follow-ups & digests come from their
 * work address. Credentials live encrypted on the EmailAccount.
 */
class SmtpMailGateway implements MailGateway
{
    public function __construct(private readonly EmailAccount $account) {}

    public function send(string $toEmail, ?string $toName, string $subject, string $body, array $options = []): bool
    {
        try {
            $fromName = $options['from_name'] ?? $this->account->from_name ?? $this->account->user?->name ?? '';

            $email = (new Email())
                ->from(new Address($this->account->email, (string) $fromName))
                ->to($toName ? new Address($toEmail, $toName) : new Address($toEmail))
                ->subject($subject)
                ->text($body);

            if (!empty($options['reply_to'])) {
                $email->replyTo($options['reply_to']);
            }

            (new Mailer(Transport::fromDsn($this->dsn())))->send($email);

            return true;
        } catch (\Throwable $e) {
            Log::warning('SmtpMailGateway send failed', ['account' => $this->account->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function label(): string
    {
        return 'Work email (' . $this->account->email . ')';
    }

    /** Build a Symfony Mailer DSN from the stored SMTP settings. */
    private function dsn(): string
    {
        $a = $this->account;
        $scheme = $a->smtp_encryption === 'ssl' ? 'smtps' : 'smtp';
        $port = $a->smtp_port ?: ($scheme === 'smtps' ? 465 : 587);

        // Omit credentials entirely for un-authenticated relays (e.g. a local
        // catcher); otherwise URL-encode them so symbols in app passwords are safe.
        $auth = '';
        if (!empty($a->smtp_username)) {
            $auth = rawurlencode((string) $a->smtp_username) . ':' . rawurlencode((string) $a->smtp_password) . '@';
        }

        return sprintf('%s://%s%s:%d', $scheme, $auth, $a->smtp_host, $port);
    }
}
