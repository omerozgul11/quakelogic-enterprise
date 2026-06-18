<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asset Management module — registry of deployed/internal assets + maintenance
 * history. Additive, `asset_`-prefixed. An asset may reference the Inventory
 * product it is an instance of (set when an inventory unit is commissioned into
 * an asset), but Asset Management owns the asset lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assets', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();   // deployed at (customer)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();       // internal assignee

            $table->string('asset_tag');
            $table->string('name');
            $table->string('serial_number')->nullable()->index();
            $table->string('status')->default('in_stock')->index();   // App\Modules\AssetManagement\Enums\AssetStatus
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('condition')->nullable();                  // new | good | fair | poor

            $table->decimal('purchase_cost', 18, 2)->nullable();
            $table->decimal('current_value', 18, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            $table->date('purchased_at')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->date('deployed_at')->nullable();
            $table->date('retired_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'asset_tag']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('asset_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('asset_assets')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('preventive');            // App\Modules\AssetManagement\Enums\MaintenanceType
            $table->string('status')->default('completed');           // scheduled | in_progress | completed
            $table->text('description');
            $table->decimal('cost', 18, 2)->nullable();
            $table->date('performed_at')->nullable();
            $table->date('next_due_at')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_records');
        Schema::dropIfExists('asset_assets');
    }
};
