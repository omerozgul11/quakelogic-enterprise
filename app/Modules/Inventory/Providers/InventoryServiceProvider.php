<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Policies\ProductPolicy;
use App\Modules\Inventory\Policies\WarehousePolicy;
use App\Modules\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        // app/Modules/Inventory (parent of this Providers/ directory).
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Product::class => ProductPolicy::class,
            Warehouse::class => WarehousePolicy::class,
        ];
    }
}
