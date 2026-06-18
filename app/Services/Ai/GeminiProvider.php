<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini provider (Google AI Studio API). Implements the full
 * AiProviderInterface so it powers every AI feature in the app. Mirrors the
 * structure of AnthropicProvider: structured methods request JSON output, and
 * any failure (missing key, 4xx/5xx, free-tier 429 quota, unparseable response)
 * falls back to the deterministic FakeAiProvider so the UI never hard-fails.
 *
 * Gemini natively reads PDFs/images, so it also supports vision extraction.
 */
class GeminiProvider implements AiProviderInterface
{
    private string $model;
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->model = (string) config('ai.providers.gemini.model', 'gemini-2.5-flash');
        $this->apiKey = (string) config('ai.providers.gemini.api_key', '');
        $this->baseUrl = rtrim((string) config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $this->timeout = (int) config('ai.providers.gemini.timeout', 60);
    }

    public function getName(): string { return 'gemini'; }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function supportsVision(): bool { return true; }

    /**
     * Extract structured procurement fields. Same output contract as the other
     * providers (the intake pipeline depends on the contacts/company/agency
     * shape), so we define the JSON shape in the prompt and normalise the result.
     */
    public function extractDocumentData(string $documentText, array $schema): array
    {
        $text = trim($documentText);

        if (! $this->isAvailable() || mb_strlen($text) < 40) {
            return $this->fallback()->extractDocumentData($documentText, $schema);
        }

        [$system, $rules] = $this->extractionPrompt();
        $prompt = $rules . "\n\nDocument:\n" . $text;

        try {
            $raw = $this->generate($system, mb_substr($prompt, 0, 16000), ['responseMimeType' => 'application/json']);
            $data = $this->decodeJsonObject($raw);
        } catch (\Throwable $e) {
            Log::warning('Gemini extraction failed; using regex fallback', ['error' => $e->getMessage()]);
            $data = null;
        }

        if (! is_array($data) || $data === []) {
            return $this->fallback()->extractDocumentData($documentText, $schema);
        }

        return $this->normalizeExtraction($data, mb_strlen($text));
    }

    /**
     * Read the ORIGINAL document (PDF/image) natively — far more complete than
     * text extraction (the company/client, cover-form fields, etc.). Returns []
     * on any failure so the caller falls back to the text path.
     */
    public function extractDocumentVision(string $base64Data, string $mediaType): array
    {
        if (! $this->isAvailable() || $base64Data === '') {
            return [];
        }

        [$system, $rules] = $this->extractionPrompt();
        $instruction = $rules
            . "\n\nRead the ATTACHED procurement document and extract the JSON. Use the WHOLE "
            . "document — the cover/title page, any SF-1449 or cover form, and every form field — "
            . "to find the company/client, agency, point-of-contact, dates and value.";

        try {
            $filePart = ['inline_data' => ['mime_type' => $mediaType, 'data' => $base64Data]];
            $raw = $this->generate($system, $instruction, ['responseMimeType' => 'application/json'], [$filePart]);
            $data = $this->decodeJsonObject($raw);
        } catch (\Throwable $e) {
            Log::warning('Gemini vision extraction failed', ['error' => $e->getMessage()]);
            return [];
        }

        return is_array($data) && $data !== [] ? $this->normalizeExtraction($data, 0) : [];
    }

    /** Web-search-grounded research (Google Search tool). '' on failure. */
    public function research(string $query): string
    {
        if (! $this->isAvailable() || trim($query) === '') {
            return '';
        }

        $system = 'You are a research assistant. Use web search to find accurate, current, factual '
            . 'information. Be concise and specific. If you cannot verify a fact, say so plainly. Never fabricate.';

        try {
            return trim($this->generate($system, $query, ['maxOutputTokens' => 800], [], [['google_search' => (object) []]]));
        } catch (\Throwable $e) {
            Log::warning('Gemini research failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /** Shared extraction system prompt + JSON schema/rules. @return array{0:string,1:string} */
    private function extractionPrompt(): array
    {
        $system = <<<'SYS'
            You are an expert U.S. government-contracting analyst. You read a procurement
            document (RFP, RFQ, IFB, quote, or award) and return ONLY the people and
            organizations actually named in it. Never invent a name, email, phone, company,
            agency, or date — if the document does not state it, use null. Do not include
            QuakeLogic or its staff: QuakeLogic is the bidder reading the document, never a
            party to extract. Return ONE JSON object and nothing else — no prose, no markdown.
            SYS;

        $rules = <<<'TXT'
            Extract this exact JSON shape (use null for anything not explicitly present):

            {
              "project_name": string|null,
              "solicitation_number": string|null,
              "agency": string|null,
              "company": string|null,
              "naics": string|null,
              "set_aside": string|null,
              "due_date": "YYYY-MM-DD"|null,
              "value": number|null,
              "scope": string|null,
              "key_dates": { "<label>": "YYYY-MM-DD" },
              "requirements": [string],
              "contacts": [ { "name": string|null, "email": string|null, "phone": string|null, "title": string|null } ]
            }

            Rules:
            - The title page and front matter (cover sheet, any SF-1449/cover form,
              and everything up to the Table of Contents) are AUTHORITATIVE. Take
              project_name, solicitation_number, agency, due_date, and value from there
              whenever they appear; prefer those over anything mentioned later in the body.
            - "project_name": the official title of the solicitation/project exactly as it
              reads on the title page — not a section heading or a sentence from the body.
            - "agency": the government agency or office ISSUING the solicitation.
            - "company": the buyer/client organization we would be doing business with
              (the customer named on the document), NOT QuakeLogic. If only a government
              agency is named, repeat it here.
            - "contacts": every named point of contact, contracting officer, contract
              specialist, buyer, or project/program manager — each with their OWN email,
              phone, and title exactly as written. Only include a person who has a name,
              an email, or a phone. Match each email/phone to the correct person.
            - For contacts, prefer the title page / "Point of Contact" / "Contracting
              Officer" block; otherwise use the header and signature block. Prefer names
              that sit next to an email, phone, or explicit contact role.
            - IGNORE any References, Bibliography, Works Cited, Citations, Sources, or
              author/reference list. NEVER extract a project name, person, email, company,
              or date from a citation (e.g. "A. Apamuk, et al., 2019") — those are not
              proposal data.
            - "value": the total or ceiling dollar amount as a plain number (no $ or commas).
            - All dates as YYYY-MM-DD.
            - If a field is not clearly stated, use null rather than guessing from unrelated text.
            TXT;

        return [$system, $rules];
    }

    public function generateProposalSummary(array $context): string
    {
        try {
            $text = trim($this->generate(
                "You are an expert proposal writer for government contracts. Write a polished, concise executive summary in plain prose — no preamble, no markdown headings.",
                "Generate an executive summary for this opportunity/proposal:\n" . json_encode($context)
            ));
            return $text !== '' ? $text : $this->fallback()->generateProposalSummary($context);
        } catch (\Throwable $e) {
            Log::warning('Gemini summary failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateProposalSummary($context);
        }
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        try {
            $data = $this->decodeJsonObject($this->generate(
                "You are a government contracting strategy expert. Return ONLY a JSON object with keys: "
                . "recommendation (GO|NO-GO|REVIEW), confidence (0-1), rationale (string), "
                . "risk_factors (string[]), strengths (string[]), win_probability (0-1). No prose, no markdown.",
                "Analyze and recommend Go/No-Go for:\n" . json_encode($opportunityData),
                ['responseMimeType' => 'application/json']
            ));
            if (is_array($data) && ! empty($data['recommendation'])) {
                $data['_provider'] = 'gemini';
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini go/no-go failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->generateGoNoGoRecommendation($opportunityData);
    }

    public function estimateWinProbability(array $context): float
    {
        try {
            $data = $this->decodeJsonObject($this->generate(
                "Estimate the win probability for this government bid. Return ONLY JSON: {\"probability\": <0.0-1.0>}. No prose.",
                json_encode($context),
                ['responseMimeType' => 'application/json']
            ));
            if (is_array($data) && isset($data['probability']) && is_numeric($data['probability'])) {
                return max(0.0, min(1.0, (float) $data['probability']));
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini win-probability failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->estimateWinProbability($context);
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        try {
            $data = $this->decodeJsonObject($this->generate(
                "Extract every compliance requirement from this RFP. Return ONLY JSON: "
                . "{\"items\": [{\"requirement_reference\": string, \"requirement\": string, \"status\": \"pending\", \"compliance_approach\": string}]}. No prose.",
                mb_substr($rfpText, 0, 16000),
                ['responseMimeType' => 'application/json']
            ));
            if (is_array($data) && ! empty($data['items']) && is_array($data['items'])) {
                return $data['items'];
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini compliance matrix failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->generateComplianceMatrix($rfpText);
    }

    public function generateFollowUpEmail(array $context): string
    {
        try {
            $text = trim($this->generate(
                "Write a professional, ready-to-send follow-up email for a government contracting proposal. "
                . "Return only the email (Subject line + body), no commentary.",
                "Write a follow-up email for:\n" . json_encode($context)
            ));
            return $text !== '' ? $text : $this->fallback()->generateFollowUpEmail($context);
        } catch (\Throwable $e) {
            Log::warning('Gemini follow-up email failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateFollowUpEmail($context);
        }
    }

    public function generateProposalSection(array $context, string $section): string
    {
        $label = $context['section_label'] ?? ucwords(str_replace('_', ' ', $section));
        $guidance = $context['section_guidance'] ?? '';
        try {
            $text = trim($this->generate(
                "You are an expert U.S. government proposal writer. Write the \"{$label}\" section in polished, persuasive, "
                . "compliant prose with short paragraphs and clear sub-headings. Be THOROUGH and DETAILED — government "
                . "evaluators expect depth; fully develop the section rather than summarizing. "
                . "The context's 'gold_standard_example' is the SAME section type from a prior winning QuakeLogic proposal: "
                . "match its structure, sub-headings, level of technical detail, length and professional tone. When "
                . "'gold_standard_is_standard' is true, reuse its standard/boilerplate language closely (company background, "
                . "methodology and framing), changing only the project-specific facts; otherwise use it as a structural model "
                . "and write THIS proposal's specifics. "
                . "Ground the section in the context's 'source_documents' (the actual solicitation, bid sheet and spec "
                . "sheets) — address their stated requirements directly. "
                . "Match the tone, voice and conventions in the context's 'style_profile', and mirror any 'relevant_past_work' "
                . "excerpts. Treat the context's 'answers' as authoritative facts. "
                . "Use ONLY facts present in the context — do NOT invent company details, past performance, personnel, "
                . "certifications, numbers or dates. Where a needed detail is still missing, insert a short bracketed note "
                . "like [NEEDS: …] instead of guessing. No preamble, no markdown code fences.",
                "Section to write: {$label}\n{$guidance}\n\nProposal context (JSON):\n" . json_encode($context),
                ['maxOutputTokens' => 8192]
            ));
            return $text !== '' ? $text : $this->fallback()->generateProposalSection($context, $section);
        } catch (\Throwable $e) {
            Log::warning('Gemini proposal section failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateProposalSection($context, $section);
        }
    }

    /**
     * Read a shipping document (PDF) or label photo natively and pull out every
     * shipment. Returns rows keyed tracking_number, carrier, recipient, deadline
     * and scope, or [] on any failure so the importer can fall back.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractShipments(string $base64Data, string $mediaType): array
    {
        if (! $this->isAvailable() || $base64Data === '') {
            return [];
        }

        $system = <<<'SYS'
            You are a logistics data extractor. You read shipping labels, packing slips,
            carrier confirmations, and manifests, and return ONLY the shipments actually
            shown. Never invent a tracking number, name, address, or date. Return ONE JSON
            object and nothing else — no prose, no markdown.
            SYS;

        $instruction = <<<'TXT'
            Extract every distinct package/shipment in this file. Return exactly this JSON:

            {
              "shipments": [
                {
                  "tracking_number": string,
                  "carrier": "ups"|"fedex"|"usps"|"dhl"|null,
                  "recipient_name": string|null,
                  "recipient_address": string|null,
                  "ship_date": "YYYY-MM-DD"|null,
                  "deadline": "YYYY-MM-DD"|null,
                  "scope": "domestic"|"international"|null
                }
              ]
            }

            Rules:
            - tracking_number is REQUIRED for every entry. Omit any shipment you can't find a tracking number for.
            - UPS tracking numbers look like 1Z followed by 16 letters/digits. Read carefully; do not guess digits.
            - "recipient_name"/"recipient_address" are the SHIP TO party, not the sender.
            - "scope": international if the ship-to country is outside the United States, otherwise domestic. Use null if unclear.
            - Dates as YYYY-MM-DD. Use null for anything not clearly shown.
            - If there are no shipments, return {"shipments": []}.
            TXT;

        try {
            $filePart = ['inline_data' => ['mime_type' => $mediaType, 'data' => $base64Data]];
            $raw = $this->generate($system, $instruction, ['responseMimeType' => 'application/json'], [$filePart]);
            $data = $this->decodeJsonObject($raw);
        } catch (\Throwable $e) {
            Log::warning('Gemini shipment extraction failed', ['error' => $e->getMessage()]);
            return [];
        }

        $rows = [];
        foreach ($data['shipments'] ?? [] as $s) {
            if (! is_array($s)) {
                continue;
            }
            $tn = $this->cleanString($s['tracking_number'] ?? null);
            if (! $tn) {
                continue;
            }
            $rows[] = [
                'tracking_number' => preg_replace('/\s+/', '', $tn),
                'carrier' => $this->cleanString($s['carrier'] ?? null),
                'recipient_name' => $this->cleanString($s['recipient_name'] ?? null),
                'recipient_address' => $this->cleanString($s['recipient_address'] ?? null),
                'deadline' => $this->cleanString($s['deadline'] ?? null),
                'scope' => $this->cleanString($s['scope'] ?? null),
            ];
        }

        return $rows;
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        try {
            // Gemini puts generation params under generationConfig; honour that if
            // a caller passes one, otherwise just send the prompt.
            return $this->generate($systemPrompt, $userPrompt, $options['generationConfig'] ?? []);
        } catch (\Exception $e) {
            Log::error('Gemini provider error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function generateFromMedia(string $systemPrompt, string $userPrompt, array $files, array $options = []): string
    {
        $fileParts = [];
        foreach ($files as $f) {
            if (! empty($f['data']) && ! empty($f['mime'])) {
                $fileParts[] = ['inline_data' => ['mime_type' => $f['mime'], 'data' => $f['data']]];
            }
        }

        try {
            return $this->generate($systemPrompt, $userPrompt, $options['generationConfig'] ?? [], $fileParts);
        } catch (\Exception $e) {
            Log::error('Gemini generateFromMedia error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function embed(array $texts): array
    {
        if (! $this->isAvailable() || $texts === []) {
            return [];
        }

        $model = (string) config('ai.providers.gemini.embed_model', 'gemini-embedding-001');
        $requests = array_map(fn ($t) => [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => mb_substr((string) $t, 0, 8000)]]],
        ], array_values($texts));

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ])->timeout($this->timeout)
              ->post("{$this->baseUrl}/v1beta/models/{$model}:batchEmbedContents", ['requests' => $requests]);

            if ($response->status() === 429) {
                Log::warning('Gemini embed rate limit / quota hit (free tier)', ['model' => $model]);
                return [];
            }
            if (! $response->successful()) {
                Log::warning('Gemini embed failed', ['status' => $response->status()]);
                return [];
            }

            return array_map(
                fn ($e) => array_map('floatval', $e['values'] ?? []),
                $response->json('embeddings') ?? []
            );
        } catch (\Throwable $e) {
            Log::warning('Gemini embed error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Core request to Gemini's generateContent endpoint. $fileParts are prepended
     * to the user content (e.g. inline_data for PDFs/images). Throws on non-2xx;
     * logs a distinct warning on a free-tier 429 so quota exhaustion is visible.
     *
     * @param  array<int,array<string,mixed>>  $fileParts
     */
    private function generate(string $system, string $user, array $genConfig = [], array $fileParts = [], array $tools = []): string
    {
        $parts = $fileParts;
        $parts[] = ['text' => $user];

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => array_merge(['maxOutputTokens' => 4096], $genConfig),
        ];
        if (trim($system) !== '') {
            $body['system_instruction'] = ['parts' => [['text' => $system]]];
        }
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'content-type' => 'application/json',
        ])->timeout($this->timeout)
          ->post("{$this->baseUrl}/v1beta/models/{$this->model}:generateContent", $body);

        if ($response->status() === 429) {
            Log::warning('Gemini rate limit / quota hit (free tier)', ['model' => $this->model]);
            throw new \RuntimeException('Gemini rate limit (429)');
        }
        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API request failed: ' . $response->status());
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = '';
        foreach ($parts as $p) {
            if (isset($p['text'])) {
                $text .= $p['text'];
            }
        }

        return $text;
    }

    /**
     * Coerce the model's JSON into the shape the intake pipeline expects.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalizeExtraction(array $data, int $chars): array
    {
        $contacts = [];
        foreach ($data['contacts'] ?? [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $row = [
                'name' => $this->cleanString($c['name'] ?? null),
                'email' => $this->cleanString($c['email'] ?? null),
                'phone' => $this->cleanString($c['phone'] ?? null),
                'title' => $this->cleanString($c['title'] ?? null),
            ];
            if ($row['name'] || $row['email'] || $row['phone']) {
                $contacts[] = $row;
            }
        }
        $data['contacts'] = array_slice($contacts, 0, 12);

        if ($contacts) {
            $data['contact_person'] ??= $contacts[0]['name'];
            $data['contact_title'] ??= $contacts[0]['title'];
            $data['email'] ??= $contacts[0]['email'];
            $data['phone'] ??= $contacts[0]['phone'];
        }

        if (isset($data['value'])) {
            $data['value'] = (float) preg_replace('/[^0-9.]/', '', (string) $data['value']) ?: null;
        }

        $data = array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
        $data['_provider'] = 'gemini';
        $data['_chars'] = $chars;
        $key = ['project_name', 'agency', 'email', 'solicitation_number', 'due_date', 'value'];
        $data['_extraction_confidence'] = round(min(0.98, 0.55 + count(array_intersect_key($data, array_flip($key))) * 0.07), 2);

        return $data;
    }

    private function cleanString(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        return ($v === '' || strcasecmp($v, 'null') === 0) ? null : $v;
    }

    /**
     * Pull the first JSON object out of a model response, tolerating markdown
     * fences and surrounding prose.
     *
     * @return array<string,mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        if (! str_starts_with($raw, '{') && preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function fallback(): FakeAiProvider
    {
        return new FakeAiProvider();
    }
}
