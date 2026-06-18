<?php

namespace App\Modules\Procurement\Providers;

use App\Modules\ModuleServiceProvider;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Policies\PurchaseOrderPolicy;
use App\Modules\Procurement\Policies\SupplierPolicy;

class ProcurementServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Supplier::class => SupplierPolicy::class,
            PurchaseOrder::class => PurchaseOrderPolicy::class,
        ];
    }
}
