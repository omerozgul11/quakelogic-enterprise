<?php

namespace App\Modules\ExpenseTracker\Console;

use App\Models\User;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Console\Command;

/**
 * Seeds the default expense categories a field/construction business works with
 * (material, labor, service, rental tools, …) for an organization. Idempotent:
 * an existing category of the same name (case-insensitive) is left untouched, so
 * it is safe to re-run and never overwrites a customized colour or budget.
 */
class SeedExpenseCategoriesCommand extends Command
{
    protected $signature = 'expenses:seed-categories {--org=1 : Organization id} {--owner= : created_by user id (defaults to the org\'s first user)}';

    protected $description = 'Seed default expense categories (Material, Labor, Service, Rental Tools, …) for an organization';

    /** @var array<int,array{name:string,color:string}> */
    private const DEFAULTS = [
        ['name' => 'Material', 'color' => 'amber'],
        ['name' => 'Labor', 'color' => 'blue'],
        ['name' => 'Service', 'color' => 'indigo'],
        ['name' => 'Rental Tools', 'color' => 'violet'],
        ['name' => 'Equipment', 'color' => 'cyan'],
        ['name' => 'Subcontractor', 'color' => 'teal'],
        ['name' => 'Travel', 'color' => 'orange'],
        ['name' => 'Permits & Fees', 'color' => 'rose'],
        ['name' => 'Utilities', 'color' => 'lime'],
        ['name' => 'Office', 'color' => 'slate'],
        ['name' => 'Other', 'color' => 'gray'],
    ];

    public function handle(): int
    {
        $orgId = (int) $this->option('org');

        $ownerId = $this->option('owner')
            ? (int) $this->option('owner')
            : User::where('organization_id', $orgId)->orderBy('id')->value('id');

        if (! $ownerId) {
            $this->error("No user found for organization {$orgId} — pass --owner=<user id>.");

            return self::FAILURE;
        }

        $existing = ExpenseCategory::where('organization_id', $orgId)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        $created = 0;
        foreach (self::DEFAULTS as $row) {
            if (in_array(mb_strtolower($row['name']), $existing, true)) {
                continue;
            }

            ExpenseCategory::create([
                'organization_id' => $orgId,
                'created_by' => $ownerId,
                'name' => $row['name'],
                'color' => $row['color'],
                'is_active' => true,
            ]);
            $created++;
            $this->line("  + {$row['name']}");
        }

        $this->info("Done. Created {$created} categ/ies for organization {$orgId} (".count(self::DEFAULTS).' defaults, existing skipped).');

        return self::SUCCESS;
    }
}
