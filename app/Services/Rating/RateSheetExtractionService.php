<?php

namespace App\Services\Rating;

use App\Models\ShipmentRateQuote;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads the rate-sheet PDF a carrier emails back (DHL spot quote) and pulls the
 * price, transit, validity and lane into structured fields. Spot rates have no
 * API, so this is the bridge from "PDF in an email" to a filled-in quote.
 *
 * Uses the configured AI provider's generic completion (not extractDocumentData,
 * which is hard-wired to a procurement schema). Text-first; falls back to native
 * PDF vision when the provider supports it and the PDF has no extractable text
 * (scanned sheet). Returns ONLY the fields it actually found — never guesses — so
 * the user reviews a pre-fill rather than trusting a fabrication.
 */
class RateSheetExtractionService
{
    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $text,
    ) {}

    /**
     * @return array<string,mixed> recognised quote fields (empty when nothing usable)
     */
    public function extract(ShipmentRateQuote $quote): array
    {
        if (! $quote->hasDocument() || ! Storage::disk('local')->exists($quote->document_path)) {
            return [];
        }

        if (! $this->ai->isAvailable()) {
            return [];
        }

        $raw = $this->askProvider($quote);
        $data = $this->decodeJsonObject($raw);

        return $data === null ? [] : $this->normalize($data);
    }

    private function askProvider(ShipmentRateQuote $quote): string
    {
        $system = <<<'SYS'
            You read a freight/parcel carrier rate quote or rate-sheet document (e.g. a DHL
            spot quote PDF) and return ONLY the rate details it explicitly states. Never
            invent a number, date, currency, or location — if the document does not state a
            field, use null. Return ONE JSON object and nothing else: no prose, no markdown.
            SYS;

        $shape = <<<'TXT'
            Extract this exact JSON shape (null for anything not explicitly present):

            {
              "amount": number|null,            // the total quoted rate as a plain number, no symbols/commas
              "currency": "USD"|"EUR"|...|null, // 3-letter ISO code of the rate
              "service_level": string|null,     // the service/product name, e.g. "DHL Express Worldwide"
              "transit_days": number|null,      // transit time in days
              "estimated_delivery": "YYYY-MM-DD"|null,
              "expires_at": "YYYY-MM-DD"|null,   // quote validity / expiration date
              "quote_reference": string|null,   // the quote, offer, or reference number
              "weight": number|null,            // chargeable weight if shown
              "origin_city": string|null,
              "origin_postal": string|null,
              "dest_city": string|null,
              "dest_postal": string|null
            }
            TXT;

        try {
            // Scanned sheet with no extractable text → native PDF vision when available.
            $text = trim($this->text->extract($quote->document_path, (string) $quote->document_mime));

            if (mb_strlen($text) >= 40) {
                return $this->ai->complete($system, $shape."\n\nDocument:\n".mb_substr($text, 0, 16000));
            }

            if ($this->ai->supportsVision()) {
                $bytes = Storage::disk('local')->get($quote->document_path);

                return $this->ai->generateFromMedia($system, $shape, [[
                    'mime' => $quote->document_mime ?: 'application/pdf',
                    'data' => base64_encode($bytes),
                ]]);
            }
        } catch (Throwable $e) {
            Log::warning('Rate-sheet extraction failed', ['quote' => $quote->id, 'error' => $e->getMessage()]);
        }

        return '';
    }

    /** Pull the first JSON object out of a model response (tolerates code fences/prose). */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Keep only fields with a usable value, coerced to the quote's column types. */
    private function normalize(array $data): array
    {
        $out = [];

        $amount = $this->num($data['amount'] ?? null);
        if ($amount !== null) {
            $out['amount'] = $amount;
        }

        if (! empty($data['currency']) && is_string($data['currency'])) {
            $out['currency'] = strtoupper(substr(trim($data['currency']), 0, 3));
        }

        foreach (['service_level', 'quote_reference', 'origin_city', 'origin_postal', 'dest_city', 'dest_postal'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                $out[$key] = trim($data[$key]);
            }
        }

        $transit = $this->num($data['transit_days'] ?? null);
        if ($transit !== null) {
            $out['transit_days'] = (int) round($transit);
        }

        $weight = $this->num($data['weight'] ?? null);
        if ($weight !== null) {
            $out['weight'] = $weight;
        }

        foreach (['estimated_delivery', 'expires_at'] as $key) {
            $date = $this->date($data[$key] ?? null);
            if ($date !== null) {
                $out[$key] = $date;
            }
        }

        return $out;
    }

    private function num(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v) && preg_match('/-?\d+(?:\.\d+)?/', str_replace(',', '', $v), $m)) {
            return (float) $m[0];
        }

        return null;
    }

    private function date(mixed $v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
