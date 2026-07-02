<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Requests — the first step of the procurement flow copied from the
 * legacy portal: a requester lists what they need, it goes through a simple
 * approve/reject, and an approved request is converted into a purchase order
 * (optionally via vendor quotations first). Additive, `procurement_`-prefixed.
 * Long index/FK names are set explicitly to stay within MariaDB's 64-char limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_project_id')->nullable()->constrained('crm_projects')->nullOnDelete();

            $table->string('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('draft')->index();   // App\Modules\Procurement\Enums\PurchaseRequestStatus
            $table->string('currency', 3)->default('USD');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('procurement_purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('procurement_purchase_request_id');
            $table->foreign('procurement_purchase_request_id', 'pr_items_pr_id_fk')
                ->references('id')->on('procurement_purchase_requests')->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_product_id')->nullable();
            $table->foreign('inventory_product_id', 'pr_items_product_id_fk')
                ->references('id')->on('inventory_products')->nullOnDelete();

            $table->string('description');
            $table->string('sku')->nullable();
            $table->string('unit')->nullable();                    // unit of measure label
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('procurement_purchase_request_id', 'pr_items_pr_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_request_items');
        Schema::dropIfExists('procurement_purchase_requests');
    }
};
