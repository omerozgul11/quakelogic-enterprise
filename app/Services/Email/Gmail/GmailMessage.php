<?php

namespace App\Services\Email\Gmail;

/**
 * A normalized inbound email message read from a Gmail inbox. Source-agnostic:
 * the same shape is produced by the IMAP client and the fake fixture client.
 */
readonly class GmailMessage
{
    /**
     * @param  array<int,array{filename:string,mime:?string,size:?int}>  $attachments
     */
    public function __construct(
        public string $messageId,        // RFC Message-ID header — stable across fetches
        public ?string $uid = null,      // IMAP UID within the mailbox
        public ?string $threadId = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public string $subject = '',
        public ?\DateTimeInterface $date = null,
        public ?string $html = null,
        public ?string $text = null,
        public array $attachments = [],
    ) {}

    public function body(): string
    {
        return (string) ($this->html !== null && $this->html !== '' ? $this->html : $this->text);
    }
}
