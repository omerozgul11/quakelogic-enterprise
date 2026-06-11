<?php

namespace App\Services\Mail;

use App\Models\EmailAccount;

/**
 * Sends through a user's connected Gmail / Google Workspace account.
 *
 * STUB: the Gmail API send is implemented once Google Workspace OAuth
 * credentials are configured. The token plumbing (EmailAccount) and the
 * gateway selection (MailGatewayFactory) are already in place, so wiring the
 * actual API call is the only remaining step.
 */
class GmailMailGateway implements MailGateway
{
    public function __construct(private readonly EmailAccount $account) {}

    public function send(string $toEmail, ?string $toName, string $subject, string $body, array $options = []): bool
    {
        // TODO(google-workspace): POST to
        // https://gmail.googleapis.com/gmail/v1/users/me/messages/send with a
        // base64url RFC-2822 message, using $this->account->access_token
        // (refreshing via refresh_token when token_expires_at has passed).
        throw new \RuntimeException('Gmail sending is not configured yet. Connect Google Workspace first.');
    }

    public function label(): string
    {
        return "Gmail ({$this->account->email})";
    }
}
