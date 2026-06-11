<?php

namespace App\Services\Mail;

/**
 * Sends an outbound email on behalf of a user. Implementations decide the
 * transport: the system SMTP mailer today, each user's connected Gmail account
 * once Google Workspace is wired up.
 */
interface MailGateway
{
    /**
     * @param  array<string,mixed>  $options  e.g. ['reply_to' => '...', 'from_name' => '...']
     */
    public function send(string $toEmail, ?string $toName, string $subject, string $body, array $options = []): bool;

    /** Human-readable description of where mail is sent from (for the UI). */
    public function label(): string;
}
