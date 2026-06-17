<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    /**
     * Whether this provider can read documents/images natively (vision). Used to
     * decide when to send a file directly vs. fall back to extracted text.
     */
    public function supportsVision(): bool;

    /**
     * Read a shipping document (PDF) or label image and return the shipments it
     * contains. Non-vision providers return []. Each row has at least a
     * tracking_number, plus optional carrier, recipient, deadline and scope.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractShipments(string $base64Data, string $mediaType): array;

    /**
     * Extract structured data from document text.
     */
    public function extractDocumentData(string $documentText, array $schema): array;

    /**
     * Extract structured procurement data by reading the original document
     * (PDF/image) natively — far more complete than text extraction for the
     * client/company name, form fields, dates, etc. Non-vision providers return
     * [] so the caller falls back to extractDocumentData(text).
     *
     * @return array<string, mixed>
     */
    public function extractDocumentVision(string $base64Data, string $mediaType): array;

    /**
     * Answer a question using live web research (search-grounded). Returns a
     * concise, factual summary, or '' when the provider can't research the web.
     */
    public function research(string $query): string;

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
     * Draft a full proposal section (e.g. executive summary, technical approach)
     * from structured proposal context. Returns prose text.
     */
    public function generateProposalSection(array $context, string $section): string;

    /**
     * General completion: send a prompt, get text back.
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string;

    /**
     * Embed a list of texts into vectors (for the knowledge base / RAG). Returns
     * one float vector per input text, in order. Returns [] when the provider
     * has no embeddings capability or the call fails.
     *
     * @param  array<int,string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array;
}
