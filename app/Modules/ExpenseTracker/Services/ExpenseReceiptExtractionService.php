<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads a dropped receipt / invoice (image or PDF) and pulls the vendor, amount,
 * date, category, etc. into structured fields so the Add-Expense form can be
 * pre-filled for the user to review. Text-first (PDF), then native vision when
 * the provider supports it (photos of receipts). Returns ONLY what the document
 * actually states — never guesses — so the user confirms a pre-fill, not a
 * fabrication. Works with whatever AI provider is configured; the Fake provider
 * returns nothing and the user simply fills the form by hand.
 */
class ExpenseReceiptExtractionService
{
    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $text,
    ) {}

    /**
     * @return array{status:string,fields:array<string,mixed>}
     *         status: ok | empty | unavailable
     */
    public function extractFromUpload(UploadedFile $file, int $organizationId): array
    {
        if (! $this->ai->isAvailable()) {
            return ['status' => 'unavailable', 'fields' => []];
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $tmp = 'tmp/expense-extract/'.Str::ulid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        Storage::disk('local')->put($tmp, (string) file_get_contents($file->getRealPath()));

        try {
            $raw = $this->askProvider($tmp, $mime);
        } catch (Throwable) {
            $raw = '';
        } finally {
            Storage::disk('local')->delete($tmp);
        }

        $data = $this->decodeJsonObject($raw);
        if ($data === null) {
            return ['status' => 'empty', 'fields' => []];
        }

        $fields = $this->normalize($data, $organizationId);

        return ['status' => $fields === [] ? 'empty' : 'ok', 'fields' => $fields];
    }

    private function askProvider(string $path, string $mime): string
    {
        $system = <<<'SYS'
            You read a single receipt or supplier invoice and return ONLY the details it
            explicitly states. Never invent a number, date, currency, vendor, or category —
            if the document does not state a field, use null. Amounts are the grand total
            actually payable (including tax). Return ONE JSON object and nothing else: no
            prose, no markdown fences.
            SYS;

        $shape = <<<'TXT'
            Extract this exact JSON shape (null for anything not explicitly present):

            {
              "vendor": string|null,            // merchant / supplier / payee name
              "amount": number|null,            // grand total payable, plain number, no symbols or commas
              "currency": "USD"|"EUR"|...|null, // 3-letter ISO currency code
              "expense_date": "YYYY-MM-DD"|null,// the invoice / receipt date
              "due_date": "YYYY-MM-DD"|null,    // payment due date if the document states one
              "description": string|null,       // one short line: what was bought
              "category": string|null,          // best fit: Material, Labor, Service, Rental Tools, Equipment, Subcontractor, Travel, Utilities, Office, Other
              "payment_method": "card"|"cash"|"bank_transfer"|"check"|"other"|null,
              "invoice_number": string|null
            }
            TXT;

        $text = trim($this->text->extract($path, $mime));

        if (mb_strlen($text) >= 40) {
            return $this->ai->complete($system, $shape."\n\nReceipt / invoice text:\n".mb_substr($text, 0, 12000));
        }

        if ($this->ai->supportsVision()) {
            $bytes = (string) Storage::disk('local')->get($path);

            return $this->ai->generateFromMedia($system, $shape, [[
                'mime' => $mime,
                'data' => base64_encode($bytes),
            ]]);
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalize(array $data, int $organizationId): array
    {
        $out = [];

        if (is_string($data['vendor'] ?? null) && trim($data['vendor']) !== '') {
            $out['vendor'] = mb_substr(trim($data['vendor']), 0, 255);
        }

        $amount = $data['amount'] ?? null;
        if (is_numeric($amount) && (float) $amount > 0) {
            $out['amount'] = round((float) $amount, 2);
        }

        if (is_string($data['currency'] ?? null) && preg_match('/^[A-Za-z]{3}$/', trim($data['currency']))) {
            $out['currency'] = strtoupper(trim($data['currency']));
        }

        foreach (['expense_date', 'due_date'] as $dateKey) {
            $d = $this->parseDate($data[$dateKey] ?? null);
            if ($d !== null) {
                $out[$dateKey] = $d;
            }
        }

        $description = is_string($data['description'] ?? null) ? trim($data['description']) : '';
        $invoiceNo = is_string($data['invoice_number'] ?? null) ? trim($data['invoice_number']) : '';
        if ($description === '' && $invoiceNo !== '') {
            $description = 'Invoice '.$invoiceNo;
        }
        if ($description !== '') {
            $out['description'] = mb_substr($description, 0, 500);
        }
        if ($invoiceNo !== '') {
            $out['notes'] = 'Invoice #'.$invoiceNo;
        }

        $method = is_string($data['payment_method'] ?? null) ? strtolower(trim($data['payment_method'])) : '';
        if (PaymentMethod::tryFrom($method) instanceof PaymentMethod) {
            $out['payment_method'] = $method;
        }

        // Match the free-text category to one the organization already uses.
        $categoryName = is_string($data['category'] ?? null) ? trim($data['category']) : '';
        if ($categoryName !== '') {
            $match = ExpenseCategory::query()
                ->where('organization_id', $organizationId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
                ->first();
            if ($match) {
                $out['expense_category_id'] = $match->id;
                $out['category_name'] = $match->name;
            }
        }

        return $out;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip ```json … ``` fences a model may wrap the object in.
        $raw = preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw;

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
