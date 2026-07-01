<?php

namespace App\Services\BidSources\BidPrime;

use App\Models\BidprimeImport;
use App\Models\BidprimeImportItem;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\BidSourceResultDTO;
use App\Services\BidSources\OpportunityDeduplicationService;
use App\Services\Opportunities\OpportunityScorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BidPrimeImportService
{
    public function __construct(
        private readonly BidPrimeConnector $connector,
        private readonly OpportunityDeduplicationService $deduplication,
        private readonly OpportunityScorer $scorer,
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

    /** Start a new import run record (reused by the email-ingest path). */
    public function newRun(Organization $organization, array $filters = []): BidprimeImport
    {
        return BidprimeImport::create([
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
    }

    /** Imported opportunities need a creator (created_by is NOT NULL): a system user. */
    public function resolveCreator(Organization $organization): ?int
    {
        return User::where('organization_id', $organization->id)->where('is_active', true)->value('id')
            ?? User::where('organization_id', $organization->id)->value('id');
    }

    /** Dedup + upsert a single DTO into an existing run, tagged with its source email. */
    public function ingestDto(BidprimeImport $import, Organization $organization, BidSourceResultDTO $dto, ?int $createdBy, ?int $emailId = null): string
    {
        $action = $this->processResult($import, $organization, $dto, $createdBy, $emailId);
        $import->increment('total_fetched');

        return $action;
    }

    public function finishRun(BidprimeImport $import, string $status = 'completed', ?string $error = null): void
    {
        $import->update(array_filter([
            'status' => $status,
            'error_message' => $error,
            'completed_at' => now(),
        ], fn ($v) => $v !== null));
    }

    private function processResult(BidprimeImport $import, Organization $organization, $dto, ?int $createdBy, ?int $emailId = null): string
    {
        try {
            return DB::transaction(function () use ($import, $organization, $dto, $createdBy, $emailId) {
                $hash = $this->deduplication->computeHash($dto);
                $duplicate = $this->deduplication->findDuplicate($organization->id, $dto);

                if ($duplicate) {
                    $import->increment('total_skipped');
                    BidprimeImportItem::create([
                        'bidprime_import_id' => $import->id,
                        'bidprime_email_id' => $emailId,
                        'external_id' => $dto->externalId,
                        'title' => $dto->title,
                        'action' => 'skipped_duplicate',
                        'opportunity_id' => $duplicate->id,
                    ]);

                    return 'skipped_duplicate';
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
                    $opportunity = $existing;
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
                }
                $opportunityId = $opportunity->id;

                // Relevance score + priority from the org's keyword groups.
                $this->scorer->scoreAndStore($opportunity);

                BidprimeImportItem::create([
                    'bidprime_import_id' => $import->id,
                    'bidprime_email_id' => $emailId,
                    'external_id' => $dto->externalId,
                    'title' => $dto->title,
                    'action' => $action,
                    'opportunity_id' => $opportunityId,
                ]);

                return $action;
            });
        } catch (\Throwable $e) {
            Log::warning('BidPrime import item failed', ['external_id' => $dto->externalId, 'error' => $e->getMessage()]);
            $import->increment('total_errors');

            return 'error';
        }
    }
}
