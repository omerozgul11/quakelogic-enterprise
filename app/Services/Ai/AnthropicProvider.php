<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    private string $model;
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->model = (string) config('ai.providers.anthropic.model', 'claude-sonnet-4-6');
        $this->apiKey = (string) config('ai.providers.anthropic.api_key', '');
        $this->baseUrl = rtrim((string) config('ai.providers.anthropic.base_url', 'https://api.anthropic.com'), '/');
    }

    public function getName(): string { return 'anthropic'; }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function supportsVision(): bool { return true; }

    /**
     * Extract structured fields from a procurement document with a real LLM.
     *
     * The output contract (and especially the `contacts` shape) is defined here
     * rather than from the caller's thin schema, because the downstream intake
     * pipeline consumes contacts/company/agency/email/phone that the schema
     * doesn't enumerate. On any failure — missing key, API error, unparseable
     * or empty response — we fall back to the deterministic regex provider so
     * the user still gets a best-effort result instead of nothing.
     */
    public function extractDocumentData(string $documentText, array $schema): array
    {
        $text = trim($documentText);

        if (!$this->isAvailable() || mb_strlen($text) < 40) {
            return $this->fallback()->extractDocumentData($documentText, $schema);
        }

        $system = <<<'SYS'
            You are an expert U.S. government-contracting analyst. You read a procurement
            document (RFP, RFQ, IFB, quote, or award) and return ONLY the people and
            organizations actually named in it. Never invent a name, email, phone, company,
            agency, or date — if the document does not state it, use null. Do not include
            QuakeLogic or its staff: QuakeLogic is the bidder reading the document, never a
            party to extract. Return ONE JSON object and nothing else — no prose, no markdown.
            SYS;

        $prompt = <<<TXT
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
            - "value": the total or ceiling dollar amount as a plain number (no \$ or commas).
            - All dates as YYYY-MM-DD.
            - If a field is not clearly stated on the title page or front matter, use null
              rather than guessing from unrelated body text.

            Document:
            {$text}
            TXT;

        try {
            $raw = $this->complete($system, mb_substr($prompt, 0, 16000));
            $data = $this->decodeJsonObject($raw);
        } catch (\Throwable $e) {
            Log::warning('Anthropic extraction failed; using regex fallback', ['error' => $e->getMessage()]);
            $data = null;
        }

        if (!is_array($data) || $data === []) {
            return $this->fallback()->extractDocumentData($documentText, $schema);
        }

        return $this->normalizeExtraction($data, mb_strlen($text));
    }

    /**
     * Coerce the model's JSON into the shape the intake pipeline expects:
     * a clean contacts list, back-compat single-contact fields, and metadata.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalizeExtraction(array $data, int $chars): array
    {
        $contacts = [];
        foreach ($data['contacts'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $row = [
                'name' => $this->cleanString($c['name'] ?? null),
                'email' => $this->cleanString($c['email'] ?? null),
                'phone' => $this->cleanString($c['phone'] ?? null),
                'title' => $this->cleanString($c['title'] ?? null),
            ];
            // A usable contact needs at least a name, email, or phone.
            if ($row['name'] || $row['email'] || $row['phone']) {
                $contacts[] = $row;
            }
        }
        $data['contacts'] = array_slice($contacts, 0, 12);

        // Back-compat single-contact fields from the first contact.
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
        $data['_provider'] = 'anthropic';
        $data['_chars'] = $chars;
        $key = ['project_name', 'agency', 'email', 'solicitation_number', 'due_date', 'value'];
        $data['_extraction_confidence'] = round(min(0.98, 0.55 + count(array_intersect_key($data, array_flip($key))) * 0.07), 2);

        return $data;
    }

    private function cleanString(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return ($v === '' || strcasecmp($v, 'null') === 0) ? null : $v;
    }

    /**
     * Pull the first JSON object out of a model response, tolerating markdown
     * code fences and surrounding prose.
     *
     * @return array<string,mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        // Strip ```json ... ``` fences if present.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        // Fall back to the outermost { ... } span.
        if (!str_starts_with($raw, '{') && preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function fallback(): FakeAiProvider
    {
        return new FakeAiProvider();
    }

    public function generateProposalSummary(array $context): string
    {
        try {
            $text = trim($this->complete(
                "You are an expert proposal writer for government contracts. Write a polished, concise executive summary in plain prose — no preamble, no markdown headings.",
                "Generate an executive summary for this opportunity/proposal:\n" . json_encode($context)
            ));
            return $text !== '' ? $text : $this->fallback()->generateProposalSummary($context);
        } catch (\Throwable $e) {
            Log::warning('Anthropic summary failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateProposalSummary($context);
        }
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        try {
            $data = $this->decodeJsonObject($this->complete(
                "You are a government contracting strategy expert. Return ONLY a JSON object with keys: "
                . "recommendation (GO|NO-GO|REVIEW), confidence (0-1), rationale (string), "
                . "risk_factors (string[]), strengths (string[]), win_probability (0-1). No prose, no markdown.",
                "Analyze and recommend Go/No-Go for:\n" . json_encode($opportunityData)
            ));
            if (is_array($data) && !empty($data['recommendation'])) {
                $data['_provider'] = 'anthropic';
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('Anthropic go/no-go failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->generateGoNoGoRecommendation($opportunityData);
    }

    public function estimateWinProbability(array $context): float
    {
        try {
            $data = $this->decodeJsonObject($this->complete(
                "Estimate the win probability for this government bid. Return ONLY JSON: {\"probability\": <0.0-1.0>}. No prose.",
                json_encode($context)
            ));
            if (is_array($data) && isset($data['probability']) && is_numeric($data['probability'])) {
                return max(0.0, min(1.0, (float) $data['probability']));
            }
        } catch (\Throwable $e) {
            Log::warning('Anthropic win-probability failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->estimateWinProbability($context);
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        try {
            $data = $this->decodeJsonObject($this->complete(
                "Extract every compliance requirement from this RFP. Return ONLY JSON: "
                . "{\"items\": [{\"requirement_reference\": string, \"requirement\": string, \"status\": \"pending\", \"compliance_approach\": string}]}. No prose.",
                mb_substr($rfpText, 0, 16000)
            ));
            if (is_array($data) && !empty($data['items']) && is_array($data['items'])) {
                return $data['items'];
            }
        } catch (\Throwable $e) {
            Log::warning('Anthropic compliance matrix failed; using fallback', ['error' => $e->getMessage()]);
        }
        return $this->fallback()->generateComplianceMatrix($rfpText);
    }

    public function generateFollowUpEmail(array $context): string
    {
        try {
            $text = trim($this->complete(
                "Write a professional, ready-to-send follow-up email for a government contracting proposal. "
                . "Return only the email (Subject line + body), no commentary.",
                "Write a follow-up email for:\n" . json_encode($context)
            ));
            return $text !== '' ? $text : $this->fallback()->generateFollowUpEmail($context);
        } catch (\Throwable $e) {
            Log::warning('Anthropic follow-up email failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateFollowUpEmail($context);
        }
    }

    public function generateProposalSection(array $context, string $section): string
    {
        $label = $context['section_label'] ?? ucwords(str_replace('_', ' ', $section));
        $guidance = $context['section_guidance'] ?? '';
        try {
            $text = trim($this->complete(
                "You are an expert U.S. government proposal writer. Write the \"{$label}\" section in polished, persuasive, "
                . "compliant prose. Match the context's 'style_profile' tone/voice and mirror any 'relevant_past_work' style; "
                . "treat 'answers' as authoritative. Use ONLY facts in the context — do not invent company details, past "
                . "performance, personnel, certifications, numbers or dates; insert [NEEDS: …] where a detail is missing. "
                . "No preamble, no markdown code fences.",
                "Section to write: {$label}\n{$guidance}\n\nProposal context (JSON):\n" . json_encode($context)
            ));
            return $text !== '' ? $text : $this->fallback()->generateProposalSection($context, $section);
        } catch (\Throwable $e) {
            Log::warning('Anthropic proposal section failed; using fallback', ['error' => $e->getMessage()]);
            return $this->fallback()->generateProposalSection($context, $section);
        }
    }

    /**
     * Read a shipping document (PDF) or a label photo (PNG/JPEG/WebP/GIF) directly
     * with the model's native document/vision support — no OCR binary required —
     * and pull out every distinct shipment it can find.
     *
     * Returns a list of rows: tracking_number (required), carrier, recipient_name,
     * recipient_address, ship_date, deadline, scope. Returns [] on any failure so
     * the importer can fall back / report the file as unreadable.
     *
     * @param  string  $base64Data  Base64-encoded file contents.
     * @param  string  $mediaType   e.g. application/pdf, image/png, image/jpeg.
     * @return array<int, array<string, mixed>>
     */
    public function extractShipments(string $base64Data, string $mediaType): array
    {
        if (! $this->isAvailable() || $base64Data === '') {
            return [];
        }

        $isPdf = $mediaType === 'application/pdf';
        $fileBlock = $isPdf
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64Data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64Data]];

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
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'pdfs-2024-09-25',
                'content-type' => 'application/json',
            ])->timeout((int) config('ai.providers.anthropic.timeout', 60))
              ->post($this->baseUrl . '/v1/messages', [
                  'model' => $this->model,
                  'max_tokens' => 4096,
                  'system' => $system,
                  'messages' => [[
                      'role' => 'user',
                      'content' => [$fileBlock, ['type' => 'text', 'text' => $instruction]],
                  ]],
              ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Anthropic file extraction failed: ' . $response->status());
            }

            $data = $this->decodeJsonObject($response->json('content.0.text') ?? '');
        } catch (\Throwable $e) {
            Log::warning('Anthropic shipment extraction failed', ['error' => $e->getMessage()]);

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
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout((int) config('ai.providers.anthropic.timeout', 60))
              ->post($this->baseUrl . '/v1/messages', array_merge([
                'model' => $this->model,
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ], $options));

            if (!$response->successful()) {
                throw new \RuntimeException('Anthropic API request failed: ' . $response->status());
            }

            return $response->json('content.0.text') ?? '';
        } catch (\Exception $e) {
            Log::error('Anthropic provider error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function extractDocumentVision(string $base64Data, string $mediaType): array { return []; }

    public function research(string $query): string { return ''; }

    /** Anthropic has no first-party embeddings API; RAG uses the Gemini tier. */
    public function embed(array $texts): array { return []; }
}
