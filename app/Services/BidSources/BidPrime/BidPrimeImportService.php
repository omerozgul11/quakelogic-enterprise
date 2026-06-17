<?php

namespace App\Services\BidSources\BidPrime;

use App\Models\BidprimeImport;
use App\Models\BidprimeImportItem;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\OpportunityDeduplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BidPrimeImportService
{
    public function __construct(
        private readonly BidPrimeConnector $connector,
        private readonly OpportunityDeduplicationService $deduplication
    ) {}

    public function import(Organization $organization, array $filters = []): BidprimeImport
    {
        $import = BidprimeImport::create([
            'organization_id' => $organization->id,
            'status' => 'running',
            'filters' => $filters,
            'total_fetched' => 0,
            'total_created' => 0,
            'total_updated' => 0,
            'total_skipped' => 0,
            'total_errors' => 0,
            'started_at' => now(),
        ]);

        // Imported opportunities need a creator (created_by is NOT NULL). Use a
        // system user from the org, mirroring the SAM.gov importer.
        $createdBy = User::where('organization_id', $organization->id)->where('is_active', true)->value('id')
            ?? User::where('organization_id', $organization->id)->value('id');

        try {
            $results = $this->connector->fetchOpportunities($filters);
            $import->update(['total_fetched' => count($results)]);

            foreach ($results as $dto) {
                $this->processResult($import, $organization, $dto, $createdBy);
            }

            $import->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('BidPrime import failed', ['error' => $e->getMessage()]);
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
        }

        return $import;
    }

    private function processResult(BidprimeImport $import, Organization $organization, $dto, ?int $createdBy): void
    {
        try {
            DB::transaction(function () use ($import, $organization, $dto, $createdBy) {
                $hash = $this->deduplication->computeHash($dto);
                $duplicate = $this->deduplication->findDuplicate($organization->id, $dto);

                if ($duplicate) {
                    $import->increment('total_skipped');
                    BidprimeImportItem::create([
                        'bidprime_import_id' => $import->id,
                        'external_id' => $dto->externalId,
                        'title' => $dto->title,
                        'action' => 'skipped_duplicate',
                        'opportunity_id' => $duplicate->id,
                    ]);
                    return;
                }

                $existing = Opportunity::where('organization_id', $organization->id)
                    ->where('source', 'bidprime')
                    ->where('external_id', $dto->externalId)
                    ->first();

                if ($existing) {
                    // Refresh volatile fields only; never null-out existing data.
                    $existing->update(array_filter([
                        'title' => $dto->title,
                        'due_date' => $dto->dueDate,
                        'estimated_value' => $dto->estimatedValue,
                        'description' => $dto->description,
                        'raw_source_data' => $dto->rawData,
                    ], fn ($v) => $v !== null && $v !== []));
                    $import->increment('total_updated');
                    $action = 'updated';
                    $opportunityId = $existing->id;
                } else {
                    // $dto->toArray() already maps to the real columns
                    // (place_of_performance_city/state/country, set_aside_type, due_date…).
                    $opportunity = Opportunity::create([
                        ...$dto->toArray(),
                        'organization_id' => $organization->id,
                        'created_by' => $createdBy,
                        'canonical_hash' => $hash,
                        'raw_source_data' => $dto->rawData,
                        'status' => 'new',
                    ]);
                    $import->increment('total_created');
                    $action = 'created';
                    $opportunityId = $opportunity->id;
                }

                BidprimeImportItem::create([
                    'bidprime_import_id' => $import->id,
                    'external_id' => $dto->externalId,
                    'title' => $dto->title,
                    'action' => $action,
                    'opportunity_id' => $opportunityId,
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('BidPrime import item failed', ['external_id' => $dto->externalId, 'error' => $e->getMessage()]);
            $import->increment('total_errors');
        }
    }
}
