<?php

namespace Database\Seeders;

use App\Models\OpportunityKeywordGroup;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds QuakeLogic's default opportunity keyword groups (editable afterwards in
 * the admin UI) for every organization. Idempotent: a group is created once per
 * org by name, so re-running won't duplicate or clobber edited groups.
 */
class OpportunityKeywordGroupSeeder extends Seeder
{
    /** @return array<int,array{name:string,keywords:array<int,string>,naics:array<int,string>,weight:int,exclusion?:bool,color?:string}> */
    public static function defaults(): array
    {
        return [
            [
                'name' => 'Seismic & Earthquake Monitoring',
                'keywords' => ['seismic', 'seismic monitoring', 'seismometer', 'earthquake', 'early warning', 'structural health monitoring', 'monitoring system', 'instrumentation'],
                'naics' => ['334513', '541380', '541330', '334511'],
                'weight' => 12,
                'color' => 'red',
            ],
            [
                'name' => 'Vibration, Sensors & Data Acquisition',
                'keywords' => ['vibration monitoring', 'accelerometer', 'data acquisition', 'daq', 'digitizer', 'geotechnical sensors', 'acoustic emission', 'infrasound', 'hydrophone'],
                'naics' => ['334513', '334515'],
                'weight' => 11,
                'color' => 'indigo',
            ],
            [
                'name' => 'Test Equipment & Simulators',
                'keywords' => ['shake table', 'load frame', 'actuator', 'servo hydraulic', 'welding simulator', 'heavy equipment simulator', 'cdl simulator', 'fiber laser', 'cnc', 'laboratory equipment'],
                'naics' => ['333999', '333515', '334516'],
                'weight' => 10,
                'color' => 'amber',
            ],
            [
                'name' => 'Exclusions',
                'keywords' => [],
                'naics' => [],
                'weight' => 10,
                'exclusion' => true,
                'color' => 'gray',
            ],
        ];
    }

    public function run(): void
    {
        $defaults = self::defaults();

        Organization::query()->each(function (Organization $org) use ($defaults) {
            foreach ($defaults as $i => $group) {
                OpportunityKeywordGroup::firstOrCreate(
                    ['organization_id' => $org->id, 'name' => $group['name']],
                    [
                        'keywords' => $group['keywords'],
                        'naics_codes' => $group['naics'],
                        'weight' => $group['weight'],
                        'is_exclusion' => $group['exclusion'] ?? false,
                        'is_active' => true,
                        'color' => $group['color'] ?? null,
                        'sort_order' => $i,
                    ],
                );
            }
        });
    }
}
