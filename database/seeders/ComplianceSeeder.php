<?php

namespace Database\Seeders;

use App\Models\ComplianceItem;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Phase 7 — seeds QuakeLogic's fixed federal identifiers (SAM UEI, CAGE, EIN)
 * onto the organization and into the compliance register, plus the recurring
 * state registrations (CA Small Business, SAM renewal, CDTFA quarterly). All
 * idempotent. Identifiers are real and fixed per the company.
 */
class ComplianceSeeder extends Seeder
{
    private const UEI = 'MB76MQ25YNP5';
    private const CAGE = '8EQM1';
    private const EIN = '84-2998411';

    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->first() ?? Organization::first();
        if (!$org) {
            return;
        }

        // Store the fixed identifiers on the organization record.
        $org->forceFill([
            'uei' => self::UEI,
            'cage_code' => self::CAGE,
            'ein' => self::EIN,
        ])->save();

        $author = User::where('organization_id', $org->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->value('id');

        $items = [
            ['type' => 'sam_registration', 'name' => 'SAM Registration', 'identifier' => self::UEI, 'renewal_interval' => 'annual', 'notes' => 'Federal System for Award Management. UEI ' . self::UEI . ', CAGE ' . self::CAGE . '. Renew annually.'],
            ['type' => 'cage_code', 'name' => 'CAGE Code', 'identifier' => self::CAGE, 'renewal_interval' => null, 'notes' => 'Commercial and Government Entity code.'],
            ['type' => 'uei', 'name' => 'Unique Entity ID (UEI)', 'identifier' => self::UEI, 'renewal_interval' => null, 'notes' => 'SAM.gov Unique Entity Identifier.'],
            ['type' => 'ein', 'name' => 'Employer Identification Number (EIN)', 'identifier' => self::EIN, 'renewal_interval' => null, 'notes' => 'IRS federal tax ID.'],
            ['type' => 'california_small_business', 'name' => 'California Small Business Registration', 'identifier' => null, 'renewal_interval' => 'annual', 'notes' => 'CA DGS Small Business certification — renewal required.'],
            ['type' => 'cdtfa', 'name' => 'CDTFA Filing', 'identifier' => null, 'renewal_interval' => 'quarterly', 'notes' => 'California Department of Tax and Fee Administration — file every three months.'],
        ];

        foreach ($items as $item) {
            ComplianceItem::firstOrCreate(
                ['organization_id' => $org->id, 'type' => $item['type'], 'name' => $item['name']],
                array_merge($item, ['organization_id' => $org->id, 'created_by' => $author, 'status' => 'active']),
            );
        }
    }
}
