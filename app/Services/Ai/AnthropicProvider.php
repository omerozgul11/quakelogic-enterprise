<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    private string $model;
    private string $apiKey;

    public function __construct()
    {
        $this->model = config('ai.anthropic.model', 'claude-sonnet-4-6');
        $this->apiKey = config('ai.anthropic.api_key', '');
    }

    public function getName(): string { return 'anthropic'; }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function extractDocumentData(string $documentText, array $schema): array
    {
        $response = $this->complete(
            "You are an expert government contracting analyst. Extract structured data from procurement documents. Return valid JSON only.",
            "Extract: " . json_encode(array_keys($schema)) . "\n\nDocument:\n" . substr($documentText, 0, 12000)
        );
        return json_decode($response, true) ?? [];
    }

    public function generateProposalSummary(array $context): string
    {
        return $this->complete(
            "You are an expert proposal writer for government contracts.",
            "Generate a concise executive summary for: " . json_encode($context)
        );
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        $response = $this->complete(
            "You are a government contracting strategy expert. Return valid JSON with keys: recommendation, confidence, rationale, risk_factors, strengths, win_probability.",
            "Analyze and recommend Go/No-Go for: " . json_encode($opportunityData)
        );
        return json_decode($response, true) ?? ['recommendation' => 'REVIEW', 'confidence' => 0.5];
    }

    public function estimateWinProbability(array $context): float
    {
        $response = $this->complete(
            "Estimate win probability. Return JSON with 'probability' (0.0-1.0).",
            json_encode($context)
        );
        $data = json_decode($response, true);
        return (float) ($data['probability'] ?? 0.5);
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        $response = $this->complete(
            "Extract compliance requirements from RFP. Return JSON: {\"items\": [...]}",
            substr($rfpText, 0, 12000)
        );
        $data = json_decode($response, true);
        return $data['items'] ?? [];
    }

    public function generateFollowUpEmail(array $context): string
    {
        return $this->complete(
            "Write professional follow-up emails for government contracting.",
            "Write follow-up email for: " . json_encode($context)
        );
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', array_merge([
                'model' => $this->model,
                'max_tokens' => 2000,
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
