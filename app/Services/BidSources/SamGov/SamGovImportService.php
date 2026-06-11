<?php

namespace App\Services\BidSources\SamGov;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\SamImport;
use App\Models\User;
use App\Services\BidSources\OpportunityDeduplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SamGovImportService
{
    public function __construct(
        private readonly SamGovConnector $connector,
        private readonly OpportunityDeduplicationService $deduplication
    ) {}

    public function import(Organization $organization, array $filters = [], ?User $triggeredBy = null): array
    {
        $import = SamImport::create([
            'organization_id' => $organization->id,
            'triggered_by' => $triggeredBy?->id,
            'status' => 'running',
            'query_params' => $filters,
            'started_at' => now(),
        ]);

        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $offset = 0;
            $limit = 50;
            // Safety cap: a broad SAM.gov query can return tens of thousands of
            // records. Bound a single sync to a sensible number of pages; users
            // narrow results with NAICS/keyword filters.
            $maxPages = (int) ($filters['max_pages'] ?? 4);
            $page = 0;

            do {
                $results = $this->connector->fetchOpportunities($filters, $limit, $offset);
                $this->processResults($import, $organization, $results, $stats);
                $offset += $limit;
                $page++;
            } while (count($results) === $limit && $page < $maxPages);

            $import->update([
                'status' => 'completed',
                'imported_records' => $stats['imported'],
                'updated_records' => $stats['updated'],
                'skipped_records' => $stats['skipped'],
                'error_records' => $stats['errors'],
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $import->update(['status' => 'failed', 'error_log' => $e->getMessage(), 'completed_at' => now()]);
            Log::error('SAM.gov import failed', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * Import a batch of already-fetched results (e.g. from the full-text
     * search) through the same dedup/upsert path as a regular import.
     */
    public function importResults(Organization $organization, array $results, ?User $triggeredBy = null, array $queryParams = []): array
    {
        $import = SamImport::create([
            'organization_id' => $organization->id,
            'triggered_by' => $triggeredBy?->id,
            'status' => 'running',
            'query_params' => $queryParams,
            'started_at' => now(),
        ]);

        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $this->processResults($import, $organization, $results, $stats);

        $import->update([
            'status' => 'completed',
            'imported_records' => $stats['imported'],
            'updated_records' => $stats['updated'],
            'skipped_records' => $stats['skipped'],
            'error_records' => $stats['errors'],
            'completed_at' => now(),
        ]);

        return $stats;
    }

    private function processResults(SamImport $import, Organization $organization, array $results, array &$stats): void
    {
        foreach ($results as $result) {
            try {
                DB::beginTransaction();

                $existing = Opportunity::where('organization_id', $organization->id)
                    ->where('source', 'sam_gov')
                    ->where('external_id', $result->externalId)
                    ->first();

                if ($existing) {
                    $this->updateOpportunity($existing, $result);
                    $stats['updated']++;
                    $action = 'updated';
                } else {
                    $opportunity = $this->createOpportunity($organization, $result);
                    $stats['imported']++;
                    $action = 'created';
                }

                $import->items()->create([
                    'external_id' => $result->externalId,
                    'opportunity_id' => $existing?->id ?? $opportunity?->id,
                    'action' => $action,
                    'raw_data' => $result->rawData,
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $stats['errors']++;
                Log::warning('SAM.gov import item error', ['external_id' => $result->externalId, 'error' => $e->getMessage()]);

                $import->items()->create([
                    'external_id' => $result->externalId,
                    'action' => 'error',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createOpportunity(Organization $organization, $result): Opportunity
    {
        $systemUser = User::where('organization_id', $organization->id)->first();

        return Opportunity::create([
            'organization_id' => $organization->id,
            'created_by' => $systemUser->id,
            'title' => $result->title,
            'solicitation_number' => $result->solicitationNumber,
            'source' => 'sam_gov',
            'external_id' => $result->externalId,
            'source_url' => $result->sourceUrl,
            'status' => 'new',
            'agency_name' => $result->agencyName,
            'sub_agency_name' => $result->subAgencyName,
            'naics_code' => $result->naicsCode,
            'psc_code' => $result->pscCode,
            'set_aside_type' => $result->setAsideType,
            'contract_type' => $result->contractType,
            'estimated_value' => $result->estimatedValue,
            'description' => $result->description,
            'posted_date' => $result->postedDate,
            'due_date' => $result->dueDate,
            'place_of_performance_city' => $result->placeOfPerformanceCity,
            'place_of_performance_state' => $result->placeOfPerformanceState,
            'place_of_performance_country' => $result->placeOfPerformanceCountry,
            'raw_source_data' => $result->rawData,
            'canonical_hash' => $this->deduplication->computeHash($result),
        ]);
    }

    private function updateOpportunity(Opportunity $opportunity, $result): void
    {
        // Sparse sources (e.g. full-text search) may carry nulls for fields a
        // previous import already filled — never overwrite data with null.
        $opportunity->update(array_filter([
            'due_date' => $result->dueDate,
            'estimated_value' => $result->estimatedValue,
            'description' => $result->description,
            'raw_source_data' => $result->rawData,
        ], fn ($v) => $v !== null));
    }
}
