<?php

namespace App\Services\BidSources\BidPrime;

use App\Services\BidSources\BidSourceConnectorInterface;
use App\Services\BidSources\BidSourceResultDTO;

class BidPrimeConnector implements BidSourceConnectorInterface
{
    public function __construct(
        private readonly FakeBidPrimeClient $client
    ) {}

    public function getSourceName(): string { return 'bidprime'; }

    public function isConfigured(): bool
    {
        return !empty(config('integrations.bidprime.api_key'));
    }

    public function fetchOpportunities(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->client->searchOpportunities($filters);
    }

    public function fetchOpportunity(string $externalId): ?BidSourceResultDTO
    {
        return $this->client->getOpportunity($externalId);
    }

    public function supportsIncrementalSync(): bool
    {
        return false;
    }
}
