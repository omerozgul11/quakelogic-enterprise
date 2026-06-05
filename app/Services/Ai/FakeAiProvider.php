<?php

namespace App\Services\Ai;

class FakeAiProvider implements AiProviderInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function extractDocumentData(string $documentText, array $schema): array
    {
        return [
            'agency' => 'Department of Defense',
            'contact_person' => 'John Smith',
            'email' => 'john.smith@example.gov',
            'phone' => '(703) 555-0100',
            'bid_number' => 'FAKE-2024-001',
            'project_name' => 'Fake Extracted Project Name',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'budget' => '$500,000',
            'scope' => 'This is a fake AI-extracted scope of work for demonstration purposes.',
            'evaluation_criteria' => [
                'Technical Approach (40%)',
                'Past Performance (30%)',
                'Price (30%)',
            ],
            'required_certifications' => ['ISO 9001', 'CMMI Level 3'],
            'submission_instructions' => 'Submit electronically via SAM.gov',
            '_extraction_confidence' => 0.92,
            '_provider' => 'fake',
        ];
    }

    public function generateProposalSummary(array $context): string
    {
        $projectName = $context['project_name'] ?? 'the project';
        $agency = $context['agency'] ?? 'the agency';
        return "This proposal addresses the requirements set forth by {$agency} for {$projectName}. "
            . "QuakeLogic brings extensive expertise and a proven track record to deliver exceptional value. "
            . "Our technical approach emphasizes innovation, reliability, and cost-effectiveness. "
            . "[This is a demo AI-generated summary — connect a real AI provider to generate live summaries.]";
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        return [
            'recommendation' => 'GO',
            'confidence' => 0.75,
            'rationale' => 'This opportunity aligns well with QuakeLogic\'s core competencies. '
                . 'The technical requirements match our existing capabilities. '
                . '[Demo AI recommendation — real analysis requires AI provider credentials.]',
            'risk_factors' => [
                'Tight deadline may strain proposal team capacity',
                'Incumbent has existing relationship with agency',
            ],
            'strengths' => [
                'Strong technical alignment',
                'Competitive pricing advantage',
            ],
            'win_probability' => 0.65,
            '_provider' => 'fake',
        ];
    }

    public function estimateWinProbability(array $context): float
    {
        // Fake deterministic calculation for demo
        return 0.65;
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        return [
            [
                'requirement_reference' => 'Section L.1',
                'requirement' => 'Offeror shall submit technical volume not exceeding 30 pages.',
                'status' => 'pending',
                'compliance_approach' => 'Will ensure technical volume adheres to page limit.',
            ],
            [
                'requirement_reference' => 'Section L.2',
                'requirement' => 'Past performance references: minimum 3 relevant contracts.',
                'status' => 'pending',
                'compliance_approach' => 'Will include 3 relevant federal contracts.',
            ],
            [
                'requirement_reference' => 'Section M.1',
                'requirement' => 'Technical evaluation: demonstrated understanding of requirements.',
                'status' => 'pending',
                'compliance_approach' => 'Technical volume will address all PWS requirements.',
            ],
        ];
    }

    public function generateFollowUpEmail(array $context): string
    {
        $contactName = $context['contact_name'] ?? 'Contracting Officer';
        $proposalTitle = $context['proposal_title'] ?? 'our proposal';
        $daysSinceSubmission = $context['days_since_submission'] ?? 14;

        return "Subject: Follow-up: {$proposalTitle} - Status Inquiry\n\n"
            . "Dear {$contactName},\n\n"
            . "I hope this message finds you well. I am following up on the proposal we submitted "
            . "{$daysSinceSubmission} days ago regarding {$proposalTitle}.\n\n"
            . "We remain very interested in this opportunity and are available to provide any additional "
            . "information or clarification that may be helpful during your evaluation process.\n\n"
            . "Please let us know if you have any questions or need additional documentation.\n\n"
            . "Thank you for your consideration.\n\n"
            . "Best regards,\n[Your Name]\n[Your Title]\nQuakeLogic\n\n"
            . "[This is a demo AI-generated email — connect a real AI provider for live generation.]";
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return "[FAKE AI RESPONSE]\n\n"
            . "System: " . substr($systemPrompt, 0, 100) . "...\n\n"
            . "This is a demonstration response from the fake AI provider. "
            . "To enable real AI responses, configure OPENAI_API_KEY or ANTHROPIC_API_KEY in your .env file "
            . "and set AI_PROVIDER=openai or AI_PROVIDER=anthropic.";
    }
}
