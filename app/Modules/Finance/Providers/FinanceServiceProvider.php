<?php

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Models\CreditNote;
use App\Modules\Finance\Payments\PaymentProviderFactory;
use App\Modules\Finance\Payments\PaymentProviderInterface;
use App\Modules\Finance\Policies\CreditNotePolicy;
use App\Modules\ModuleServiceProvider;

class FinanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // config/finance.php is auto-loaded by Laravel; just bind the gateway.
        $this->app->singleton(PaymentProviderInterface::class, fn () => PaymentProviderFactory::default());
    }

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            CreditNote::class => CreditNotePolicy::class,
        ];
    }
}
