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

    public function supportsIncrementalSync(): bool { return true; }
}
