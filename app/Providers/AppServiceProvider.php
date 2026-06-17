<?php

namespace App\Providers;

use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiProviderInterface;
use App\Services\BidSources\SamGov\FakeSamGovClient;
use App\Services\BidSources\SamGov\SamGovConnector;
use App\Services\BidSources\BidPrime\FakeBidPrimeClient;
use App\Services\BidSources\OpportunityDeduplicationService;
use App\Services\BidSources\SamGov\SamGovImportService;
use App\Listeners\RefreshPipelineOnLogin;
use App\Observers\EmbeddingObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // AI Provider binding
        $this->app->singleton(AiProviderInterface::class, function () {
            return AiProviderFactory::default();
        });

        // Shipments: UPS Quantum View account-ingest client — real only when
        // enabled + credentialed + a subscription is configured; otherwise a
        // simulator drives dev so the ingest pipeline is testable.
        $this->app->singleton(\App\Services\Ups\QuantumView\QuantumViewClient::class, function () {
            $ups = config('services.ups');
            $qv = $ups['quantum_view'];
            if ($qv['enabled'] && $ups['client_id'] && $ups['client_secret'] && $qv['subscription']) {
                return new \App\Services\Ups\QuantumView\UpsQuantumViewClient(
                    $ups['client_id'], $ups['client_secret'], $ups['base_url'], $qv['subscription']
                );
            }

            return new \App\Services\Ups\QuantumView\FakeQuantumViewClient();
        });

        // SAM.gov client: use real if configured, fake otherwise
        $this->app->singleton(SamGovConnector::class, function () {
            $apiKey = config('integrations.sam_gov.api_key');
            $client = $apiKey
                ? new \App\Services\BidSources\SamGov\SamGovClient($apiKey, config('integrations.sam_gov.base_url'))
                : new FakeSamGovClient();
            return new SamGovConnector($client);
        });

        $this->app->singleton(SamGovImportService::class, function ($app) {
            return new SamGovImportService(
                $app->make(SamGovConnector::class),
                $app->make(OpportunityDeduplicationService::class)
            );
        });

        // BidPrime client binding
        $this->app->singleton(FakeBidPrimeClient::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(!$this->app->isProduction());

        // Keep the opportunity pipeline fresh on every login (purge past-due +
        // throttled SAM.gov refresh).
        Event::listen(Login::class, RefreshPipelineOnLogin::class);

        // Keep the AI knowledge base in sync: re-embed a record whenever it's
        // created/updated/deleted, so QuakeBot answers from current data.
        foreach (array_keys(EmbeddingObserver::KIND_MAP) as $modelClass) {
            $modelClass::observe(EmbeddingObserver::class);
        }

        if ($this->app->isLocal()) {
            DB::listen(function ($query) {
                if ($query->time > 500) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time' => $query->time,
                    ]);
                }
            });
        }
    }
}
