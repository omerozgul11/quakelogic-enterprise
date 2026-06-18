<?php

namespace App\Modules;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base service provider for Enterprise Hub modules (app/Modules/<Name>/).
 *
 * Concrete module providers extend this and declare their path + policies; the
 * base wires up the conventional pieces: self-contained migrations, a web
 * routes.php (wrapped in the shared section middleware), and policy bindings.
 * Existing flat app code is untouched — only new modules live here.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Absolute path to the module directory (the folder holding module.json). */
    abstract protected function modulePath(): string;

    /**
     * Model => Policy bindings for this module.
     *
     * @return array<class-string,class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * Middleware applied to the module's routes.php. All Hub modules are
     * authenticated, verified web sections behind the global proposals login.
     *
     * @return array<int,string>
     */
    protected function routeMiddleware(): array
    {
        return ['web', 'auth', 'verified'];
    }

    public function boot(): void
    {
        $path = $this->modulePath();

        if (is_dir($path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($path.'/Database/Migrations');
        }

        if (is_file($path.'/routes.php')) {
            Route::middleware($this->routeMiddleware())->group($path.'/routes.php');
        }

        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
