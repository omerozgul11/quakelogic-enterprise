<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Merges duplicate CRM records. Every reference to the duplicate is repointed to
 * the survivor, blank survivor fields are backfilled from the duplicate, and the
 * duplicate is **soft-deleted** (never hard-deleted) — all inside one
 * transaction, so a merge is atomic and fully recoverable.
 */
class MergeService
{
    /** Tables/columns that point at a company. */
    private const COMPANY_REFS = [
        ['contacts', 'company_id'],
        ['crm_leads', 'company_id'],
        ['crm_projects', 'company_id'],
        ['crm_invoices', 'company_id'],
        ['proposal_submissions', 'company_id'],
        ['opportunities', 'company_id'],
    ];

    /** Tables/columns that point at a contact. */
    private const CONTACT_REFS = [
        ['crm_leads', 'contact_id'],
        ['crm_projects', 'contact_id'],
    ];

    /** Polymorphic timeline/follow-up tables keyed by subject. */
    private const MORPH_TABLES = ['crm_activities', 'crm_follow_ups'];

    public function mergeCompanies(Company $primary, Company $duplicate): void
    {
        $this->guard($primary, $duplicate);

        DB::transaction(function () use ($primary, $duplicate) {
            foreach (self::COMPANY_REFS as [$table, $col]) {
                DB::table($table)->where($col, $duplicate->id)->update([$col => $primary->id]);
            }
            $this->repointMorph($primary, $duplicate);
            $this->fillBlanks($primary, $duplicate, [
                'industry', 'cage_code', 'uei', 'duns', 'website', 'phone', 'email',
                'address_line1', 'city', 'state', 'zip', 'country', 'notes',
            ]);
            $duplicate->delete();
        });
    }

    public function mergeContacts(Contact $primary, Contact $duplicate): void
    {
        $this->guard($primary, $duplicate);

        DB::transaction(function () use ($primary, $duplicate) {
            foreach (self::CONTACT_REFS as [$table, $col]) {
                DB::table($table)->where($col, $duplicate->id)->update([$col => $primary->id]);
            }
            $this->repointMorph($primary, $duplicate);
            $this->fillBlanks($primary, $duplicate, [
                'title', 'department', 'email', 'phone', 'mobile', 'linkedin_url', 'notes',
            ]);
            $duplicate->delete();
        });
    }

    /** Repoint polymorphic activities & follow-ups from the duplicate to the survivor. */
    private function repointMorph(Model $primary, Model $duplicate): void
    {
        foreach (self::MORPH_TABLES as $table) {
            DB::table($table)
                ->where('subject_type', $duplicate->getMorphClass())
                ->where('subject_id', $duplicate->id)
                ->update(['subject_id' => $primary->id]);
        }
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function fillBlanks(Model $primary, Model $duplicate, array $fields): void
    {
        $patch = [];
        foreach ($fields as $field) {
            if (blank($primary->getAttribute($field)) && filled($duplicate->getAttribute($field))) {
                $patch[$field] = $duplicate->getAttribute($field);
            }
        }
        if ($patch) {
            $primary->forceFill($patch)->save();
        }
    }

    private function guard(Model $primary, Model $duplicate): void
    {
        abort_if($primary->getKey() === $duplicate->getKey(), 422, 'Cannot merge a record into itself.');
        abort_unless($primary->getAttribute('organization_id') === $duplicate->getAttribute('organization_id'), 403);
    }
}
