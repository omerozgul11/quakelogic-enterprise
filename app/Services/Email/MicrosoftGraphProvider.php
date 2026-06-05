<?php

namespace App\Services\Email;

class MicrosoftGraphProvider implements EmailProviderInterface
{
    public function getName(): string
    {
        return 'microsoft_graph';
    }

    public function isConfigured(): bool
    {
        return false; // Stub — requires Azure AD app registration
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        throw new \RuntimeException('Microsoft Graph integration is not yet configured.');
    }
}
