<?php

namespace App\Modules\Manufacturing\Providers;

use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Policies\BomPolicy;
use App\Modules\Manufacturing\Policies\WorkOrderPolicy;
use App\Modules\ModuleServiceProvider;

class ManufacturingServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Bom::class => BomPolicy::class,
            WorkOrder::class => WorkOrderPolicy::class,
        ];
    }
}
