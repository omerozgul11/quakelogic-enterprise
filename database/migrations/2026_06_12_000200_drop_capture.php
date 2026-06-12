<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The Capture module was removed. Tear down its tables, the vestigial
     * `capture_stage` column on opportunities, and its RBAC (the four capture
     * permissions + the "Capture Manager" role). This teardown is one-way.
     */
    public function up(): void
    {
        // Children first (they FK to capture_plans with cascade), then the parent.
        Schema::dropIfExists('capture_decisions');
        Schema::dropIfExists('capture_tasks');
        Schema::dropIfExists('capture_risks');
        Schema::dropIfExists('capture_stage_history');
        Schema::dropIfExists('capture_plans');

        if (Schema::hasColumn('opportunities', 'capture_stage')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->dropColumn('capture_stage');
            });
        }

        Permission::whereIn('name', [
            'view capture plans', 'manage capture plans', 'manage capture risks', 'manage capture decisions',
        ])->delete();
        Role::where('name', 'Capture Manager')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible — the Capture feature has been removed.
    }
};
