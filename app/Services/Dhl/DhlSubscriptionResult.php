<?php

namespace App\Services\Dhl;

/** A DHL push-subscription record as returned by the push API (create/list). */
final readonly class DhlSubscriptionResult
{
    /**
     * @param  array<string,mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $scope,
        public ?string $secret,
        public array $raw = [],
    ) {}
}
