<?php

namespace App\Services\Dhl;

use Illuminate\Support\Str;

/**
 * In-memory DHL push client for dev/tests. Records the calls it receives (so the
 * subscription + activation flow can be asserted) and returns deterministic ids.
 * Never hits DHL — bound whenever DHL_API_KEY is absent (see AppServiceProvider).
 */
class FakeDhlPushClient implements DhlPushClient
{
    /** @var array<int,array{tracking_number?:string,account_number?:string,callback_url:string,id:string}> */
    public array $created = [];

    /** @var array<int,array{id:string,secret:string}> */
    public array $activated = [];

    /** @var string[] */
    public array $deleted = [];

    public function createShipmentSubscription(string $trackingNumber, string $callbackUrl): DhlSubscriptionResult
    {
        $id = (string) Str::uuid();
        $this->created[] = ['tracking_number' => $trackingNumber, 'callback_url' => $callbackUrl, 'id' => $id];

        return new DhlSubscriptionResult($id, 'subscription.created', null, ['self' => "https://api-test.dhl.com/tracking/push/v1/subscription/{$id}"]);
    }

    public function createAccountSubscription(string $accountNumber, string $callbackUrl): DhlSubscriptionResult
    {
        $id = (string) Str::uuid();
        $this->created[] = ['account_number' => $accountNumber, 'callback_url' => $callbackUrl, 'id' => $id];

        return new DhlSubscriptionResult($id, 'subscription.created', null, ['self' => "https://api-test.dhl.com/tracking/push/v1/subscription/{$id}"]);
    }

    public function activate(string $subscriptionId, string $secret): void
    {
        $this->activated[] = ['id' => $subscriptionId, 'secret' => $secret];
    }

    public function delete(string $subscriptionId): void
    {
        $this->deleted[] = $subscriptionId;
    }

    public function list(): array
    {
        return [];
    }
}
