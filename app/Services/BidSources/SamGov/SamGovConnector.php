<?php

namespace App\Services\BidSources\SamGov;

use App\Services\BidSources\BidSourceConnectorInterface;
use App\Services\BidSources\BidSourceResultDTO;

class SamGovConnector implements BidSourceConnectorInterface
{
    public function __construct(
        private readonly SamGovClient|FakeSamGovClient $client
    ) {}

    public function getSourceName(): string { return 'sam_gov'; }

    public function isConfigured(): bool
    {
        return !empty(config('integrations.sam_gov.api_key'));
    }

    public function fetchOpportunities(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->client->searchOpportunities(array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    public function fetchOpportunity(string $externalId): ?BidSourceResultDTO
    {
        return $this->client->getOpportunity($externalId);
    }

    /**
     * Full-text keyword search (matches descriptions, not just titles).
     *
     * @return BidSourceResultDTO[]
     */
    public function searchFullText(string $keyword, int $limit = 25): array
    {
        return method_exists($this->client, 'searchFullText')
            ? $this->client->searchFullText($keyword, $limit)
            : [];
    }

    /**
     * Past awarded contracts for pricing benchmarks.
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchAwards(array $filters = []): array
    {
        return method_exists($this->client, 'searchAwards')
            ? $this->client->searchAwards($filters)
            : [];
    }

    /**
     * Full-text search for award notices (no amounts — enrich via getAward).
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchAwardsFullText(string $keyword, int $limit = 25): array
    {
        return method_exists($this->client, 'searchAwardsFullText')
            ? $this->client->searchAwardsFullText($keyword, $limit)
            : [];
    }

    /** @return array<string,mixed>|null */
    public function getAward(string $noticeId): ?array
    {
        return method_exists($this->client, 'getAward')
            ? $this->client->getAward($noticeId)
            : null;
    }

    public function supportsIncrementalSync(): bool { return true; }
}
