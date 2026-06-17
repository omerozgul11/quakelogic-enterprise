<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 — Compliance & Template Library.
     *  - compliance_items: org-level registrations/certs (W-9, insurance, ISO,
     *    SAM, CAGE, UEI, NDA, vendor reg) with identifiers and expiry tracking.
     *  - proposal_templates: reusable proposal content (company profile,
     *    technical narrative, QA/QC, warranty, training/installation/support).
     */
    public function up(): void
    {
        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');                    // ComplianceType enum
            $table->string('name');                    // human label, e.g. "General Liability"
            $table->string('identifier')->nullable();  // CAGE value, policy #, cert #, UEI, etc.
            $table->string('status')->default('active'); // ComplianceStatus enum
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('reference_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'expires_at']);
        });

        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');                // TemplateCategory enum
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_templates');
        Schema::dropIfExists('compliance_items');
    }
};
