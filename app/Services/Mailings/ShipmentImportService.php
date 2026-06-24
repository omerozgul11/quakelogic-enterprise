<?php

namespace App\Services\Mailings;

use App\Enums\Carrier;
use App\Enums\ShipmentScope;
use App\Services\Ai\AiProviderFactory;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Turns whatever the user throws at the importer — a pasted blob of tracking
 * numbers, a CSV, a label PDF, or a photo of a label — into a clean list of
 * candidate shipment rows ready to be created + tracked.
 *
 * CSV/TXT are parsed locally; PDFs and images are read by the AI provider's
 * native document/vision support (no OCR binary required). Each input also
 * yields a short diagnostic so the UI can say what came from where.
 */
class ShipmentImportService
{
    /** UPS tracking numbers: 1Z + 16 alphanumerics. The reliable, low-false-positive signal. */
    private const UPS_RE = '/\b1Z[0-9A-Z]{16}\b/i';

    /**
     * A number explicitly labelled as a tracking/shipment code (any carrier).
     * The captured token is contiguous (no spaces) so it can't swallow the words
     * that follow the label, e.g. "Tracking Number: 1Z… shipped today".
     */
    private const LABELLED_RE = '/(?:tracking|shipment|consignment|waybill|airwaybill|awb)\s*(?:#|no\.?|number|id|code)?\s*[:\-]?\s*([0-9A-Za-z][0-9A-Za-z\-]{8,33}[0-9A-Za-z])/i';

    /** Max file size (bytes) we'll send to the AI for reading. */
    private const AI_MAX_BYTES = 12 * 1024 * 1024;

    public function __construct(private readonly DocumentTextExtractionService $text) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array{carrier?:string, reference_type?:?string, scope?:string, recipient_name?:?string, deadline?:?string}  $defaults
     * @return array{candidates: Collection<int, array<string,mixed>>, sources: array<int, array<string,mixed>>}
     */
    public function build(array $files, ?string $pastedText, array $defaults): array
    {
        $sources = [];
        $rows = collect();

        if ($pastedText !== null && trim($pastedText) !== '') {
            $found = $this->numbersFromText($pastedText, $defaults);
            $rows = $rows->concat($found);
            $sources[] = ['name' => 'Pasted text', 'kind' => 'paste', 'found' => count($found)];
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            [$found, $note] = $this->fromFile($file, $defaults);
            $rows = $rows->concat($found);
            $sources[] = [
                'name' => $file->getClientOriginalName(),
                'kind' => $this->kind($file),
                'found' => count($found),
                'note' => $note,
            ];
        }

        // Dedupe by tracking number, keeping the first (richest) occurrence.
        $candidates = $rows
            ->filter(fn ($r) => ! empty($r['tracking_number']))
            ->unique(fn ($r) => strtoupper($r['tracking_number']))
            ->values();

        return ['candidates' => $candidates, 'sources' => $sources];
    }

    /**
     * @return array{0: array<int, array<string,mixed>>, 1: ?string}
     */
    private function fromFile(UploadedFile $file, array $defaults): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if ($ext === 'csv' || $mime === 'text/csv' || str_ends_with($mime, '/csv')) {
            return [$this->fromCsv($file, $defaults), null];
        }

        // PDFs and label photos: read with the model's native document/vision support.
        if ($ext === 'pdf' || str_starts_with($mime, 'image/') || $mime === 'application/pdf') {
            return $this->fromAiFile($file, $defaults, $ext);
        }

        // Plain text / Word docs: extract text locally, then scan for numbers.
        if (in_array($ext, ['txt', 'md', 'docx', 'doc'], true) || str_starts_with($mime, 'text/')) {
            $text = $this->safeText($file);
            $found = $this->numbersFromText($text, $defaults);

            return [$found, $found === [] ? 'No tracking numbers found in the text.' : null];
        }

        return [[], 'Unsupported file type.'];
    }

    /**
     * @return array{0: array<int, array<string,mixed>>, 1: ?string}
     */
    private function fromAiFile(UploadedFile $file, array $defaults, string $ext): array
    {
        if ($file->getSize() > self::AI_MAX_BYTES) {
            return [[], 'File is too large to read automatically (max 12 MB).'];
        }

        $provider = AiProviderFactory::default();
        if (! $provider->supportsVision()) {
            return [[], 'Automatic reading needs a vision-capable AI provider (e.g. Gemini or Anthropic). Paste the tracking numbers or use a CSV instead.'];
        }

        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            return [[], 'Could not read the uploaded file.'];
        }

        $mime = $ext === 'pdf' ? 'application/pdf' : (string) $file->getMimeType();
        $extracted = $provider->extractShipments(base64_encode($contents), $mime);

        $rows = [];
        foreach ($extracted as $e) {
            $rows[] = $this->normalizeRow($e, $defaults);
        }

        $note = $rows === []
            ? 'Could not find a tracking number in this file.'
            : null;

        return [array_values(array_filter($rows)), $note];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function fromCsv(UploadedFile $file, array $defaults): array
    {
        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw; // strip UTF-8 BOM
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($raw)) ?: [],
            fn ($l) => trim($l) !== ''
        ));
        if ($lines === []) {
            return [];
        }

        // Excel in some locales exports with ';' or tab delimiters — detect it.
        $delim = $this->detectDelimiter($lines[0]);
        $header = str_getcsv($lines[0], $delim, '"', '\\');
        $map = $this->mapHeader($header);

        // No recognizable header → every line is data, first column = tracking number.
        $dataLines = $lines;
        if ($map === []) {
            $map = ['tracking_number' => 0];
        } else {
            array_shift($dataLines);
        }

        $rows = [];
        foreach ($dataLines as $line) {
            $cells = str_getcsv($line, $delim, '"', '\\');
            $get = fn (string $key) => isset($map[$key], $cells[$map[$key]]) ? trim((string) $cells[$map[$key]]) : null;

            $rows[] = $this->normalizeRow([
                'tracking_number' => $get('tracking_number'),
                'recipient_name' => $get('recipient_name'),
                'recipient_address' => $get('recipient_address'),
                'deadline' => $get('deadline'),
                'scope' => $get('scope'),
                'carrier' => $get('carrier'),
            ], $defaults);
        }

        return array_values(array_filter($rows));
    }

    /** Pick the most frequent of the common CSV delimiters in the header line. */
    private function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];
        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * Map known column names to their index. Returns [] when no column is recognized.
     *
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $aliases = [
            'tracking_number' => ['trackingnumber', 'tracking', 'trackingno', 'track', 'shipmentcode', 'shipment', 'code', 'number', '1z', 'awb', 'waybill'],
            'recipient_name' => ['recipient', 'recipientname', 'to', 'shipto', 'consignee', 'agency', 'name', 'company'],
            'recipient_address' => ['address', 'recipientaddress', 'shiptoaddress', 'destination'],
            'deadline' => ['deadline', 'due', 'duedate', 'duedate', 'needby'],
            'scope' => ['scope', 'category', 'type', 'domesticinternational', 'intl'],
            'carrier' => ['carrier', 'service'],
        ];

        $map = [];
        foreach ($header as $i => $cell) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $cell))) ?? '';
            if ($norm === '') {
                continue;
            }
            foreach ($aliases as $field => $names) {
                if (isset($map[$field])) {
                    continue;
                }
                if (in_array($norm, $names, true)) {
                    $map[$field] = $i;
                }
            }

            // Forgiving catch-alls so slightly different headings still map
            // (e.g. "Tracking Numbers", "Ship To", "Due Date", "Category").
            if (! isset($map['tracking_number']) && str_contains($norm, 'track')) {
                $map['tracking_number'] = $i;
            }
            if (! isset($map['recipient_name']) && (str_contains($norm, 'recipient') || str_contains($norm, 'consignee') || str_contains($norm, 'shipto'))) {
                $map['recipient_name'] = $i;
            }
            if (! isset($map['deadline']) && (str_contains($norm, 'deadline') || str_contains($norm, 'duedate'))) {
                $map['deadline'] = $i;
            }
            if (! isset($map['scope']) && (str_contains($norm, 'scope') || str_contains($norm, 'categor'))) {
                $map['scope'] = $i;
            }
        }

        return $map;
    }

    /**
     * Pull tracking numbers out of free text, attaching the import defaults.
     *
     * @return array<int, array<string,mixed>>
     */
    private function numbersFromText(string $text, array $defaults): array
    {
        $numbers = [];

        if (preg_match_all(self::UPS_RE, $text, $m)) {
            foreach ($m[0] as $n) {
                $numbers[] = strtoupper($n);
            }
        }
        if (preg_match_all(self::LABELLED_RE, $text, $m)) {
            foreach ($m[1] as $n) {
                $clean = strtoupper(preg_replace('/[\s\-]/', '', $n) ?? $n);
                if (strlen($clean) >= 10) {
                    $numbers[] = $clean;
                }
            }
        }

        // When the text is just a list (one code per line/comma), accept each token.
        if ($numbers === []) {
            foreach (preg_split('/[\s,]+/', trim($text)) ?: [] as $tok) {
                $tok = strtoupper(trim($tok));
                if (preg_match('/^[0-9A-Z]{10,35}$/', $tok)) {
                    $numbers[] = $tok;
                }
            }
        }

        $rows = [];
        foreach (array_unique($numbers) as $n) {
            $rows[] = $this->normalizeRow(['tracking_number' => $n], $defaults);
        }

        return array_values(array_filter($rows));
    }

    /**
     * Normalize one candidate against the import defaults + the supported enums.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>|null  null if there's no usable tracking number
     */
    private function normalizeRow(array $row, array $defaults): ?array
    {
        $tn = preg_replace('/\s+/', '', (string) ($row['tracking_number'] ?? ''));
        if ($tn === '' || strlen($tn) < 6) {
            return null;
        }

        return [
            'tracking_number' => $tn,
            'carrier' => $this->carrier($row['carrier'] ?? null, $defaults),
            // Reference type only applies to carriers that use it (J.B. Hunt); the
            // controller normalises it per the final carrier when persisting.
            'reference_type' => $defaults['reference_type'] ?? null,
            'scope' => $this->scope($row['scope'] ?? null, $defaults),
            'recipient_name' => $this->str($row['recipient_name'] ?? null) ?? ($defaults['recipient_name'] ?? null),
            'recipient_address' => $this->str($row['recipient_address'] ?? null),
            'deadline' => $this->date($row['deadline'] ?? null) ?? ($defaults['deadline'] ?? null),
        ];
    }

    private function carrier(mixed $value, array $defaults): string
    {
        $default = $defaults['carrier'] ?? 'ups';
        $v = strtolower(trim((string) ($value ?? '')));
        if ($v === '') {
            return $default;
        }
        $carrier = Carrier::tryFrom($v);

        return ($carrier && $carrier->supported()) ? $carrier->value : $default;
    }

    private function scope(mixed $value, array $defaults): string
    {
        $default = $defaults['scope'] ?? 'domestic';
        $v = strtolower(trim((string) ($value ?? '')));

        return match (true) {
            str_starts_with($v, 'int') => ShipmentScope::International->value,
            str_starts_with($v, 'dom') || $v === 'us' || $v === 'usa' => ShipmentScope::Domestic->value,
            $v !== '' && ShipmentScope::tryFrom($v) !== null => $v,
            default => $default,
        };
    }

    private function date(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function str(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);

        return ($v === '' || strcasecmp($v, 'null') === 0) ? null : $v;
    }

    private function safeText(UploadedFile $file): string
    {
        try {
            // The extraction service reads from the local disk by relative path;
            // for a freshly-uploaded temp file, read it directly instead.
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['txt', 'md', 'csv'], true)) {
                return (string) @file_get_contents($file->getRealPath());
            }

            $stored = $file->store('imports-tmp', 'local');
            $text = $this->text->extract($stored, (string) $file->getMimeType());
            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);

            return $text;
        } catch (\Throwable) {
            return '';
        }
    }

    private function kind(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match (true) {
            $ext === 'csv' => 'csv',
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true) => 'image',
            default => 'doc',
        };
    }
}
