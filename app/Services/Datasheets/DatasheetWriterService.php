<?php

namespace App\Services\Datasheets;

use App\Models\Datasheet;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns the dumped inputs of a Datasheet (pasted technical notes + uploaded spec
 * sheets + product photos) into a fully-written, structured product datasheet.
 * Spec PDFs and images are sent to the AI provider natively (vision); their text
 * is also extracted so a non-vision provider still has material to work from. The
 * result is normalised into the sections the branded PDF renders.
 */
class DatasheetWriterService
{
    /** Mimes we can hand to a vision model inline. */
    private const VISION_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    private const MAX_FILES = 6;
    private const MAX_BYTES = 15_000_000;
    private const MAX_SOURCE_CHARS = 16000;

    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $text,
    ) {}

    public function generate(Datasheet $datasheet): Datasheet
    {
        $sourceText = $this->extractSourceText($datasheet);
        $files = $this->mediaParts($datasheet);

        $raw = '';
        try {
            $raw = $this->ai->generateFromMedia($this->systemPrompt(), $this->userPrompt($datasheet, $sourceText), $files, [
                'generationConfig' => ['responseMimeType' => 'application/json', 'maxOutputTokens' => 4096],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Datasheet generation failed; using fallback.', ['error' => $e->getMessage(), 'datasheet' => $datasheet->id]);
        }

        $sections = $this->parse($raw) ?? $this->fallback($datasheet, $sourceText);

        $datasheet->forceFill([
            'sections' => $sections,
            'tagline' => $datasheet->tagline ?: ($sections['tagline'] ?? null),
            'source_text' => $sourceText !== '' ? Str::limit($sourceText, self::MAX_SOURCE_CHARS, '') : null,
            'status' => 'generated',
            'generated_at' => now(),
        ])->save();

        return $datasheet;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a senior technical copywriter producing a product DATASHEET for QuakeLogic, a precision instruments and machinery vendor. From the supplied product photos, spec sheets, and notes, write a complete, professional datasheet.

        Return ONLY a JSON object with this exact shape:
        {
          "tagline": "a short 4-8 word marketing line",
          "overview": "2-3 tight paragraphs introducing the product, its purpose and standout value. Plain prose, no markdown headings.",
          "key_features": ["concise feature/benefit bullet", "..."],
          "specifications": [{"label": "Spec name", "value": "Spec value with units"}],
          "applications": ["typical use / industry", "..."]
        }

        Rules: ground every spec and claim in the provided material — never invent numbers, model names, or certifications. If a value is genuinely unknown, omit it rather than guessing. Keep it precise and benefit-oriented. 5-10 key_features, as many real specifications as the inputs support, 3-6 applications.
        PROMPT;
    }

    private function userPrompt(Datasheet $d, string $sourceText): string
    {
        $parts = ["PRODUCT: {$d->product_name}"];
        if ($d->model_number) {
            $parts[] = "MODEL / PART NO: {$d->model_number}";
        }
        if (trim((string) $d->input_notes) !== '') {
            $parts[] = "TECHNICAL NOTES PROVIDED BY THE USER:\n" . trim((string) $d->input_notes);
        }
        if (trim($sourceText) !== '') {
            $parts[] = "TEXT EXTRACTED FROM THE UPLOADED SPEC SHEETS:\n" . $sourceText;
        }
        $parts[] = 'Attached images/PDFs (if any) are the product photos and spec sheets — read them for specifications and appearance. Produce the datasheet JSON now.';

        return implode("\n\n", $parts);
    }

    private function extractSourceText(Datasheet $d): string
    {
        $out = '';
        foreach ($d->mediaOfKind('spec') as $m) {
            if (mb_strlen($out) >= self::MAX_SOURCE_CHARS) {
                break;
            }
            try {
                $path = Storage::disk($m['disk'] ?? 'local')->path($m['path']);
                $text = $this->text->extract($path, $m['mime'] ?? '');
                if (trim($text) !== '') {
                    $out .= "\n\n--- {$m['name']} ---\n" . trim($text);
                }
            } catch (\Throwable) {
                // skip unreadable file
            }
        }

        return Str::limit(trim($out), self::MAX_SOURCE_CHARS, '');
    }

    /** @return array<int,array{mime:string,data:string}> */
    private function mediaParts(Datasheet $d): array
    {
        if (! $this->ai->supportsVision()) {
            return [];
        }

        // Images first (appearance), then spec PDFs.
        $media = array_merge($d->mediaOfKind('image'), $d->mediaOfKind('spec'));
        $parts = [];
        foreach ($media as $m) {
            if (count($parts) >= self::MAX_FILES) {
                break;
            }
            $mime = (string) ($m['mime'] ?? '');
            if (! in_array($mime, self::VISION_MIMES, true) || (int) ($m['size'] ?? 0) > self::MAX_BYTES) {
                continue;
            }
            try {
                $bytes = Storage::disk($m['disk'] ?? 'local')->get($m['path']);
                if ($bytes) {
                    $parts[] = ['mime' => $mime, 'data' => base64_encode($bytes)];
                }
            } catch (\Throwable) {
                // skip
            }
        }

        return $parts;
    }

    /**
     * Parse the model's JSON response into normalised sections, or null if the
     * response isn't usable (caller then uses the deterministic fallback).
     *
     * @return array<string,mixed>|null
     */
    private function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // Strip ```json fences if the model added them.
        $raw = preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw;
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $overview = trim((string) ($data['overview'] ?? ''));
        $specs = $this->normalizeSpecs($data['specifications'] ?? []);
        if ($overview === '' && $specs === []) {
            return null;
        }

        return [
            'tagline' => $this->cleanLine($data['tagline'] ?? null),
            'overview' => $overview,
            'key_features' => $this->normalizeList($data['key_features'] ?? []),
            'specifications' => $specs,
            'applications' => $this->normalizeList($data['applications'] ?? []),
        ];
    }

    /** Deterministic datasheet from the raw inputs, when the AI is unavailable. */
    private function fallback(Datasheet $d, string $sourceText): array
    {
        $material = trim($d->input_notes . "\n" . $sourceText);
        $overview = trim((string) $d->input_notes) !== ''
            ? trim((string) $d->input_notes)
            : "{$d->product_name} — datasheet generated from the supplied technical material. Connect a live AI provider (AI_PROVIDER=gemini) to expand this into full marketing-grade copy.";

        return [
            'tagline' => $d->tagline,
            'overview' => $overview,
            'key_features' => [],
            'specifications' => $this->specsFromText($material),
            'applications' => [],
        ];
    }

    /** @return array<int,array{label:string,value:string}> */
    private function specsFromText(string $text): array
    {
        $specs = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (count($specs) >= 25) {
                break;
            }
            if (preg_match('/^\s*[\-\*\x{2022}]?\s*(.{2,40}?)\s*[:\x{2013}\-]\s+(.{1,80})\s*$/u', $line, $m)) {
                $specs[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
            }
        }

        return $specs;
    }

    /** @return array<int,array{label:string,value:string}> */
    private function normalizeSpecs(mixed $specs): array
    {
        if (! is_array($specs)) {
            return [];
        }
        $out = [];
        foreach ($specs as $s) {
            if (is_array($s) && trim((string) ($s['label'] ?? '')) !== '') {
                $out[] = ['label' => $this->cleanLine($s['label']) ?? '', 'value' => $this->cleanLine($s['value'] ?? '') ?? ''];
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    private function normalizeList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($v) => $this->cleanLine(is_array($v) ? implode(' ', $v) : $v) ?? '', $list)));
    }

    private function cleanLine(mixed $v): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $s = trim(preg_replace('/\s+/', ' ', (string) $v) ?? '');

        return $s !== '' ? $s : null;
    }
}
