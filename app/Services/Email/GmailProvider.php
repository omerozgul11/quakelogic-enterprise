<?php

namespace App\Services\Email;

class GmailProvider implements EmailProviderInterface
{
    public function getName(): string
    {
        return 'gmail';
    }

    public function isConfigured(): bool
    {
        return false; // Stub — requires OAuth2 integration
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        throw new \RuntimeException('Gmail OAuth2 integration is not yet configured.');
    }
}
