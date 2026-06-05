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

    public function test_compute_hash_is_deterministic(): void
    {
        $dto = new BidSourceResultDTO(
            externalId: 'abc123',
            solicitationNumber: 'W911QY-24-R-0001',
            title: 'Test Opportunity',
            description: null,
            agencyName: 'Army',
            agencyCode: null,
            naicsCode: '541512',
            type: null,
            setAside: null,
            placeOfPerformance: null,
            responseDeadline: '2024-06-30',
            archiveDate: null,
            estimatedValue: null,
            postedDate: null,
            sourceUrl: null,
            rawData: [],
        );

        $hash1 = $this->service->computeHash($dto);
        $hash2 = $this->service->computeHash($dto);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA256 hex is 64 chars
    }

    public function test_different_solicitation_numbers_produce_different_hashes(): void
    {
        $dto1 = new BidSourceResultDTO('a', 'SOL-001', 'Title', null, 'Agency', null, null, null, null, null, null, null, null, null, null, []);
        $dto2 = new BidSourceResultDTO('b', 'SOL-002', 'Title', null, 'Agency', null, null, null, null, null, null, null, null, null, null, []);

        $this->assertNotEquals($this->service->computeHash($dto1), $this->service->computeHash($dto2));
    }
}
