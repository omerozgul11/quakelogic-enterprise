<?php

namespace Database\Seeders;

use App\Models\FollowUp;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class FollowUpSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $bdm = User::where('email', 'bdm@quakelogic.net')->first();
        $sales = User::where('email', 'sales@quakelogic.net')->first();

        $submittedProposals = ProposalSubmission::where('organization_id', $org->id)
            ->whereIn('status', ['submitted', 'pending', 'awarded', 'lost'])
            ->get();

        foreach ($submittedProposals as $proposal) {
            $daysSince = $proposal->submission_date
                ? now()->diffInDays($proposal->submission_date, false)
                : 0;

            // 7-day follow-up
            FollowUp::firstOrCreate(
                ['organization_id' => $org->id, 'proposal_submission_id' => $proposal->id, 'subject' => '7-Day Follow-Up: ' . $proposal->project_name],
                [
                    'organization_id' => $org->id,
                    'created_by' => $bdm?->id,
                    'assigned_to' => $proposal->owner_id,
                    'proposal_submission_id' => $proposal->id,
                    'type' => 'email',
                    'subject' => '7-Day Follow-Up: ' . $proposal->project_name,
                    'message' => "Following up on our proposal submitted on {$proposal->submission_date}. Please let us know if you need any additional information.",
                    'status' => abs($daysSince) > 7 ? 'sent' : 'scheduled',
                    'scheduled_date' => $proposal->submission_date?->addDays(7),
                    'sent_at' => abs($daysSince) > 7 ? $proposal->submission_date?->addDays(7) : null,
                    'is_automated' => true,
                ]
            );

            // 30-day follow-up
            if (abs($daysSince) > 20) {
                FollowUp::firstOrCreate(
                    ['organization_id' => $org->id, 'proposal_submission_id' => $proposal->id, 'subject' => '30-Day Follow-Up: ' . $proposal->project_name],
                    [
                        'organization_id' => $org->id,
                        'created_by' => $bdm?->id,
                        'assigned_to' => $proposal->owner_id,
                        'proposal_submission_id' => $proposal->id,
                        'type' => 'email',
                        'subject' => '30-Day Follow-Up: ' . $proposal->project_name,
                        'status' => abs($daysSince) > 30 ? (in_array($proposal->status->value, ['awarded', 'lost']) ? 'responded' : 'sent') : 'scheduled',
                        'scheduled_date' => $proposal->submission_date?->addDays(30),
                        'is_automated' => true,
                    ]
                );
            }
        }

        // Add some overdue follow-ups for dashboard interest
        FollowUp::create([
            'organization_id' => $org->id,
            'created_by' => $bdm?->id,
            'assigned_to' => $sales?->id,
            'type' => 'call',
            'subject' => 'Quarterly business review call with DoD contacts',
            'status' => 'overdue',
            'scheduled_date' => now()->subDays(5),
        ]);

        FollowUp::create([
            'organization_id' => $org->id,
            'created_by' => $bdm?->id,
            'assigned_to' => $bdm?->id,
            'type' => 'meeting',
            'subject' => 'Agency relationship meeting - GSA',
            'status' => 'scheduled',
            'scheduled_date' => now()->addDays(7),
        ]);
    }
}
