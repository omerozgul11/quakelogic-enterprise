<?php

namespace App\Services\Proposals;

use App\Models\ComplianceItem;
use App\Models\ProposalSubmission;

/**
 * Phase 17 — Submission Management. Scores a proposal's readiness to submit
 * (0–100%) from a rules-based checklist: required content, a client, a value,
 * a submission method, and current company compliance (insurance / SAM). Used
 * to surface what's missing before submission and to warn on early submits.
 */
class ProposalReadinessService
{
    /** Below this, the proposal is flagged as not ready to submit. */
    public const THRESHOLD = 70;

    /** @return array{score:int,ready:bool,threshold:int,items:array<int,array{key:string,label:string,done:bool}>} */
    public function evaluate(ProposalSubmission $proposal): array
    {
        $orgId = $proposal->organization_id;

        $items = [
            ['key' => 'document', 'label' => 'Proposal document attached', 'done' => $proposal->files()->exists()],
            ['key' => 'client', 'label' => 'Client / agency identified', 'done' => (bool) ($proposal->company_id || $proposal->agency_id)],
            ['key' => 'due_date', 'label' => 'Due date set', 'done' => $proposal->due_date !== null],
            ['key' => 'value', 'label' => 'Proposal value entered', 'done' => (float) $proposal->proposal_value > 0],
            ['key' => 'scope', 'label' => 'Scope or description written', 'done' => !empty($proposal->scope_summary) || !empty($proposal->description)],
            ['key' => 'method', 'label' => 'Submission method selected', 'done' => !empty($proposal->submission_methods)],
            ['key' => 'insurance', 'label' => 'Active insurance on file', 'done' => $this->hasActiveCompliance($orgId, 'insurance')],
            ['key' => 'sam', 'label' => 'Active SAM registration', 'done' => $this->hasActiveCompliance($orgId, 'sam_registration')],
        ];

        $done = count(array_filter($items, fn ($i) => $i['done']));
        $score = (int) round($done / count($items) * 100);

        return [
            'score' => $score,
            'ready' => $score >= self::THRESHOLD,
            'threshold' => self::THRESHOLD,
            'items' => array_map(fn ($i) => ['key' => $i['key'], 'label' => $i['label'], 'done' => (bool) $i['done']], $items),
        ];
    }

    /** Short message naming what's still missing, or null when ready. */
    public function missingSummary(ProposalSubmission $proposal): ?string
    {
        $result = $this->evaluate($proposal);
        if ($result['ready']) {
            return null;
        }
        $missing = array_map(fn ($i) => $i['label'], array_filter($result['items'], fn ($i) => !$i['done']));

        return "Submission readiness is {$result['score']}% (target {$result['threshold']}%). Still missing: " . implode(', ', $missing) . '.';
    }

    private function hasActiveCompliance(int $organizationId, string $type): bool
    {
        return ComplianceItem::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString()))
            ->exists();
    }
}
