<?php

namespace App\Services\Email\Gmail;

use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\ClientManager;

/**
 * Reads BidPrime alert emails from a Gmail inbox over IMAP using a Gmail App
 * Password (pure-PHP webklex/php-imap — no ext-imap needed). Read-only: it never
 * deletes or modifies messages. Sender/subject filtering is applied in PHP so it
 * is robust to Gmail's search quirks.
 */
class ImapGmailInboxClient implements GmailInboxClient
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['username']) && ! empty($this->config['password']);
    }

    public function label(): string
    {
        return 'imap';
    }

    /** @return array<int,GmailMessage> */
    public function fetch(array $criteria = []): array
    {
        $client = (new ClientManager())->make([
            'host' => $this->config['host'] ?? 'imap.gmail.com',
            'port' => (int) ($this->config['port'] ?? 993),
            'encryption' => $this->config['encryption'] ?? 'ssl',
            'validate_cert' => true,
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'protocol' => 'imap',
            'authentication' => null,
        ]);

        $client->connect();

        try {
            $folder = $client->getFolderByPath($this->config['mailbox'] ?? 'INBOX');

            $query = $folder->query()->setFetchOrder('desc');
            if (($criteria['since'] ?? null) instanceof \DateTimeInterface) {
                $query->since(Carbon::parse($criteria['since']));
            }

            $messages = $query->limit((int) ($criteria['limit'] ?? 200))->get();

            $fromFilters = array_map('strtolower', $criteria['from_filters'] ?? []);
            $subjectFilters = array_map('strtolower', $criteria['subject_filters'] ?? []);

            $out = [];
            foreach ($messages as $message) {
                [$fromMail, $fromName] = $this->fromParts($message);
                $subject = (string) $message->getSubject();

                if ($fromFilters && ! $this->matchesAny($fromMail.' '.strtolower($fromName), $fromFilters)) {
                    continue;
                }
                if ($subjectFilters && ! $this->matchesAny(strtolower($subject), $subjectFilters)) {
                    continue;
                }

                $out[] = new GmailMessage(
                    messageId: $this->messageId($message),
                    uid: (string) $message->getUid(),
                    threadId: null,
                    fromEmail: $fromMail ?: null,
                    fromName: $fromName ?: null,
                    subject: $subject,
                    date: $this->date($message),
                    html: ($message->getHTMLBody() ?: null),
                    text: ($message->getTextBody() ?: null),
                    attachments: $this->attachments($message),
                );
            }

            return $out;
        } finally {
            $client->disconnect();
        }
    }

    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0:?string,1:?string} [email, name] parsed robustly from the From header. */
    private function fromParts($message): array
    {
        $raw = '';
        try {
            $raw = trim((string) $message->getHeader()?->get('from'));
        } catch (\Throwable) {
        }
        if ($raw === '') {
            $from = $message->getFrom();
            $address = is_array($from) ? ($from[0] ?? null) : (is_object($from) && method_exists($from, 'first') ? $from->first() : null);
            if ($address) {
                $raw = trim((string) ($address->full ?? (($address->personal ?? '').' <'.($address->mail ?? '').'>')));
            }
        }

        $mail = preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $raw, $m) ? strtolower($m[1]) : null;
        $name = trim((string) preg_replace('/\s*<[^>]*>\s*/', '', $raw), " \"'");

        return [$mail, ($name !== '' && $name !== $mail) ? $name : null];
    }

    private function messageId($message): string
    {
        $id = trim((string) $message->getMessageId());

        return $id !== '' ? $id : 'uid-'.$message->getUid();
    }

    private function date($message): ?\DateTimeInterface
    {
        $raw = trim((string) $message->getDate());
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int,array{filename:string,mime:?string,size:?int}> */
    private function attachments($message): array
    {
        $list = [];
        foreach ($message->getAttachments() as $attachment) {
            $list[] = [
                'filename' => (string) $attachment->getName(),
                'mime' => $attachment->getMimeType() ? (string) $attachment->getMimeType() : null,
                'size' => $attachment->getSize() !== null ? (int) $attachment->getSize() : null,
            ];
        }

        return $list;
    }
}
