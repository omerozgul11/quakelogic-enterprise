<?php

namespace App\Services\BidSources\BidPrime;

use App\Models\BidprimeImport;
use App\Models\BidprimeImportItem;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Services\BidSources\OpportunityDeduplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        try {
            $results = $this->connector->fetchOpportunities($filters);
            $import->update(['total_fetched' => count($results)]);

            foreach ($results as $dto) {
                $this->processResult($import, $organization, $dto);
            }

            $import->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('BidPrime import failed', ['error' => $e->getMessage()]);
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
        }

        return $import;
    }

    private function processResult(BidprimeImport $import, Organization $organization, $dto): void
    {
        try {
            DB::transaction(function () use ($import, $organization, $dto) {
                $hash = $this->deduplication->computeHash($dto);
                $duplicate = $this->deduplication->findDuplicate($organization->id, $hash, $dto->externalId, $dto->solicitationNumber);

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
                    $existing->update([
                        'title' => $dto->title,
                        'due_date' => $dto->responseDeadline,
                        'raw_source_data' => $dto->rawData,
                    ]);
                    $import->increment('total_updated');
                    $action = 'updated';
                    $opportunityId = $existing->id;
                } else {
                    $opportunity = Opportunity::create([
                        'ulid' => (string) Str::ulid(),
                        'organization_id' => $organization->id,
                        'source' => 'bidprime',
                        'external_id' => $dto->externalId,
                        'canonical_hash' => $hash,
                        'solicitation_number' => $dto->solicitationNumber,
                        'title' => $dto->title,
                        'description' => $dto->description,
                        'agency_name' => $dto->agencyName,
                        'naics_code' => $dto->naicsCode,
                        'set_aside_type' => $dto->setAside,
                        'place_of_performance' => $dto->placeOfPerformance,
                        'due_date' => $dto->responseDeadline,
                        'posted_date' => $dto->postedDate,
                        'source_url' => $dto->sourceUrl,
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
