<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Mail;

class SmtpEmailProvider implements EmailProviderInterface
{
    public function getName(): string
    {
        return 'smtp';
    }

    public function isConfigured(): bool
    {
        return !empty(config('mail.mailers.smtp.host'));
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            Mail::html($body, function ($message) use ($to, $subject, $options) {
                $message->to($to)->subject($subject);
                if (!empty($options['from'])) {
                    $message->from($options['from'], $options['from_name'] ?? null);
                }
                if (!empty($options['cc'])) {
                    $message->cc($options['cc']);
                }
            });
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
