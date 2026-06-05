<?php

namespace Database\Seeders;

use App\Models\CapturePlan;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class CapturePlanSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $captureUser = User::where('email', 'capture@quakelogic.net')->first();
        $bdmUser = User::where('email', 'bdm@quakelogic.net')->first();

        $opportunitiesWithCapture = Opportunity::where('organization_id', $org->id)
            ->whereNotNull('capture_stage')
            ->whereNotIn('status', ['new', 'monitoring', 'no_bid'])
            ->get();

        foreach ($opportunitiesWithCapture as $opp) {
            if (CapturePlan::where('opportunity_id', $opp->id)->exists()) continue;

            $plan = CapturePlan::create([
                'organization_id' => $org->id,
                'opportunity_id' => $opp->id,
                'capture_manager_id' => $captureUser?->id,
                'created_by' => $bdmUser?->id ?? $org->users()->first()->id,
                'stage' => $opp->capture_stage?->value ?? 'discovery',
                'probability_of_win' => $opp->probability_of_win ?? 50.0,
                'estimated_value' => $opp->estimated_value,
                'estimated_margin' => 15.0,
                'strategy' => 'Position QuakeLogic as the low-risk, high-value solution with proven past performance and strong technical approach.',
                'win_themes' => "1. Technical excellence and innovation\n2. Competitive pricing with full transparency\n3. Proven past performance on similar federal contracts\n4. Strong small business subcontracting plan",
                'discriminators' => "- Proprietary AI-enhanced delivery methodology\n- Experienced team with average 10+ years federal IT experience\n- Existing relationships with key agency stakeholders",
                'is_incumbent' => false,
            ]);

            // Stage history
            $plan->stageHistory()->create([
                'changed_by' => $bdmUser?->id ?? $captureUser?->id,
                'from_stage' => null,
                'to_stage' => 'discovery',
                'changed_at' => $opp->created_at,
            ]);

            if ($opp->capture_stage && $opp->capture_stage->value !== 'discovery') {
                $plan->stageHistory()->create([
                    'changed_by' => $captureUser?->id ?? $bdmUser?->id,
                    'from_stage' => 'discovery',
                    'to_stage' => $opp->capture_stage?->value,
                    'changed_at' => now()->subDays(rand(5, 20)),
                ]);
            }

            // Add risks
            $plan->risks()->createMany([
                ['title' => 'Incumbent Advantage', 'description' => 'Current contractor has 5-year relationship with agency.', 'likelihood' => 'high', 'impact' => 'high', 'mitigation_strategy' => 'Leverage new agency contacts and superior technical approach.', 'status' => 'open', 'created_by' => $captureUser?->id ?? $bdmUser?->id],
                ['title' => 'Tight Deadline Risk', 'description' => 'Proposal due date conflicts with other major proposal.', 'likelihood' => 'medium', 'impact' => 'medium', 'mitigation_strategy' => 'Identify additional proposal writers or extend hours.', 'status' => 'open', 'created_by' => $captureUser?->id ?? $bdmUser?->id],
            ]);

            // Add tasks
            $plan->tasks()->createMany([
                ['title' => 'Conduct agency capability briefing', 'status' => 'open', 'due_date' => now()->addDays(14), 'assigned_to' => $bdmUser?->id, 'created_by' => $captureUser?->id ?? $bdmUser?->id],
                ['title' => 'Identify and qualify teaming partners', 'status' => 'in_progress', 'due_date' => now()->addDays(21), 'assigned_to' => $captureUser?->id, 'created_by' => $captureUser?->id ?? $bdmUser?->id],
                ['title' => 'Develop win strategy document', 'status' => 'open', 'due_date' => now()->addDays(30), 'assigned_to' => $captureUser?->id, 'created_by' => $captureUser?->id ?? $bdmUser?->id],
            ]);
        }
    }
}
