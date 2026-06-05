<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    /**
     * Extract structured data from document text.
     */
    public function extractDocumentData(string $documentText, array $schema): array;

    /**
     * Generate a proposal summary.
     */
    public function generateProposalSummary(array $context): string;

    /**
     * Generate a Go/No-Go recommendation.
     */
    public function generateGoNoGoRecommendation(array $opportunityData): array;

    /**
     * Estimate win probability.
     */
    public function estimateWinProbability(array $context): float;

    /**
     * Generate compliance matrix from document text.
     */
    public function generateComplianceMatrix(string $rfpText): array;

    /**
     * Generate a follow-up email draft.
     */
    public function generateFollowUpEmail(array $context): string;

    /**
     * General completion: send a prompt, get text back.
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string;
}
