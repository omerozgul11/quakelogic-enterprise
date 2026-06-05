<?php

namespace App\Services\Email;

interface EmailProviderInterface
{
    public function getName(): string;
    public function isConfigured(): bool;
    public function send(string $to, string $subject, string $body, array $options = []): bool;
}
