<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    private PendingRequest $client;
    private string $model;

    public function __construct()
    {
        $this->model = config('ai.providers.openai.model', 'gpt-4o');
        $this->client = Http::withToken((string) config('ai.providers.openai.api_key'))
            ->baseUrl(rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/'))
            ->timeout((int) config('ai.providers.openai.timeout', 60));
    }

    public function getName(): string { return 'openai'; }

    public function isAvailable(): bool
    {
        return !empty(config('ai.providers.openai.api_key'));
    }

    public function extractDocumentData(string $documentText, array $schema): array
    {
        $systemPrompt = "You are an expert government contracting analyst. Extract structured data from procurement documents. Return valid JSON only.";
        $userPrompt = "Extract the following fields from this document: " . json_encode(array_keys($schema)) . "\n\nDocument text:\n" . substr($documentText, 0, 12000);

        $response = $this->complete($systemPrompt, $userPrompt, ['response_format' => ['type' => 'json_object']]);
        return json_decode($response, true) ?? [];
    }

    public function generateProposalSummary(array $context): string
    {
        return $this->complete(
            "You are an expert proposal writer for government contracts. Generate professional executive summaries.",
            "Generate a concise executive summary for this proposal:\n" . json_encode($context)
        );
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        $response = $this->complete(
            "You are a government contracting strategy expert. Analyze opportunities and provide Go/No-Go recommendations. Return JSON.",
            "Analyze this opportunity and provide a Go/No-Go recommendation:\n" . json_encode($opportunityData),
            ['response_format' => ['type' => 'json_object']]
        );
        return json_decode($response, true) ?? ['recommendation' => 'REVIEW', 'confidence' => 0.5];
    }

    public function estimateWinProbability(array $context): float
    {
        $response = $this->complete(
            "You are an expert in government contract win probability estimation. Return a JSON object with a 'probability' field (0.0-1.0).",
            "Estimate win probability for: " . json_encode($context),
            ['response_format' => ['type' => 'json_object']]
        );
        $data = json_decode($response, true);
        return (float) ($data['probability'] ?? 0.5);
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        $response = $this->complete(
            "You are an expert in government RFP compliance. Extract compliance requirements. Return JSON array of items.",
            "Extract all compliance requirements from this RFP: " . substr($rfpText, 0, 12000),
            ['response_format' => ['type' => 'json_object']]
        );
        $data = json_decode($response, true);
        return $data['items'] ?? $data ?? [];
    }

    public function generateFollowUpEmail(array $context): string
    {
        return $this->complete(
            "You are a professional business development specialist. Write professional follow-up emails.",
            "Write a follow-up email for this context: " . json_encode($context)
        );
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        try {
            $body = array_merge([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 2000,
            ], $options);

            $response = $this->client->post('chat/completions', $body);

            if (!$response->successful()) {
                Log::error('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException('OpenAI API request failed: ' . $response->status());
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (\Exception $e) {
            Log::error('OpenAI provider error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
