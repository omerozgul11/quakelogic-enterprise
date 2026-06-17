<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityAssignmentStage;
use App\Models\Opportunity;

/**
 * Computes an opportunity's 0–100 health score and traffic-light category from
 * deadline pressure, work progress, recent activity and customer engagement.
 * Used on the opportunity page and to surface at-risk work on the executive
 * dashboard. Deterministic and cheap.
 *
 * Weights: deadline 30 · activity 30 · progress 20 · engagement 20.
 */
class OpportunityHealthService
{
    /**
     * @return array{score:int,category:string,factors:array<string,int>}
     */
    public function score(Opportunity $opportunity): array
    {
        $deadline = $this->deadlineFactor($opportunity);
        $activity = $this->activityFactor($opportunity);
        $progress = $this->progressFactor($opportunity);
        $engagement = $this->engagementFactor($opportunity);

        $score = (int) round(100 * (0.30 * $deadline + 0.30 * $activity + 0.20 * $progress + 0.20 * $engagement));
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'category' => $score >= 70 ? 'healthy' : ($score >= 40 ? 'warning' : 'critical'),
            'factors' => [
                'deadline' => (int) round($deadline * 100),
                'activity' => (int) round($activity * 100),
                'progress' => (int) round($progress * 100),
                'engagement' => (int) round($engagement * 100),
            ],
        ];
    }

    /** More runway = healthier; overdue is the worst signal. */
    private function deadlineFactor(Opportunity $opportunity): float
    {
        $days = $opportunity->days_until_deadline;
        if ($days === null) {
            return 0.6;
        }
        if ($days < 0) {
            return 0.0;
        }

        return min(1.0, 0.2 + ($days / 30) * 0.8);
    }

    /** Recent activity = healthier; long silence is risky. */
    private function activityFactor(Opportunity $opportunity): float
    {
        $days = $opportunity->days_since_activity;
        if ($days === null) {
            return 0.5;
        }

        return match (true) {
            $days <= 2 => 1.0,
            $days <= 7 => 0.7,
            $days <= 14 => 0.4,
            $days <= 21 => 0.2,
            default => 0.1,
        };
    }

    /** Further along the assignment lifecycle = healthier. */
    private function progressFactor(Opportunity $opportunity): float
    {
        return match ($opportunity->assignment_stage) {
            OpportunityAssignmentStage::Submitted, OpportunityAssignmentStage::Won => 1.0,
            OpportunityAssignmentStage::UnderReview => 0.85,
            OpportunityAssignmentStage::ProposalDrafting => 0.7,
            OpportunityAssignmentStage::InProgress => 0.55,
            OpportunityAssignmentStage::Accepted => 0.4,
            OpportunityAssignmentStage::Assigned => 0.2,
            default => 0.0, // Unassigned / Lost / Abandoned
        };
    }

    /** A live proposal and logged client touches signal engagement. */
    private function engagementFactor(Opportunity $opportunity): float
    {
        $score = 0.2;
        if ($opportunity->proposals()->exists()) {
            $score += 0.4;
        }
        $followUps = $opportunity->followUps()->count();
        if ($followUps > 0) {
            $score += min(0.4, $followUps * 0.2);
        }

        return min(1.0, $score);
    }
}
