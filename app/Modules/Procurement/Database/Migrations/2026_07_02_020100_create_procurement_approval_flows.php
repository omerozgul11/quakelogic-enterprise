<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable multi-level approval chains (ported from the legacy RISE Purchase
 * plugin's pur_approval_setting / pur_approval_details). A flow is an ordered
 * set of steps for a document type, optionally tiered by amount; when a document
 * is submitted, the matching flow is instantiated into a per-document approval
 * with per-step state (incl. an optional digital signature). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Chain templates: one flow = an ordered set of steps for a doc type,
        // applied when the document total >= min_amount (highest tier wins).
        Schema::create('procurement_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('document_type');                 // purchase_request | purchase_order | bill_payment
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'document_type', 'is_active'], 'pur_flows_lookup_idx');
        });

        Schema::create('procurement_approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('procurement_approval_flow_id');
            $table->foreign('procurement_approval_flow_id', 'pur_flowstep_flow_fk')
                ->references('id')->on('procurement_approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name')->nullable();
            $table->string('approver_type');                 // user | role
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->foreign('approver_user_id', 'pur_flowstep_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->string('approver_role')->nullable();     // Spatie role name when approver_type = role
            $table->boolean('require_signature')->default(false);
            $table->timestamps();
            $table->index('procurement_approval_flow_id', 'pur_flowstep_flow_idx');
        });

        // Per-document running chain.
        Schema::create('procurement_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('procurement_approval_flow_id')->nullable();
            $table->foreign('procurement_approval_flow_id', 'pur_appr_flow_fk')
                ->references('id')->on('procurement_approval_flows')->nullOnDelete();
            $table->string('status')->default('pending');    // pending | approved | rejected
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id'], 'pur_appr_morph_idx');
        });

        // Instantiated steps for a given per-document approval.
        Schema::create('procurement_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('procurement_approval_id');
            $table->foreign('procurement_approval_id', 'pur_apprstep_appr_fk')
                ->references('id')->on('procurement_approvals')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name')->nullable();
            $table->string('approver_type');
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->foreign('approver_user_id', 'pur_apprstep_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->string('approver_role')->nullable();
            $table->boolean('require_signature')->default(false);
            $table->string('status')->default('pending');    // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->foreign('decided_by', 'pur_apprstep_decider_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->string('signature_path')->nullable();    // private-disk path to the captured signature PNG
            $table->timestamps();
            $table->index('procurement_approval_id', 'pur_apprstep_appr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_approval_steps');
        Schema::dropIfExists('procurement_approvals');
        Schema::dropIfExists('procurement_approval_flow_steps');
        Schema::dropIfExists('procurement_approval_flows');
    }
};
