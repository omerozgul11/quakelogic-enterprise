<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 of the Project Field Information System: digital sign-offs — captured
 * signatures (customer / PM / QA / acceptance …) with an attestation statement,
 * a timestamp and an optional drawn signature image (base64 PNG). May reference
 * the execution record being accepted. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_signoffs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('crm_project_execution_record_id')->nullable()->constrained('crm_project_execution_records')->nullOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type');                 // SignoffType
            $table->string('signer_name');
            $table->string('signer_title')->nullable();
            $table->string('signer_email')->nullable();
            $table->text('statement')->nullable();
            $table->longText('signature_data')->nullable(); // base64 PNG of the drawn signature
            $table->dateTime('signed_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id'], 'cpso_org_proj_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_signoffs');
    }
};
