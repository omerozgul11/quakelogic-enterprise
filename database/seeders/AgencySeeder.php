<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $user = User::where('organization_id', $org->id)->first();

        $agencies = [
            ['name' => 'Department of Defense', 'acronym' => 'DoD', 'agency_type' => 'Federal', 'federal_code' => '097', 'website' => 'https://www.defense.gov', 'email' => 'contracting@defense.gov', 'city' => 'Arlington', 'state' => 'VA'],
            ['name' => 'Defense Advanced Research Projects Agency', 'acronym' => 'DARPA', 'agency_type' => 'Federal', 'federal_code' => '097-DA', 'website' => 'https://www.darpa.mil', 'city' => 'Arlington', 'state' => 'VA'],
            ['name' => 'Department of Homeland Security', 'acronym' => 'DHS', 'agency_type' => 'Federal', 'federal_code' => '070', 'website' => 'https://www.dhs.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'Cybersecurity and Infrastructure Security Agency', 'acronym' => 'CISA', 'agency_type' => 'Federal', 'website' => 'https://www.cisa.gov', 'city' => 'Arlington', 'state' => 'VA'],
            ['name' => 'General Services Administration', 'acronym' => 'GSA', 'agency_type' => 'Federal', 'federal_code' => '047', 'website' => 'https://www.gsa.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'Department of Energy', 'acronym' => 'DOE', 'agency_type' => 'Federal', 'federal_code' => '089', 'website' => 'https://www.energy.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'Department of Veterans Affairs', 'acronym' => 'VA', 'agency_type' => 'Federal', 'federal_code' => '036', 'website' => 'https://www.va.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'Department of Transportation', 'acronym' => 'DOT', 'agency_type' => 'Federal', 'federal_code' => '069', 'website' => 'https://www.transportation.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'National Aeronautics and Space Administration', 'acronym' => 'NASA', 'agency_type' => 'Federal', 'federal_code' => '080', 'website' => 'https://www.nasa.gov', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'California Department of Technology', 'acronym' => 'CDT', 'agency_type' => 'State', 'website' => 'https://cdt.ca.gov', 'city' => 'Sacramento', 'state' => 'CA'],
            ['name' => 'Texas Department of Information Resources', 'acronym' => 'DIR', 'agency_type' => 'State', 'website' => 'https://dir.texas.gov', 'city' => 'Austin', 'state' => 'TX'],
            ['name' => 'New York State Office of Information Technology Services', 'acronym' => 'ITS', 'agency_type' => 'State', 'city' => 'Albany', 'state' => 'NY'],
        ];

        foreach ($agencies as $data) {
            Agency::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $data['name']],
                [...$data, 'organization_id' => $org->id, 'created_by' => $user->id]
            );
        }
    }
}
