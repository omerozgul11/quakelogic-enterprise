<?php

namespace App\Modules\ExpenseTracker\Providers;

use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Models\RecurringExpense;
use App\Modules\ExpenseTracker\Observers\ExpenseObserver;
use App\Modules\ExpenseTracker\Policies\ExpenseCategoryPolicy;
use App\Modules\ExpenseTracker\Policies\ExpensePolicy;
use App\Modules\ExpenseTracker\Policies\RecurringExpensePolicy;
use App\Modules\ExpenseTracker\QuickBooks\QuickBooksClientFactory;
use App\Modules\ExpenseTracker\QuickBooks\QuickBooksClientInterface;
use App\Modules\ModuleServiceProvider;

class ExpenseTrackerServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuickBooksClientInterface::class, fn () => QuickBooksClientFactory::default());
    }

    public function boot(): void
    {
        parent::boot();
        Expense::observe(ExpenseObserver::class);
    }

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Expense::class => ExpensePolicy::class,
            ExpenseCategory::class => ExpenseCategoryPolicy::class,
            RecurringExpense::class => RecurringExpensePolicy::class,
        ];
    }
}
