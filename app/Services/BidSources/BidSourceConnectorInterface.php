<?php

namespace App\Services\BidSources;

interface BidSourceConnectorInterface
{
    public function getSourceName(): string;

    public function isConfigured(): bool;

    /**
     * Fetch opportunities from the source.
     *
     * @param array $filters e.g. naics codes, keywords, agencies
     * @param int $limit
     * @param int $offset
     * @return BidSourceResultDTO[]
     */
    public function fetchOpportunities(array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Fetch a single opportunity by external ID.
     */
    public function fetchOpportunity(string $externalId): ?BidSourceResultDTO;

    /**
     * Check if source supports incremental updates since a given date.
     */
    public function supportsIncrementalSync(): bool;
}
