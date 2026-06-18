<?php

namespace App\Modules\AssetManagement\Providers;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Policies\AssetPolicy;
use App\Modules\ModuleServiceProvider;

class AssetManagementServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Asset::class => AssetPolicy::class,
        ];
    }
}
