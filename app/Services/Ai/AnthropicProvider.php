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
            - "agency": the government agency or office ISSUING the solicitation.
            - "company": the buyer/client organization we would be doing business with
              (the customer named on the document), NOT QuakeLogic. If only a government
              agency is named, repeat it here.
            - "contacts": every named point of contact, contracting officer, contract
              specialist, buyer, or project/program manager — each with their OWN email,
              phone, and title exactly as written. Only include a person who has a name,
              an email, or a phone. Match each email/phone to the correct person.
            - Search the ENTIRE document for the real people — the header, any
              "Point of Contact"/"Contracting Officer" block, and the signature block.
              Prefer names that sit next to an email, phone, or explicit contact role.
            - IGNORE any References, Bibliography, Works Cited, Citations, Sources, or
              author/reference list. NEVER extract a person, email, or company from a
              citation (e.g. "A. Apamuk, et al., 2019") — those are not contacts.
            - "value": the total or ceiling dollar amount as a plain number (no \$ or commas).
            - All dates as YYYY-MM-DD.

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
}
