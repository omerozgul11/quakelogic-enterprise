<?php

namespace App\Services\Email\Gmail;

/**
 * Reads messages from a Gmail inbox. Implemented by the live IMAP client and a
 * fake fixture client, selected in AppServiceProvider by config + credentials.
 */
interface GmailInboxClient
{
    /**
     * Fetch matching messages.
     *
     * @param  array{since?:\DateTimeInterface,from_filters?:array<int,string>,subject_filters?:array<int,string>,limit?:int}  $criteria
     * @return array<int,GmailMessage>
     */
    public function fetch(array $criteria = []): array;

    /** True when real IMAP credentials are configured (otherwise the fake client is in use). */
    public function isConfigured(): bool;

    /** Short identifier for logs/UI, e.g. 'imap' or 'fake'. */
    public function label(): string;
}
