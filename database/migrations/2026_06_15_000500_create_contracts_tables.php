<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — Contract & Financial Lifecycle. A contract is the post-award
     * financial record for a won proposal (1:1), tracking contract/PO/invoice
     * numbers, the contract stage, payment status, and delivery milestones.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('contract_number')->nullable();
            $table->string('po_number')->nullable();           // purchase order
            $table->string('invoice_number')->nullable();

            $table->string('stage')->default('contract_review'); // ContractStage enum
            $table->string('payment_status')->default('not_invoiced'); // PaymentStatus enum

            $table->decimal('contract_value', 18, 2)->nullable();
            $table->decimal('amount_invoiced', 18, 2)->nullable();
            $table->decimal('amount_paid', 18, 2)->nullable();
            $table->string('currency', 10)->default('USD');

            $table->date('signed_at')->nullable();
            $table->date('po_received_at')->nullable();
            $table->date('invoice_sent_at')->nullable();
            $table->date('paid_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'stage']);
            $table->index(['organization_id', 'payment_status']);
        });

        Schema::create('delivery_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['contract_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_milestones');
        Schema::dropIfExists('contracts');
    }
};
