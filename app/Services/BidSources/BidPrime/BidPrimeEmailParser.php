<?php

namespace App\Services\BidSources\BidPrime;

use App\Services\BidSources\BidSourceResultDTO;
use App\Services\Email\Gmail\GmailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns a BidPrime alert email into normalized opportunity DTOs.
 *
 * Built defensively: it locates one block per BidPrime link in the HTML (using
 * DOMDocument/XPath), falling back to plain-text block splitting, then pulls
 * labelled fields with synonym-aware regexes. Unknown layouts degrade to "no
 * opportunities" rather than throwing. Field label sets are easy to extend as
 * real BidPrime formats are observed.
 */
class BidPrimeEmailParser
{
    private const LABEL_HINT = '/\b(Agency|Buyer|Solicitation|Bid\s*(?:#|No|Number)|RFP|RFQ|IFB|Due|Closing|Response|Deadline|NAICS|Location|State|Set[\-\s]?Aside|Posted)\b/i';

    /** @return array<int,BidSourceResultDTO> */
    public function extractOpportunities(GmailMessage $message): array
    {
        $blocks = [];
        // BidPrime "matched leads" daily digest — the live format: each lead is a
        // title linking to /bid/link/source/<uuid> followed by a due date and a
        // "ST - Agency" line.
        if ($message->html && str_contains($message->html, '/bid/link/source/')) {
            $blocks = $this->matchedLeadBlocks($message->html);
        }
        if (! $blocks && $message->html) {
            $blocks = $this->htmlBlocks($message->html);
        }
        if (! $blocks && $message->text) {
            $blocks = $this->textBlocks($message->text);
        }
        if (! $blocks && $message->html) {
            $blocks = $this->textBlocks($this->htmlToText($message->html));
        }

        $dtos = [];
        $seen = [];
        foreach ($blocks as $block) {
            $dto = $this->toDto($block, $message);
            if ($dto && ! isset($seen[$dto->externalId])) {
                $dtos[] = $dto;
                $seen[$dto->externalId] = true;
            }
        }

        return $dtos;
    }

    /**
     * BidPrime daily "matched leads" digest: title → due date (M/D/YYYY) →
     * "ST - Agency" lines, one lead after another under section headers. The
     * per-lead BidPrime URL (and its UUID) comes from the title's source link.
     *
     * @return array<int,array{title:string,bidprimeUrl:?string,sourceUrl:?string,text:string}>
     */
    private function matchedLeadBlocks(string $html): array
    {
        $clean = $this->stripNoise($html);

        // title (normalized) => source link href
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$clean, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $titleUrls = [];
        foreach ($xpath->query("//a[contains(@href,'/bid/link/source/')]") as $a) {
            if (! $a instanceof \DOMElement) {
                continue;
            }
            $t = $this->collapse($a->textContent);
            if ($t !== '' && ! isset($titleUrls[$t])) {
                $titleUrls[$t] = trim($a->getAttribute('href'));
            }
        }

        $lines = explode("\n", $this->htmlToText($clean));
        $skip = ['state & local', 'federal', 'bid alert', 'request documents'];
        $blocks = [];
        $count = count($lines);
        for ($i = 1; $i < $count - 1; $i++) {
            if (! preg_match('~^\d{1,2}/\d{1,2}/\d{4}$~', trim($lines[$i]))) {
                continue;
            }
            if (! preg_match('~^([A-Z]{2})\s*-\s*(.+)$~', trim($lines[$i + 1]), $loc)) {
                continue;
            }
            $title = trim($lines[$i - 1]);
            $lower = strtolower($title);
            if ($title === '' || in_array($lower, $skip, true) || str_starts_with($lower, 'bid alert')) {
                continue;
            }

            $url = $titleUrls[$this->collapse($title)] ?? null;
            if (! $url) {
                foreach ($titleUrls as $anchorTitle => $href) {
                    if ($anchorTitle !== '' && str_contains($anchorTitle, $title)) {
                        $url = $href;
                        break;
                    }
                }
            }

            $blocks[] = [
                'title' => $title,
                'bidprimeUrl' => $url,
                'sourceUrl' => null,
                'text' => "Title: {$title}\nDue Date: ".trim($lines[$i])."\nState: {$loc[1]}\nAgency: ".trim($loc[2]),
            ];
        }

        return $blocks;
    }

    private function stripNoise(string $html): string
    {
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string) $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', (string) $html);

        return (string) preg_replace('/<!--.*?-->/s', '', (string) $html);
    }

    /** @return array<int,array{title:string,bidprimeUrl:?string,sourceUrl:?string,text:string}> */
    private function htmlBlocks(string $html): array
    {
        $html = $this->stripNoise($html);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $anchors = $xpath->query("//a[contains(translate(@href,'BIDPRME','bidprme'),'bidprime')]");
        if (! $anchors || $anchors->length === 0) {
            return [];
        }

        $blocks = [];
        $seenHref = [];
        foreach ($anchors as $anchor) {
            if (! $anchor instanceof \DOMElement) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || isset($seenHref[$href])) {
                continue;
            }
            $seenHref[$href] = true;

            // Climb to the tightest ancestor that carries opportunity labels.
            $container = $anchor;
            $node = $anchor;
            for ($i = 0; $i < 6 && $node->parentNode instanceof \DOMElement; $i++) {
                $node = $node->parentNode;
                if (preg_match(self::LABEL_HINT, $node->textContent)) {
                    $container = $node;
                    break;
                }
            }

            // First non-BidPrime http link inside the block = original solicitation.
            $sourceUrl = null;
            foreach ($xpath->query('.//a', $container) as $a) {
                if (! $a instanceof \DOMElement) {
                    continue;
                }
                $h = trim($a->getAttribute('href'));
                if ($h !== '' && str_starts_with($h, 'http') && stripos($h, 'bidprime') === false) {
                    $sourceUrl = $h;
                    break;
                }
            }

            $blocks[] = [
                'title' => $this->collapse($anchor->textContent),
                'bidprimeUrl' => $href,
                'sourceUrl' => $sourceUrl,
                'text' => $this->nodeText($container),
            ];
        }

        return $blocks;
    }

    /** @return array<int,array{title:string,bidprimeUrl:?string,sourceUrl:?string,text:string}> */
    private function textBlocks(string $text): array
    {
        $chunks = preg_split('/\n\s*\n|\n[-=_*]{3,}\n/', $text) ?: [];
        $blocks = [];
        foreach ($chunks as $chunk) {
            $hasBidprime = (bool) preg_match('#https?://\S*bidprime\S*#i', $chunk);
            if (! preg_match(self::LABEL_HINT, $chunk) && ! $hasBidprime) {
                continue;
            }
            $bidUrl = null;
            if (preg_match('#(https?://\S*bidprime\S*)#i', $chunk, $m)) {
                $bidUrl = rtrim($m[1], ').,;');
            }
            $title = '';
            foreach (preg_split('/\n/', $chunk) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $title = $line;
                    break;
                }
            }
            $blocks[] = [
                'title' => $this->collapse($title),
                'bidprimeUrl' => $bidUrl,
                'sourceUrl' => null,
                'text' => $this->normalizeLines($chunk),
            ];
        }

        return $blocks;
    }

    private function toDto(array $block, GmailMessage $message): ?BidSourceResultDTO
    {
        $text = $block['text'];

        $title = $this->field($text, ['Title', 'Project Title', 'Project', 'Opportunity']) ?: ($block['title'] ?: null);
        if (! $title) {
            return null;
        }
        $title = Str::limit($title, 250, '');

        $solicitation = $this->field($text, ['Solicitation Number', 'Solicitation #', 'Solicitation', 'Bid Number', 'Bid #', 'RFP', 'RFQ', 'IFB', 'Reference', 'Notice ID', 'Notice']);
        $bidUrl = $block['bidprimeUrl'];

        $location = $this->field($text, ['Place of Performance', 'Location', 'Place']);
        $state = $this->field($text, ['State']);
        $city = null;
        if ($location) {
            if (preg_match('/^(.*?),\s*([A-Za-z]{2})$/', $location, $m)) {
                $city = $m[1];
                $state = $state ?: strtoupper($m[2]);
            } else {
                $city = $location;
            }
        }

        return new BidSourceResultDTO(
            externalId: $this->externalId($bidUrl, $solicitation, $title),
            source: 'bidprime',
            title: $title,
            solicitationNumber: $solicitation,
            agencyName: $this->field($text, ['Agency', 'Buyer', 'Issuing Agency', 'Organization']),
            subAgencyName: $this->field($text, ['Department', 'Division', 'Sub-Agency']),
            naicsCode: $this->match($text, '/NAICS\s*(?:Code)?\s*[:#\-]?\s*(\d{3,6})/i'),
            pscCode: $this->match($text, '/(?:PSC|UNSPSC)\s*(?:Code)?\s*[:#\-]?\s*([A-Z0-9]{2,8})/i'),
            setAsideType: $this->field($text, ['Set-Aside', 'Set Aside', 'SetAside']),
            contractType: $this->field($text, ['Opportunity Type', 'Contract Type', 'Type']),
            estimatedValue: $this->money($text),
            description: $this->field($text, ['Description', 'Summary', 'Scope', 'Details']),
            postedDate: $this->parseDate($this->field($text, ['Posted Date', 'Posted', 'Published', 'Issued', 'Date Posted'])),
            dueDate: $this->parseDate($this->field($text, ['Due Date', 'Closing Date', 'Response Deadline', 'Response Date', 'Deadline', 'Closing', 'Response', 'Bid Due', 'Due'])),
            placeOfPerformanceCity: $city,
            placeOfPerformanceState: $state ? strtoupper(Str::limit($state, 2, '')) : null,
            placeOfPerformanceCountry: ($city || $state) ? 'USA' : null,
            sourceUrl: $bidUrl ?: $block['sourceUrl'],
            rawData: array_filter([
                'channel' => 'email',
                'gmail_message_id' => $message->messageId,
                'email_subject' => $message->subject,
                'bidprime_url' => $bidUrl,
                'source_url' => $block['sourceUrl'],
                'block_text' => Str::limit($text, 4000),
            ], fn ($v) => $v !== null && $v !== ''),
        );
    }

    /** Read a `Label: value` field; tries synonyms longest-first, one line each. */
    private function field(string $text, array $labels): ?string
    {
        usort($labels, fn ($a, $b) => strlen($b) <=> strlen($a));
        $alt = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $labels));
        if (preg_match('/(?:^|\n)\s*(?:'.$alt.')\s*(?:#|No\.?|Number)?\s*[:\-]\s*(.+)/i', $text, $m)) {
            $v = trim($m[1]);
            return $v !== '' ? $v : null;
        }

        return null;
    }

    private function match(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }

    private function money(string $text): ?float
    {
        $v = $this->field($text, ['Estimated Value', 'Estimated Amount', 'Contract Value', 'Value', 'Amount', 'Budget', 'Estimated']);
        if (! $v) {
            return null;
        }
        $n = preg_replace('/[^\d.]/', '', explode(' ', trim($v))[0] ?? $v);

        return $n !== '' && $n !== '.' ? (float) $n : null;
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if (! $value) {
            return null;
        }
        // Keep the leading date token; drop trailing notes like "(local time)".
        $value = trim(preg_replace('/\b(at|by|EST|EDT|CST|CDT|MST|MDT|PST|PDT|local time)\b.*$/i', '', $value));
        try {
            $d = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return ($d->year >= 2000 && $d->year < 2100) ? $d : null;
    }

    private function externalId(?string $bidUrl, ?string $solicitation, string $title): string
    {
        if ($bidUrl && preg_match('~([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})~i', $bidUrl, $m)) {
            return 'bp-'.strtolower($m[1]);
        }
        if ($bidUrl && preg_match('~bid[/=#_-]?(\d{4,})~i', $bidUrl, $m)) {
            return 'bp-'.$m[1];
        }
        if ($bidUrl) {
            return 'bp-url-'.substr(sha1($bidUrl), 0, 16);
        }
        if ($solicitation) {
            return 'bp-sol-'.Str::slug($solicitation);
        }

        return 'bp-'.substr(sha1(Str::lower($title)), 0, 16);
    }

    /** Serialize an element to text, turning block tags into line breaks. */
    private function nodeText(\DOMElement $el): string
    {
        $html = '';
        foreach ($el->childNodes as $child) {
            $html .= $el->ownerDocument->saveHTML($child);
        }

        return $this->htmlToText($html);
    }

    private function htmlToText(string $html): string
    {
        $html = $this->stripNoise($html);
        $html = preg_replace('#<\s*(br|/p|/div|/tr|/li|/h[1-6]|/td)\s*/?>#i', "\n", $html);

        return $this->normalizeLines(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function normalizeLines(string $text): string
    {
        $lines = array_map(
            fn ($l) => trim(preg_replace('/[ \t\x{00a0}]+/u', ' ', $l)),
            preg_split('/\r\n|\r|\n/', $text) ?: [],
        );

        return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
    }

    private function collapse(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
