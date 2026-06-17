<?php

namespace App\Services\Proposals;

use App\Models\Organization;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\KnowledgeBaseService;
use App\Services\Documents\DocumentTextExtractionService;

/**
 * Drafts full proposal sections from a proposal's context using the active AI
 * provider. It writes in the org's Style Profile and the style of its past
 * proposals (RAG), and it asks clarifying questions rather than inventing facts
 * — drafts use only the provided context plus the user's answers.
 */
class ProposalWriterService
{
    /**
     * The standard proposal sections — aligned to QuakeLogic's exemplar proposal
     * template (learn it with `proposal:learn-template`). Every full draft covers
     * these in order, matching the gold-standard exemplar for each.
     *
     * section key => [label, guidance for the writer]
     */
    public const SECTIONS = [
        'executive_summary' => ['Executive Summary', 'A persuasive 3–5 paragraph overview: who QuakeLogic is, our understanding of the requirement, our proposed solution, and why we are the best, lowest-risk choice.'],
        'solution_snapshot' => ['Solution Snapshot', 'An at-a-glance summary of the proposed solution — key components, standards met, and headline benefits — as tight bullets and short sub-headings.'],
        'introduction' => ['Introduction', 'The purpose of the proposal, the scope of what it covers, and how the document is organized.'],
        'general_background' => ['General Background', 'QuakeLogic background, qualifications, domain expertise, certifications and credentials relevant to this requirement.'],
        'system_overview' => ['System Overview', 'A high-level description of the proposed system architecture and how the major components and subsystems work together.'],
        'technical_solution' => ['Technical Solution', 'The detailed technical approach: methodology, components, specifications, standards compliance, and exactly how each requirement is met. This is the most detailed, in-depth section — use clear sub-headings.'],
        'compliance_matrix' => ['Compliance Matrix', 'A requirement-by-requirement compliance statement mapping each solicitation/spec requirement to how the solution meets it (Comply / Partial / Exception) with remarks.'],
        'installation_deployment' => ['Installation & Deployment Plan', 'How the system is delivered, installed, integrated, tested and commissioned — phase by phase, with roles, responsibilities and acceptance criteria.'],
        'operations_maintenance' => ['Operations & Maintenance (O&M)', 'Post-deployment operations, maintenance, support, warranty, spares and service levels.'],
        'schedule_deliverables' => ['Schedule & Deliverables', 'The project schedule and milestones, and the list of deliverables with their timing.'],
        'commercial_proposal' => ['Commercial Proposal', 'Pricing narrative and basis of estimate, the value delivered, and commercial terms (narrative, not a raw price table).'],
        'capabilities_references' => ['Technical Capabilities & References', 'Relevant past performance, comparable prior projects, and references that demonstrate capability.'],
    ];

    /** Cap on uploaded-document text fed into a draft prompt (chars). */
    private const MAX_SOURCE_CHARS = 24000;

    /** Skip extracting from any single uploaded file larger than this (bytes). */
    private const MAX_FILE_BYTES = 30000000;

    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly KnowledgeBaseService $kb,
        private readonly ProposalStyleService $styles,
        private readonly DocumentTextExtractionService $textExtractor,
        private readonly ProposalTemplateService $templates,
    ) {}

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (string $key) => ['value' => $key, 'label' => self::SECTIONS[$key][0]],
            array_keys(self::SECTIONS),
        );
    }

    /**
     * Clarifying questions the writer needs answered before it can write this
     * section without inventing anything. Returns a list of short questions.
     *
     * @return array<int,string>
     */
    public function questions(ProposalSubmission $proposal, string $section): array
    {
        [$label, $guidance] = self::SECTIONS[$section] ?? [ucwords(str_replace('_', ' ', $section)), ''];
        $context = $this->baseContext($proposal, $section, $label, $guidance);

        $system = 'You are an expert U.S. government proposal writer preparing to write a section. '
            . 'Your job here is NOT to write the section, but to list the specific facts you would need from the '
            . 'bidder to write it well WITHOUT inventing anything. Only ask about details that are missing from the '
            . 'provided context. Ask short, concrete questions (max 6). '
            . 'Return ONLY JSON: {"questions": ["...", "..."]}. If you have everything you need, return {"questions": []}.';

        $prompt = "Section: {$label}\nGuidance: {$guidance}\n\nProposal context (JSON):\n" . json_encode($context);

        try {
            $raw = $this->ai->complete($system, $prompt, ['generationConfig' => ['responseMimeType' => 'application/json']]);
            $data = $this->decodeJson($raw);
            $questions = is_array($data['questions'] ?? null) ? $data['questions'] : [];

            return array_values(array_filter(array_map(
                fn ($q) => is_string($q) ? trim($q) : '',
                array_slice($questions, 0, 6),
            ), fn ($q) => $q !== ''));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Draft a section. $answers is a list of ['question'=>..,'answer'=>..] the
     * user provided; the writer uses them and never invents missing facts.
     *
     * @param array<int,array{question?:string,answer?:string}> $answers
     */
    public function draft(ProposalSubmission $proposal, string $section, array $answers = []): string
    {
        [$label, $guidance] = self::SECTIONS[$section] ?? [ucwords(str_replace('_', ' ', $section)), ''];
        $context = $this->baseContext($proposal, $section, $label, $guidance);

        // Org Style Profile (tone, voice, boilerplate, win themes, rules).
        $org = Organization::find($proposal->organization_id);
        if ($org && ($style = $this->styles->promptBlock($org)) !== '') {
            $context['style_profile'] = $style;
        }

        // Gold-standard exemplar from the org's learned proposal template (a prior
        // winning bid) — the writer matches its structure and depth, and reuses
        // standard/boilerplate language for the "standard" sections.
        $exemplar = $this->templates->exemplar($proposal->organization_id, $section);
        if ($exemplar) {
            $context['gold_standard_example'] = $exemplar['content'];
            $context['gold_standard_is_standard'] = ($exemplar['type'] ?? '') === 'standard';
        }

        // Relevant excerpts from the org's own data (RAG) to match style + facts.
        try {
            $past = $this->kb->contextFor(
                $proposal->organization_id,
                trim($label . ' ' . $proposal->project_name . ' ' . (string) $proposal->scope_summary),
                4,
            );
            if ($past !== '') {
                $context['relevant_past_work'] = $past;
            }
        } catch (\Throwable $e) {
            // RAG is best-effort.
        }

        // The user's answers to clarifying questions — authoritative, never overridden.
        $clean = [];
        foreach ($answers as $a) {
            $q = is_string($a['question'] ?? null) ? trim($a['question']) : '';
            $ans = is_string($a['answer'] ?? null) ? trim($a['answer']) : '';
            if ($ans !== '') {
                $clean[] = ['question' => $q, 'answer' => $ans];
            }
        }
        if ($clean !== []) {
            $context['answers'] = $clean;
        }

        return trim($this->ai->generateProposalSection($context, $section));
    }

    /** @return array<string,mixed> */
    private function baseContext(ProposalSubmission $proposal, string $section, string $label, string $guidance): array
    {
        $proposal->loadMissing('company:id,name', 'agency:id,name');

        $context = [
            'section' => $section,
            'section_label' => $label,
            'section_guidance' => $guidance,
            'project_name' => $proposal->project_name,
            'proposal_number' => $proposal->proposal_number,
            'solicitation_number' => $proposal->solicitation_number,
            'proposal_type' => $proposal->proposal_type?->value,
            'agency' => $proposal->agency?->name,
            'client' => $proposal->company?->name ?? $proposal->agency?->name,
            'value' => $proposal->proposal_value !== null ? (float) $proposal->proposal_value : null,
            'currency' => $proposal->currency,
            'due_date' => $proposal->due_date?->toDateString(),
            'scope_summary' => $proposal->scope_summary,
            'description' => $proposal->description,
        ];

        // The actual uploaded solicitation / bid / spec sheets — so drafts work
        // straight off the dumped documents without waiting on async embeddings.
        $docs = $this->sourceDocuments($proposal);
        if ($docs !== null) {
            $context['source_documents'] = $docs;
        }

        return $context;
    }

    /**
     * Concatenated text of the proposal's current uploaded files (the
     * solicitation/spec/bid sheets), char-capped to fit a prompt. Cached briefly
     * so drafting every section doesn't re-extract the same PDFs.
     */
    private function sourceDocuments(ProposalSubmission $proposal): ?string
    {
        $files = $proposal->files()->get(['id', 'path', 'mime_type', 'size', 'display_name', 'updated_at']);
        if ($files->isEmpty()) {
            return null;
        }

        $signature = $files->map(fn (ProposalFile $f) => $f->id . ':' . ($f->updated_at?->timestamp ?? 0))->implode(',');
        $cacheKey = 'proposal-source-docs:' . $proposal->id . ':' . md5($signature);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(15), function () use ($files) {
            $budget = self::MAX_SOURCE_CHARS;
            $parts = [];
            foreach ($files as $file) {
                if ($budget <= 0) {
                    break;
                }
                if (empty($file->path) || (int) ($file->size ?? 0) > self::MAX_FILE_BYTES) {
                    continue;
                }
                try {
                    $text = trim($this->textExtractor->extract($file->path, (string) $file->mime_type));
                } catch (\Throwable) {
                    continue;
                }
                if ($text === '') {
                    continue;
                }
                $slice = mb_substr($text, 0, $budget);
                $budget -= mb_strlen($slice);
                $parts[] = '### ' . ($file->display_name ?: 'Document') . "\n" . $slice;
            }

            return $parts === [] ? null : implode("\n\n", $parts);
        });
    }

    /** @return array<string,mixed> */
    private function decodeJson(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        if (! str_starts_with($raw, '{') && preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
