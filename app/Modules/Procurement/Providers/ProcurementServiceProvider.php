<?php

namespace App\Modules\Procurement\Providers;

use App\Modules\ModuleServiceProvider;
use App\Modules\Procurement\Console\GenerateRecurringBillsCommand;
use App\Modules\Procurement\Console\PortalMigratePurchasingCommand;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Policies\BillPolicy;
use App\Modules\Procurement\Policies\PurchaseOrderPolicy;
use App\Modules\Procurement\Policies\PurchaseRequestPolicy;
use App\Modules\Procurement\Policies\QuotationPolicy;
use App\Modules\Procurement\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Route;

class ProcurementServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    public function register(): void
    {
        parent::register();

        // Module config (vendor-portal feature flag, etc.).
        $this->mergeConfigFrom($this->modulePath().'/config/procurement.php', 'procurement');
    }

    public function boot(): void
    {
        parent::boot();

        // Vendor self-service portal — registered under `web` + `vendor` prefix,
        // deliberately OUTSIDE the staff [web, auth, verified] group so a vendor
        // session can never reach a staff route. Also load its Blade views.
        Route::middleware('web')->prefix('vendor')->name('vendor.')
            ->group($this->modulePath().'/vendor-routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateRecurringBillsCommand::class, PortalMigratePurchasingCommand::class]);
        }
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Supplier::class => SupplierPolicy::class,
            PurchaseOrder::class => PurchaseOrderPolicy::class,
            PurchaseRequest::class => PurchaseRequestPolicy::class,
            Quotation::class => QuotationPolicy::class,
            Bill::class => BillPolicy::class,
        ];
    }
}
