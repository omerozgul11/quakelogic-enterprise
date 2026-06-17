<?php

namespace Tests\Unit\Services;

use App\Services\BidSources\BidSourceResultDTO;
use App\Services\BidSources\OpportunityDeduplicationService;
use PHPUnit\Framework\TestCase;

class OpportunityDeduplicationTest extends TestCase
{
    private OpportunityDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OpportunityDeduplicationService();
    }

    private function makeDto(string $externalId = 'abc123', string $solicitationNumber = 'W911QY-24-R-0001'): BidSourceResultDTO
    {
        return new BidSourceResultDTO(
            externalId: $externalId,
            source: 'sam_gov',
            title: 'Test Opportunity',
            solicitationNumber: $solicitationNumber,
            agencyName: 'Army',
            subAgencyName: null,
            naicsCode: '541512',
            pscCode: null,
            setAsideType: null,
            contractType: null,
            estimatedValue: null,
            description: null,
            postedDate: null,
            dueDate: null,
            placeOfPerformanceCity: null,
            placeOfPerformanceState: null,
            placeOfPerformanceCountry: null,
            sourceUrl: null,
            rawData: [],
        );
    }

    public function test_compute_hash_is_deterministic(): void
    {
        $dto = $this->makeDto();

        $hash1 = $this->service->computeHash($dto);
        $hash2 = $this->service->computeHash($dto);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA256 hex is 64 chars
    }

    public function test_different_solicitation_numbers_produce_different_hashes(): void
    {
        $dto1 = $this->makeDto(externalId: 'same', solicitationNumber: 'SOL-001');
        $dto2 = $this->makeDto(externalId: 'same', solicitationNumber: 'SOL-002');

        $this->assertNotEquals($this->service->computeHash($dto1), $this->service->computeHash($dto2));
    }
}
