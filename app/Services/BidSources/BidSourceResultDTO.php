<?php

namespace App\Services\BidSources;

readonly class BidSourceResultDTO
{
    public function __construct(
        public string $externalId,
        public string $source,
        public string $title,
        public ?string $solicitationNumber,
        public ?string $agencyName,
        public ?string $subAgencyName,
        public ?string $naicsCode,
        public ?string $pscCode,
        public ?string $setAsideType,
        public ?string $contractType,
        public ?float $estimatedValue,
        public ?string $description,
        public ?\DateTimeInterface $postedDate,
        public ?\DateTimeInterface $dueDate,
        public ?string $placeOfPerformanceCity,
        public ?string $placeOfPerformanceState,
        public ?string $placeOfPerformanceCountry,
        public ?string $sourceUrl,
        public array $rawData = [],
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'source' => $this->source,
            'title' => $this->title,
            'solicitation_number' => $this->solicitationNumber,
            'agency_name' => $this->agencyName,
            'sub_agency_name' => $this->subAgencyName,
            'naics_code' => $this->naicsCode,
            'psc_code' => $this->pscCode,
            'set_aside_type' => $this->setAsideType,
            'contract_type' => $this->contractType,
            'estimated_value' => $this->estimatedValue,
            'description' => $this->description,
            'posted_date' => $this->postedDate?->format('Y-m-d'),
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'place_of_performance_city' => $this->placeOfPerformanceCity,
            'place_of_performance_state' => $this->placeOfPerformanceState,
            'place_of_performance_country' => $this->placeOfPerformanceCountry,
            'source_url' => $this->sourceUrl,
        ];
    }
}
