<?php

namespace App\Services\BidSources;

use App\Services\BidSources\BidPrime\BidPrimeConnector;
use App\Services\BidSources\SamGov\SamGovConnector;

class ConnectorRegistry
{
    /** @var array<string, BidSourceConnectorInterface> */
    private array $connectors = [];

    public function __construct(
        SamGovConnector $samGov,
        BidPrimeConnector $bidPrime
    ) {
        $this->connectors['sam_gov'] = $samGov;
        $this->connectors['bidprime'] = $bidPrime;
    }

    public function get(string $source): ?BidSourceConnectorInterface
    {
        return $this->connectors[$source] ?? null;
    }

    /** @return array<string, BidSourceConnectorInterface> */
    public function all(): array
    {
        return $this->connectors;
    }

    public function keys(): array
    {
        return array_keys($this->connectors);
    }
}
