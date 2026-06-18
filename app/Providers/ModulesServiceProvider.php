<?php

namespace App\Providers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Boots every enabled Enterprise Hub module discovered under app/Modules/.
 * Registered once in config/app.php; each module self-describes via module.json,
 * so new modules need no change here.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (ModuleRegistry::enabledProviders() as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
