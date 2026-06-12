<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProposalSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $bdm = User::where('email', 'bdm@quakelogic.net')->first();
        $pm = User::where('email', 'pm@quakelogic.net')->first();
        $sales = User::where('email', 'sales@quakelogic.net')->first();
        $writer = User::where('email', 'writer@quakelogic.net')->first();

        $dod = Agency::where('organization_id', $org->id)->where('acronym', 'DoD')->first();
        $dhs = Agency::where('organization_id', $org->id)->where('acronym', 'DHS')->first();
        $gsa = Agency::where('organization_id', $org->id)->where('acronym', 'GSA')->first();
        $darpa = Agency::where('organization_id', $org->id)->where('acronym', 'DARPA')->first();
        $va = Agency::where('organization_id', $org->id)->where('acronym', 'VA')->first();

        $proposals = [
            [
                'proposal_number' => 'QL-2024-0001',
                'project_name' => 'Enterprise IT Modernization Program - Phase II Proposal',
                'solicitation_number' => 'DoD-EITM-2024-0001',
                'status' => 'in_progress',
                'agency_id' => $dod?->id,
                'owner_id' => $bdm?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 14500000.00,
                'due_date' => now()->addDays(45),
                'description' => 'Comprehensive IT modernization proposal for DoD enterprise systems.',
                'scope_summary' => 'Cloud migration, application modernization, and cybersecurity enhancement for DoD enterprise.',
            ],
            [
                'proposal_number' => 'QL-2024-0002',
                'project_name' => 'DHS Cybersecurity Assessment Services Response',
                'solicitation_number' => 'DHS-CAS-2024-0042',
                'status' => 'in_progress',
                'agency_id' => $dhs?->id,
                'owner_id' => $sales?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 8200000.00,
                'due_date' => now()->addDays(25),
                'description' => 'Cybersecurity assessment services proposal for DHS components.',
            ],
            [
                'proposal_number' => 'QL-2024-0003',
                'project_name' => 'GSA OASIS Cloud Migration Support',
                'solicitation_number' => 'GSA-OASIS-2024-0118',
                'status' => 'submitted',
                'agency_id' => $gsa?->id,
                'owner_id' => $sales?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 21500000.00,
                'due_date' => now()->subDays(5),
                'submission_date' => now()->subDays(6),
                'description' => 'Cloud migration support proposal for GSA OASIS vehicle.',
            ],
            [
                'proposal_number' => 'QL-2024-0004',
                'project_name' => 'DARPA AI/ML Research Platform',
                'solicitation_number' => 'DARPA-AI-2024-0007',
                'status' => 'awarded',
                'agency_id' => $darpa?->id,
                'owner_id' => $bdm?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 4800000.00,
                'award_value' => 5000000.00,
                'due_date' => now()->subMonths(4),
                'submission_date' => now()->subMonths(4)->subDays(2),
                'award_date' => now()->subMonths(2),
                'description' => 'DARPA AI/ML research platform development contract - WON.',
            ],
            [
                'proposal_number' => 'QL-2023-0018',
                'project_name' => 'VA Health Records Modernization Bid',
                'solicitation_number' => 'VA-VHRM-2024-0055',
                'status' => 'lost',
                'agency_id' => $va?->id,
                'owner_id' => $sales?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 11800000.00,
                'due_date' => now()->subMonths(3),
                'submission_date' => now()->subMonths(3)->subDays(3),
                'description' => 'VA health records modernization proposal - lost to incumbent.',
                'loss_reason' => 'Incumbent advantage and lower pricing from existing contract vehicle.',
                'lessons_learned' => 'Need to pursue existing VA contract vehicles and develop relationships earlier in acquisition lifecycle.',
            ],
            [
                'proposal_number' => 'QL-2024-0005',
                'project_name' => 'DHS Network Security Operations Center',
                'solicitation_number' => 'DHS-NSOC-2024-0099',
                'status' => 'in_progress',
                'agency_id' => $dhs?->id,
                'owner_id' => $bdm?->id,
                'proposal_manager_id' => $pm?->id,
                'proposal_value' => 7500000.00,
                'due_date' => now()->addDays(60),
                'description' => 'Network Security Operations Center proposal - early draft phase.',
            ],
        ];

        foreach ($proposals as $data) {
            $existing = ProposalSubmission::where('organization_id', $org->id)->where('proposal_number', $data['proposal_number'])->first();
            if ($existing) continue;

            $proposal = ProposalSubmission::create([
                ...$data,
                'organization_id' => $org->id,
                'created_by' => $bdm?->id ?? User::where('organization_id', $org->id)->first()?->id,
                'updated_by' => $pm?->id,
                'currency' => 'USD',
            ]);

            // Add status history
            $proposal->statusHistory()->create([
                'changed_by' => $bdm?->id ?? $org->users()->first()->id,
                'from_status' => null,
                'to_status' => 'in_progress',
                'changed_at' => $proposal->created_at,
            ]);

            if ($data['status'] !== 'in_progress') {
                $proposal->statusHistory()->create([
                    'changed_by' => $pm?->id ?? $bdm?->id,
                    'from_status' => 'in_progress',
                    'to_status' => $data['status'],
                    'changed_at' => now()->subDays(rand(1, 10)),
                ]);
            }

            // Add team members
            if ($pm) $proposal->teamMembers()->create(['user_id' => $pm->id, 'role' => 'manager', 'assigned_by' => $bdm?->id ?? $pm->id]);
            if ($writer) $proposal->teamMembers()->create(['user_id' => $writer->id, 'role' => 'writer', 'assigned_by' => $pm?->id ?? $writer->id]);
        }
    }
}
