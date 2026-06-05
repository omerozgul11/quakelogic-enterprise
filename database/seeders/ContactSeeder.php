<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $user = User::where('organization_id', $org->id)->first();
        $agencies = Agency::where('organization_id', $org->id)->get()->keyBy('acronym');

        $contacts = [
            ['first_name' => 'Robert', 'last_name' => 'Martinez', 'title' => 'Contracting Officer', 'email' => 'r.martinez@defense.gov', 'phone' => '(703) 555-0201', 'agency_key' => 'DoD', 'is_decision_maker' => true],
            ['first_name' => 'Patricia', 'last_name' => 'Williams', 'title' => 'Program Manager', 'email' => 'p.williams@darpa.mil', 'phone' => '(703) 555-0202', 'agency_key' => 'DARPA', 'is_decision_maker' => false],
            ['first_name' => 'Michael', 'last_name' => 'Brown', 'title' => 'Contracting Officer Representative', 'email' => 'm.brown@dhs.gov', 'phone' => '(202) 555-0301', 'agency_key' => 'DHS', 'is_decision_maker' => false],
            ['first_name' => 'Sandra', 'last_name' => 'Davis', 'title' => 'Chief Procurement Officer', 'email' => 's.davis@gsa.gov', 'phone' => '(202) 555-0401', 'agency_key' => 'GSA', 'is_decision_maker' => true, 'is_key_contact' => true],
            ['first_name' => 'Christopher', 'last_name' => 'Taylor', 'title' => 'IT Project Manager', 'email' => 'c.taylor@energy.gov', 'phone' => '(202) 555-0501', 'agency_key' => 'DOE', 'is_decision_maker' => false],
            ['first_name' => 'Angela', 'last_name' => 'Anderson', 'title' => 'Contracting Officer', 'email' => 'a.anderson@va.gov', 'phone' => '(202) 555-0601', 'agency_key' => 'VA', 'is_decision_maker' => true],
            ['first_name' => 'Brian', 'last_name' => 'Jackson', 'title' => 'Technical Evaluation Chair', 'email' => 'b.jackson@nasa.gov', 'phone' => '(202) 555-0701', 'agency_key' => 'NASA', 'is_decision_maker' => false],
            ['first_name' => 'Karen', 'last_name' => 'White', 'title' => 'Procurement Director', 'email' => 'k.white@cisa.gov', 'phone' => '(703) 555-0801', 'agency_key' => 'CISA', 'is_decision_maker' => true, 'is_key_contact' => true],
        ];

        foreach ($contacts as $data) {
            $agencyKey = $data['agency_key'];
            unset($data['agency_key']);

            $agency = $agencies[$agencyKey] ?? null;

            Contact::firstOrCreate(
                ['organization_id' => $org->id, 'email' => $data['email']],
                [
                    ...$data,
                    'organization_id' => $org->id,
                    'created_by' => $user->id,
                    'agency_id' => $agency?->id,
                    'is_decision_maker' => $data['is_decision_maker'] ?? false,
                    'is_key_contact' => $data['is_key_contact'] ?? false,
                ]
            );
        }
    }
}
