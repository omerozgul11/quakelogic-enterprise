<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $users = User::where('organization_id', $org->id)->whereNotNull('email')->get();
        $agencies = Agency::where('organization_id', $org->id)->get();

        $bdmUser = User::where('email', 'bdm@quakelogic.net')->first() ?? $users->first();
        $salesUser = User::where('email', 'sales@quakelogic.net')->first() ?? $users->first();
        $captureUser = User::where('email', 'capture@quakelogic.net')->first() ?? $users->first();

        $dod = $agencies->where('acronym', 'DoD')->first();
        $darpa = $agencies->where('acronym', 'DARPA')->first();
        $dhs = $agencies->where('acronym', 'DHS')->first();
        $gsa = $agencies->where('acronym', 'GSA')->first();
        $doe = $agencies->where('acronym', 'DOE')->first();
        $va = $agencies->where('acronym', 'VA')->first();
        $nasa = $agencies->where('acronym', 'NASA')->first();

        $opportunities = [
            [
                'title' => 'Enterprise IT Modernization Program - Phase II',
                'solicitation_number' => 'DoD-EITM-2024-0001',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-DOD-0001',
                'status' => 'pursuing',
                'agency_name' => 'Department of Defense',
                'agency_id' => $dod?->id,
                'naics_code' => '541511',
                'psc_code' => 'D301',
                'set_aside_type' => 'Full and Open',
                'contract_type' => 'Solicitation',
                'estimated_value' => 15000000.00,
                'probability_of_win' => 55.0,
                'posted_date' => now()->subDays(30),
                'due_date' => now()->addDays(45),
                'description' => 'The Department of Defense seeks qualified vendors to provide enterprise IT modernization services including cloud migration, application development, and cybersecurity enhancements.',
                'assigned_to' => $bdmUser?->id,
                'owner_id' => $bdmUser?->id,
                'go_no_go_decision' => 'go',
            ],
            [
                'title' => 'Cybersecurity Assessment Services IDIQ',
                'solicitation_number' => 'DHS-CAS-2024-0042',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-DHS-0042',
                'status' => 'proposal_in_progress',
                'agency_name' => 'Department of Homeland Security',
                'agency_id' => $dhs?->id,
                'naics_code' => '541512',
                'set_aside_type' => 'Small Business Set-Aside',
                'estimated_value' => 8500000.00,
                'probability_of_win' => 65.0,
                'posted_date' => now()->subDays(20),
                'due_date' => now()->addDays(25),
                'description' => 'DHS seeks comprehensive cybersecurity assessment and risk management support services across its components.',
                'assigned_to' => $captureUser?->id,
                'owner_id' => $bdmUser?->id,
                'go_no_go_decision' => 'go',
            ],
            [
                'title' => 'Cloud Migration Support Services - OASIS',
                'solicitation_number' => 'GSA-OASIS-2024-0118',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-GSA-0118',
                'status' => 'submitted',
                'agency_name' => 'General Services Administration',
                'agency_id' => $gsa?->id,
                'naics_code' => '541513',
                'set_aside_type' => 'Full and Open',
                'estimated_value' => 22000000.00,
                'probability_of_win' => 40.0,
                'posted_date' => now()->subDays(60),
                'due_date' => now()->subDays(5),
                'description' => 'GSA requires cloud migration services for federal civilian agency support under OASIS contract vehicle.',
                'assigned_to' => $salesUser?->id,
                'owner_id' => $bdmUser?->id,
                'go_no_go_decision' => 'go',
            ],
            [
                'title' => 'AI/ML Research Platform Development',
                'solicitation_number' => 'DARPA-AI-2024-0007',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-DARPA-0007',
                'status' => 'awarded',
                'agency_name' => 'DARPA',
                'agency_id' => $darpa?->id,
                'naics_code' => '541511',
                'set_aside_type' => 'Full and Open',
                'estimated_value' => 5000000.00,
                'probability_of_win' => 100.0,
                'posted_date' => now()->subMonths(6),
                'due_date' => now()->subMonths(4),
                'award_date' => now()->subMonths(2),
                'description' => 'DARPA AI/ML Research platform development and operations support.',
                'assigned_to' => $bdmUser?->id,
                'owner_id' => $bdmUser?->id,
                'go_no_go_decision' => 'go',
            ],
            [
                'title' => 'Veterans Health Records Modernization',
                'solicitation_number' => 'VA-VHRM-2024-0055',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-VA-0055',
                'status' => 'lost',
                'agency_name' => 'Department of Veterans Affairs',
                'agency_id' => $va?->id,
                'naics_code' => '541519',
                'set_aside_type' => 'Service-Disabled Veteran Owned',
                'estimated_value' => 12000000.00,
                'probability_of_win' => 0.0,
                'posted_date' => now()->subMonths(5),
                'due_date' => now()->subMonths(3),
                'description' => 'VA health records modernization system integration and support.',
                'assigned_to' => $salesUser?->id,
                'owner_id' => $salesUser?->id,
                'go_no_go_decision' => 'go',
            ],
            [
                'title' => 'NASA IT Infrastructure Support Services',
                'solicitation_number' => 'NASA-ITS-2024-0033',
                'source' => 'bidprime',
                'external_id' => 'BP-2024-NASA-0033',
                'status' => 'monitoring',
                'agency_name' => 'NASA',
                'agency_id' => $nasa?->id,
                'naics_code' => '541511',
                'estimated_value' => 18000000.00,
                'posted_date' => now()->subDays(10),
                'due_date' => now()->addDays(75),
                'description' => 'NASA seeks IT infrastructure support services for all center locations.',
                'assigned_to' => $bdmUser?->id,
                'owner_id' => $bdmUser?->id,
            ],
            [
                'title' => 'DOE Supercomputing Facility Support',
                'solicitation_number' => 'DOE-SC-2024-0019',
                'source' => 'sam_gov',
                'external_id' => 'SAM-2024-DOE-0019',
                'status' => 'qualified',
                'agency_name' => 'Department of Energy',
                'agency_id' => $doe?->id,
                'naics_code' => '541511',
                'set_aside_type' => 'Full and Open',
                'estimated_value' => 35000000.00,
                'probability_of_win' => 30.0,
                'posted_date' => now()->subDays(15),
                'due_date' => now()->addDays(60),
                'description' => 'DOE Office of Science supercomputing facility IT support and maintenance.',
                'assigned_to' => $captureUser?->id,
                'owner_id' => $bdmUser?->id,
            ],
            [
                'title' => 'Network Security Operations Center',
                'solicitation_number' => null,
                'source' => 'manual',
                'status' => 'new',
                'agency_name' => 'CISA',
                'estimated_value' => 6000000.00,
                'posted_date' => now()->subDays(5),
                'due_date' => now()->addDays(90),
                'description' => 'Potential opportunity identified through agency relationship - pre-solicitation intelligence gathering.',
                'assigned_to' => $bdmUser?->id,
                'owner_id' => $bdmUser?->id,
            ],
        ];

        foreach ($opportunities as $data) {
            Opportunity::firstOrCreate(
                ['organization_id' => $org->id, 'title' => $data['title']],
                [
                    ...$data,
                    'organization_id' => $org->id,
                    'created_by' => $bdmUser?->id ?? $users->first()->id,
                ]
            );
        }
    }
}
