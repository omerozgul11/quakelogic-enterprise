<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalLlmProvider implements AiProviderInterface
{
    private string $host;
    private string $model;

    public function __construct()
    {
        $this->host = rtrim((string) config('ai.providers.local.base_url', 'http://localhost:11434'), '/');
        $this->model = config('ai.providers.local.model', 'llama3');
    }

    public function getName(): string { return 'local'; }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(3)->get($this->host . '/api/tags')->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function extractDocumentData(string $documentText, array $schema): array
    {
        $response = $this->complete(
            "You are a document extraction AI. Return valid JSON only.",
            "Extract: " . json_encode(array_keys($schema)) . "\n\n" . substr($documentText, 0, 8000)
        );
        return json_decode($response, true) ?? [];
    }

    public function generateProposalSummary(array $context): string
    {
        return $this->complete("You are a proposal writer.", "Summarize: " . json_encode($context));
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        $response = $this->complete("Return JSON Go/No-Go recommendation.", json_encode($opportunityData));
        return json_decode($response, true) ?? ['recommendation' => 'REVIEW'];
    }

    public function estimateWinProbability(array $context): float
    {
        $response = $this->complete("Return JSON with probability 0-1.", json_encode($context));
        return (float) (json_decode($response, true)['probability'] ?? 0.5);
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        $response = $this->complete("Extract compliance items. Return JSON array.", substr($rfpText, 0, 8000));
        return json_decode($response, true) ?? [];
    }

    public function generateFollowUpEmail(array $context): string
    {
        return $this->complete("Write professional follow-up emails.", json_encode($context));
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        try {
            $response = Http::timeout(120)->post($this->host . '/api/generate', [
                'model' => $this->model,
                'prompt' => $systemPrompt . "\n\n" . $userPrompt,
                'stream' => false,
            ]);
            return $response->json('response') ?? '';
        } catch (\Exception $e) {
            Log::error('Local LLM error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
