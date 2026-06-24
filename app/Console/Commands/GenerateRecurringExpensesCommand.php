<?php

namespace App\Console\Commands;

use App\Modules\ExpenseTracker\Models\RecurringExpense;
use App\Modules\ExpenseTracker\Services\RecurringExpenseGenerator;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:generate-recurring
                            {--org= : Limit to a single organization id}
                            {--dry-run : Report what would be generated without creating anything}';

    protected $description = 'Generate due expenses from active recurring cost schedules and advance their next run date.';

    public function handle(RecurringExpenseGenerator $generator): int
    {
        $today = now()->startOfDay();

        $schedules = RecurringExpense::query()
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today->toDateString()))
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->orderBy('next_run_date')
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No recurring costs are due.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        foreach ($schedules as $schedule) {
            if ($dryRun) {
                $this->line("• Due: \"{$schedule->name}\" ({$schedule->amount} {$schedule->currency}) — next run {$schedule->next_run_date->toDateString()}");

                continue;
            }

            $count = $generator->generateDue($schedule, $today);
            $created += $count;
            if ($count > 0) {
                $this->line("• \"{$schedule->name}\" → {$count} expense(s) generated.");
            }
        }

        $this->info($dryRun
            ? "{$schedules->count()} schedule(s) are due."
            : "Generated {$created} expense(s) from {$schedules->count()} schedule(s).");

        return self::SUCCESS;
    }
}
