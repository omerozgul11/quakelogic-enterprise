<?php

namespace App\Jobs;

use App\Models\Opportunity;
use App\Services\BidSources\OpportunityDeduplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeduplicateOpportunityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $opportunityId)
    {
        $this->onQueue('imports');
    }

    public function handle(OpportunityDeduplicationService $service): void
    {
        $opportunity = Opportunity::findOrFail($this->opportunityId);

        $dto = new \App\Services\BidSources\BidSourceResultDTO(
            externalId: $opportunity->external_id ?? '',
            solicitationNumber: $opportunity->solicitation_number,
            title: $opportunity->title,
            description: null,
            agencyName: $opportunity->agency_name,
            agencyCode: null,
            naicsCode: $opportunity->naics_code,
            type: null,
            setAside: null,
            placeOfPerformance: null,
            responseDeadline: null,
            archiveDate: null,
            estimatedValue: null,
            postedDate: null,
            sourceUrl: null,
            rawData: [],
        );

        $duplicate = $service->findDuplicate(
            $opportunity->organization_id,
            $opportunity->canonical_hash,
            $opportunity->external_id,
            $opportunity->solicitation_number
        );

        if ($duplicate && $duplicate->id !== $opportunity->id) {
            $opportunity->update(['duplicate_of' => $duplicate->id, 'status' => 'duplicate']);
        }
    }
}
