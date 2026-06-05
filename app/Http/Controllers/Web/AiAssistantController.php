<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiProviderInterface $ai) {}

    public function index(Request $request): Response
    {
        $this->authorize('use ai assistant');

        $user = $request->user();

        $recentAnalyses = AiAnalysis::where('organization_id', $user->organization_id)
            ->with('createdBy:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('AI/Index', [
            'recentAnalyses' => $recentAnalyses,
            'aiProvider' => $this->ai->getName(),
            'aiAvailable' => $this->ai->isAvailable(),
            'analysisTypes' => [
                ['value' => 'proposal_summary', 'label' => 'Proposal Summary'],
                ['value' => 'go_no_go', 'label' => 'Go/No-Go Recommendation'],
                ['value' => 'win_probability', 'label' => 'Win Probability'],
                ['value' => 'compliance_matrix', 'label' => 'Compliance Matrix'],
                ['value' => 'follow_up_email', 'label' => 'Follow-up Email'],
                ['value' => 'executive_summary', 'label' => 'Executive Summary'],
            ],
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
            'context_data' => ['additional_context' => $validated['additional_context']],
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

        return Inertia::render('AI/Show', ['analysis' => $aiAnalysis]);
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

        return redirect()->route('ai.index')->with('success', 'Analysis reviewed.');
    }
}
