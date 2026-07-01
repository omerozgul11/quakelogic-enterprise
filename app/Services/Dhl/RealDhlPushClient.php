<?php

namespace App\Services\Dhl;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live DHL tracking push subscription client (api-eu.dhl.com/tracking/push/v1),
 * authenticated with the DHL-API-Key header. DHL's push guide documents the
 * endpoints (POST /subscription, POST /subscription/{id} to activate,
 * DELETE /subscription/{id}, GET /subscriptions) but the exact create body is
 * only fully specified to credentialed apps, so the request shape is kept small
 * and defensive — tighten create() to the OpenAPI spec once sandbox access lands.
 */
class RealDhlPushClient implements DhlPushClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public function createShipmentSubscription(string $trackingNumber, string $callbackUrl): DhlSubscriptionResult
    {
        return $this->result($this->request('post', '/subscription', [
            'trackingId' => $trackingNumber,
            'callbackUrl' => $callbackUrl,
        ]));
    }

    public function createAccountSubscription(string $accountNumber, string $callbackUrl): DhlSubscriptionResult
    {
        return $this->result($this->request('post', '/subscription', [
            'accountId' => $accountNumber,
            'callbackUrl' => $callbackUrl,
        ]));
    }

    public function activate(string $subscriptionId, string $secret): void
    {
        $this->request('post', '/subscription/'.rawurlencode($subscriptionId), ['secret' => $secret]);
    }

    public function delete(string $subscriptionId): void
    {
        $this->request('delete', '/subscription/'.rawurlencode($subscriptionId));
    }

    public function list(): array
    {
        $body = $this->request('get', '/subscriptions');
        $items = data_get($body, 'subscriptions') ?? data_get($body, 'items') ?? [];

        return collect(is_array($items) ? $items : [])
            ->filter('is_array')
            ->map(fn (array $i) => $this->result($i))
            ->all();
    }

    /**
     * @param  array<string,mixed>|null  $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $request = Http::withHeaders(['DHL-API-Key' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(12);

            $url = rtrim($this->baseUrl, '/').'/tracking/push/v1'.$path;

            $response = match ($method) {
                'post' => $request->post($url, $body ?? []),
                'delete' => $request->delete($url),
                default => $request->get($url),
            };
        } catch (Throwable $e) {
            throw new DhlPushException("DHL push API {$method} {$path} error: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new DhlPushException("DHL push API {$method} {$path} failed ({$response->status()}).");
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** @param array<string,mixed> $data */
    private function result(array $data): DhlSubscriptionResult
    {
        // DHL returns the subscription URL in `self`; the last path segment is the id.
        $self = (string) data_get($data, 'self', '');
        $segments = array_values(array_filter(explode('/', (string) (parse_url($self, PHP_URL_PATH) ?: $self))));
        $id = (string) (data_get($data, 'id')
            ?? data_get($data, 'subscriptionId')
            ?? ($segments !== [] ? end($segments) : ''));

        $secret = data_get($data, 'secret');

        return new DhlSubscriptionResult(
            $id,
            (string) data_get($data, 'scope', ''),
            is_string($secret) ? $secret : null,
            $data,
        );
    }
}
