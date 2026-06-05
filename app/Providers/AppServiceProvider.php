<?php

namespace App\Providers;

use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiProviderInterface;
use App\Services\BidSources\SamGov\FakeSamGovClient;
use App\Services\BidSources\SamGov\SamGovConnector;
use App\Services\BidSources\BidPrime\FakeBidPrimeClient;
use App\Services\BidSources\OpportunityDeduplicationService;
use App\Services\BidSources\SamGov\SamGovImportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

        // SAM.gov client: use real if configured, fake otherwise
        $this->app->singleton(SamGovConnector::class, function () {
            $apiKey = config('integrations.sam_gov.api_key');
            $client = $apiKey ? new \App\Services\BidSources\SamGov\SamGovClient($apiKey) : new FakeSamGovClient();
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
