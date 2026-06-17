<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\LocalAssistantResponder;
use App\Services\Ai\PortalContextService;
use App\Services\Proposals\ProposalWriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiProviderInterface $ai) {}

    public function chat(Request $request, PortalContextService $portal, LocalAssistantResponder $local, \App\Services\Ai\KnowledgeBaseService $kb): JsonResponse
    {
        $this->authorize('use ai assistant');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $user = $request->user();

        // Cache concrete, standalone questions (no conversation context) per user
        // for a short window: repeated "what's due this week?"-type asks return
        // instantly and don't spend Gemini free-tier quota. Follow-up turns (with
        // history) depend on context, so they're never served from cache.
        $cacheable = empty($validated['history']);
        $cacheKey = 'quakebot:reply:' . $user->organization_id . ':' . $user->id
            . ':' . sha1(mb_strtolower(trim($validated['message'])));
        if ($cacheable && ($hit = \Illuminate\Support\Facades\Cache::get($cacheKey))) {
            return response()->json([
                'reply' => $hit['reply'],
                'provider' => $hit['provider'],
                'cached' => true,
            ]);
        }

        $context = $portal->forUser($user);

        // Knowledge base (RAG): pull the most relevant chunks from the org's data
        // (proposals, opportunities, contacts, …) to ground the answer.
        // Best-effort — never break chat on failure.
        $kbContext = '';
        try {
            $kbContext = $kb->contextFor($user->organization_id, $validated['message'], 8);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('KB retrieval failed for chat', ['error' => $e->getMessage()]);
        }

        $system = "You are QuakeBot, the assistant for QuakeLogic's government bid & proposal platform, "
            . "which also tracks mailed proposal shipments via UPS (the Shipments section). "
            . "Be concise, helpful and professional. Help with proposals, opportunities, agencies, contacts, "
            . "deadlines, follow-ups and shipment/delivery tracking. "
            . "Today is " . now()->toFormattedDateString() . ". "
            . "Use the live, organisation-scoped portal data below to answer questions about the user's own "
            . "proposals and shipments. Cite specifics (proposal numbers, tracking numbers, dates) when relevant. "
            . "If the answer isn't in the data, say so briefly rather than inventing details.\n\n"
            . "=== LIVE PORTAL DATA ===\n" . $context . "\n=== END PORTAL DATA ===";

        if ($kbContext !== '') {
            $system .= "\n\n=== RELEVANT RECORDS (excerpts from this org's proposals, documents, "
                . "opportunities, companies, contacts, agencies, follow-ups & contracts) ===\n"
                . $kbContext . "\n=== END RELEVANT RECORDS ===";
        }

        $conversation = '';
        foreach (array_slice($validated['history'] ?? [], -8) as $m) {
            $conversation .= ($m['role'] === 'user' ? 'User: ' : 'Assistant: ') . $m['content'] . "\n";
        }
        $conversation .= 'User: ' . $validated['message'];

        // Try the AI provider; if it's unavailable (no credits, outage, etc.),
        // fall back to the built-in responder so the chatbot still answers from
        // live portal data instead of erroring out.
        try {
            $reply = trim((string) $this->ai->complete($system, $conversation));
            if ($reply === '') {
                throw new \RuntimeException('Empty AI response');
            }
            $mode = $this->ai->getName();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('QuakeBot AI fallback engaged', ['error' => $e->getMessage()]);
            $reply = $local->answerForUser($user, $validated['message']);
            $mode = 'builtin';
        }

        // Only cache real provider answers — never the offline fallback, so a
        // transient outage doesn't pin a degraded reply for the whole TTL.
        if ($cacheable && $mode !== 'builtin' && $reply !== '') {
            \Illuminate\Support\Facades\Cache::put(
                $cacheKey,
                ['reply' => $reply, 'provider' => $mode],
                now()->addSeconds((int) config('ai.chat_cache_ttl', 900)),
            );
        }

        return response()->json([
            'reply' => $reply,
            'provider' => $mode,
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('use ai assistant');

        $user = $request->user();

        $query = AiAnalysis::where('organization_id', $user->organization_id)
            ->with(['createdBy:id,name', 'subject'])
            ->latest();
        if ($request->filled('type')) {
            $query->where('analysis_type', $request->input('type'));
        }

        $history = $query->limit(30)->get()->map(fn (AiAnalysis $a) => [
            'id' => $a->id,
            'type' => $a->analysis_type,
            'type_label' => $this->analysisTypeLabel($a->analysis_type),
            'status' => $a->status instanceof \BackedEnum ? $a->status->value : $a->status,
            'preview' => $this->analysisPreview($a),
            'subject_label' => $this->analysisSubjectLabel($a),
            'subject_url' => $this->analysisSubjectUrl($a),
            'by' => $a->createdBy?->name,
            'created_at' => $a->created_at?->toIso8601String(),
        ])->values();

        // Filter options = the analysis types actually present in this org's history.
        $historyTypes = AiAnalysis::where('organization_id', $user->organization_id)
            ->distinct()->pluck('analysis_type')->filter()
            ->map(fn ($t) => ['value' => $t, 'label' => $this->analysisTypeLabel($t)])
            ->values();

        // Subjects the quick-action analyses (Go/No-Go, Win Probability,
        // Proposal Summary) can run against — open opportunities and the
        // proposals this user can see.
        $opportunities = Opportunity::where('organization_id', $user->organization_id)
            ->active()->orderByDesc('id')->limit(300)
            ->get(['id', 'title', 'solicitation_number'])
            ->map(fn (Opportunity $o) => [
                'id' => $o->id,
                'label' => trim(($o->solicitation_number ? "[{$o->solicitation_number}] " : '') . $o->title),
            ])->values();

        $proposalQuery = ProposalSubmission::forOrganization($user->organization_id);
        if (! $user->can('view all proposals')) {
            $proposalQuery->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }
        $proposals = $proposalQuery->orderByDesc('id')->limit(300)
            ->get(['id', 'proposal_number', 'project_name'])
            ->map(fn (ProposalSubmission $p) => [
                'id' => $p->id,
                'label' => trim(($p->proposal_number ? "{$p->proposal_number} — " : '') . $p->project_name),
            ])->values();

        return Inertia::render('AI/Index', [
            'history' => $history,
            'historyTypes' => $historyTypes,
            'filters' => ['type' => $request->input('type')],
            'aiProvider' => $this->ai->getName(),
            'aiAvailable' => $this->ai->isAvailable(),
            'subjects' => ['opportunity' => $opportunities, 'proposal' => $proposals],
        ]);
    }

    private function analysisTypeLabel(?string $type): string
    {
        return match ($type) {
            'go_no_go' => 'Go / No-Go',
            'win_probability' => 'Win Probability',
            'proposal_summary' => 'Proposal Summary',
            'executive_summary' => 'Executive Summary',
            'follow_up_email' => 'Follow-up Email',
            'compliance_matrix' => 'Compliance Matrix',
            'document_extraction' => 'Document Extraction',
            'proposal_draft' => 'Proposal Draft',
            default => ucwords(str_replace('_', ' ', (string) $type)),
        };
    }

    /** A short human preview of an analysis result. */
    private function analysisPreview(AiAnalysis $a): string
    {
        $o = is_array($a->output) ? $a->output : [];

        $text = match ($a->analysis_type) {
            'go_no_go' => trim(($o['recommendation'] ?? '') . (isset($o['confidence']) ? ' · ' . round(((float) $o['confidence']) * 100) . '% confidence' : '')),
            'win_probability' => isset($o['probability']) ? round(((float) $o['probability']) * 100) . '% win probability' : '',
            'proposal_draft' => (string) ($o['text'] ?? ''),
            'follow_up_email' => (string) ($o['email_draft'] ?? $o['text'] ?? ''),
            'proposal_summary', 'executive_summary' => (string) ($o['text'] ?? ''),
            default => '',
        };

        if (trim($text) === '') {
            foreach ($o as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $text = $v;
                    break;
                }
            }
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $text));

        return $text === '' ? '—' : \Illuminate\Support\Str::limit($text, 120);
    }

    private function analysisSubjectLabel(AiAnalysis $a): ?string
    {
        $s = $a->subject;
        if ($s instanceof ProposalSubmission) {
            return trim(($s->proposal_number ?? '') . ' — ' . ($s->project_name ?? ''), ' —') ?: $s->proposal_number;
        }
        if ($s instanceof Opportunity) {
            return $s->title;
        }

        return null;
    }

    private function analysisSubjectUrl(AiAnalysis $a): ?string
    {
        $s = $a->subject;
        if ($s instanceof ProposalSubmission) {
            return "/proposals/{$s->id}";
        }
        if ($s instanceof Opportunity) {
            return "/opportunities/{$s->id}";
        }

        return null;
    }

    /**
     * Proposal Writer workspace — a standalone QuakeAI destination to draft full
     * proposal sections. Lists the proposals the user can edit; when one is
     * selected (?proposal=ID) it loads that proposal's already-saved sections so
     * the writer panel can assemble the document. Drafting/saving/exporting reuse
     * the per-proposal endpoints on ProposalController.
     */
    public function writer(Request $request): Response
    {
        $this->authorize('use ai assistant');

        $user = $request->user();
        $canWrite = $user->can('update proposals');

        $proposals = [];
        if ($canWrite) {
            $query = ProposalSubmission::forOrganization($user->organization_id)
                ->with(['company:id,name', 'agency:id,name'])
                ->withCount('sections');

            // Mirror the update policy: admins edit any proposal, everyone else
            // only the ones they own, manage or are an attached team member of.
            if (! $user->hasRole('Super Admin')) {
                $query->where(fn ($q) => $q
                    ->where('owner_id', $user->id)
                    ->orWhere('proposal_manager_id', $user->id)
                    ->orWhereHas('teamMembers', fn ($t) => $t->where('user_id', $user->id)));
            }

            $proposals = $query->orderByDesc('updated_at')->limit(300)->get()->map(fn (ProposalSubmission $p) => [
                'id' => $p->id,
                'proposal_number' => $p->proposal_number,
                'project_name' => $p->project_name,
                'client' => $p->company?->name ?? $p->agency?->name,
                'status' => $p->status?->label(),
                'due_date' => $p->due_date?->toDateString(),
                'sections_count' => (int) $p->sections_count,
            ])->values();
        }

        $selected = null;
        $savedSections = [];
        if ($request->filled('proposal')) {
            $proposal = ProposalSubmission::forOrganization($user->organization_id)
                ->with(['company:id,name', 'agency:id,name'])
                ->find((int) $request->input('proposal'));

            if ($proposal && $user->can('update', $proposal)) {
                $selected = [
                    'id' => $proposal->id,
                    'proposal_number' => $proposal->proposal_number,
                    'project_name' => $proposal->project_name,
                    'client' => $proposal->company?->name ?? $proposal->agency?->name,
                    'status' => $proposal->status?->label(),
                    'due_date' => $proposal->due_date?->toDateString(),
                ];
                $savedSections = $proposal->sections()->get()->map(fn ($s) => [
                    'id' => $s->id,
                    'section_key' => $s->section_key,
                    'heading' => $s->heading,
                    'content' => $s->content,
                ])->values();
            }
        }

        return Inertia::render('AI/Writer', [
            'canWrite' => $canWrite,
            'canCreate' => $user->can('create proposals'),
            'autodraft' => $request->boolean('autodraft'),
            'proposals' => $proposals,
            'selected' => $selected,
            'savedSections' => $savedSections,
            'sections' => ProposalWriterService::options(),
            'canEditStyle' => $user->hasRole('Super Admin'),
            'aiProvider' => $this->ai->getName(),
            'aiAvailable' => $this->ai->isAvailable(),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $this->authorize('use ai assistant');

        $validated = $request->validate([
            'analysis_type' => 'required|string',
            'subject_type' => 'required|in:opportunity,proposal',
            'subject_id' => 'required|integer',
            'additional_context' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();

        // Load and authorize the subject
        $subject = match($validated['subject_type']) {
            'opportunity' => Opportunity::where('organization_id', $user->organization_id)->findOrFail($validated['subject_id']),
            'proposal' => ProposalSubmission::where('organization_id', $user->organization_id)->findOrFail($validated['subject_id']),
        };

        $analysis = AiAnalysis::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'analysis_type' => $validated['analysis_type'],
            'ai_provider' => $this->ai->getName(),
            'status' => 'processing',
            'context_data' => ['additional_context' => $validated['additional_context'] ?? null],
        ]);

        try {
            $context = $subject->toArray();
            $context['additional_context'] = $validated['additional_context'] ?? null;

            $output = match($validated['analysis_type']) {
                'proposal_summary' => ['text' => $this->ai->generateProposalSummary($context)],
                'go_no_go' => $this->ai->generateGoNoGoRecommendation($context),
                'win_probability' => ['probability' => $this->ai->estimateWinProbability($context)],
                'follow_up_email' => ['email_draft' => $this->ai->generateFollowUpEmail($context)],
                default => ['text' => $this->ai->complete("You are an AI assistant.", json_encode($context))],
            };

            $analysis->update([
                'output' => $output,
                'status' => 'needs_review',
            ]);
        } catch (\Exception $e) {
            $analysis->update(['status' => 'failed']);
            return back()->with('error', 'AI analysis failed: ' . $e->getMessage());
        }

        return redirect()->route('ai.show', $analysis)->with('success', 'AI analysis completed.');
    }

    public function show(Request $request, AiAnalysis $aiAnalysis): Response
    {
        abort_unless($aiAnalysis->organization_id === $request->user()->organization_id, 403);

        $aiAnalysis->load(['createdBy:id,name', 'reviewedBy:id,name', 'subject']);

        // Build an explicit payload: the result lives in the `output` column (a
        // human-reviewed edit, when present, takes precedence), and the
        // creator/reviewer relations are exposed under stable keys so the page
        // doesn't depend on Eloquent's relation/column serialization quirks.
        return Inertia::render('AI/Show', [
            'analysis' => [
                'id' => $aiAnalysis->id,
                'analysis_type' => $aiAnalysis->analysis_type,
                'ai_provider' => $aiAnalysis->ai_provider,
                'status' => $aiAnalysis->status instanceof \BackedEnum ? $aiAnalysis->status->value : $aiAnalysis->status,
                'output' => $aiAnalysis->human_modified_output ?: $aiAnalysis->output,
                'human_decision' => $aiAnalysis->human_decision,
                'subject_type' => class_basename((string) $aiAnalysis->subject_type),
                'subject_id' => $aiAnalysis->subject_id,
                'subject_label' => $this->analysisSubjectLabel($aiAnalysis),
                'subject_url' => $this->analysisSubjectUrl($aiAnalysis),
                'created_by_user' => $aiAnalysis->createdBy ? ['id' => $aiAnalysis->createdBy->id, 'name' => $aiAnalysis->createdBy->name] : null,
                'reviewed_by_user' => $aiAnalysis->reviewedBy ? ['id' => $aiAnalysis->reviewedBy->id, 'name' => $aiAnalysis->reviewedBy->name] : null,
                'created_at' => $aiAnalysis->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function review(Request $request, AiAnalysis $aiAnalysis): RedirectResponse
    {
        $this->authorize('review ai extraction');
        abort_unless($aiAnalysis->organization_id === $request->user()->organization_id, 403);

        $validated = $request->validate([
            'human_decision' => 'required|in:accepted,rejected,modified',
            'human_modified_output' => 'nullable|array',
        ]);

        $aiAnalysis->update([
            ...$validated,
            'status' => 'completed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // On acceptance, persist the AI's estimate onto the record itself so it
        // feeds the dashboards/forecasts — not just the analysis log.
        $this->applyAcceptedAnalysis($aiAnalysis, $request->user());

        return redirect()->route('ai.index')->with('success', 'Analysis reviewed.');
    }

    /**
     * Write an accepted win-probability / go-no-go analysis back onto its subject
     * (proposal or opportunity). The human-modified output, when present, wins.
     */
    private function applyAcceptedAnalysis(AiAnalysis $analysis, \App\Models\User $reviewer): void
    {
        if (! in_array($analysis->human_decision, ['accepted', 'modified'], true)) {
            return;
        }

        $output = $analysis->human_modified_output ?: $analysis->output;
        if (! is_array($output)) {
            return;
        }

        $analysis->loadMissing('subject');
        $subject = $analysis->subject;
        if (! $subject) {
            return;
        }

        $toPercent = static fn ($v) => is_numeric($v) ? (int) round(max(0.0, min(1.0, (float) $v)) * 100) : null;

        if ($analysis->analysis_type === 'win_probability') {
            $pct = $toPercent($output['probability'] ?? null);
            if ($pct === null) {
                return;
            }
            if ($subject instanceof ProposalSubmission) {
                $subject->forceFill(['win_probability' => $pct])->save();
            } elseif ($subject instanceof Opportunity) {
                $subject->forceFill(['probability_of_win' => $pct])->save();
            }

            return;
        }

        if ($analysis->analysis_type === 'go_no_go' && $subject instanceof Opportunity) {
            $fill = ['go_no_go_decided_by' => $reviewer->id, 'go_no_go_decided_at' => now()];
            if (is_string($output['recommendation'] ?? null)) {
                $fill['go_no_go_decision'] = $output['recommendation'];
            }
            if (is_string($output['rationale'] ?? null)) {
                $fill['go_no_go_notes'] = $output['rationale'];
            }
            if (($pct = $toPercent($output['win_probability'] ?? null)) !== null) {
                $fill['probability_of_win'] = $pct;
            }
            $subject->forceFill($fill)->save();
        }
    }
}
