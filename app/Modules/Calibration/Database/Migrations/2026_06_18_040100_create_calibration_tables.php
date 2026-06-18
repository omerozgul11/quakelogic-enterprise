<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calibration module — NIST-traceable calibration certificates. Additive,
 * `calibration_`-prefixed. A certificate is a completed calibration event with a
 * result and a next-due date; the schedule for an instrument is driven by its
 * most recent certificate's due_at. The composite unique index is named
 * explicitly to stay under MariaDB's 64-char identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('asset_id')->nullable()->constrained('asset_assets')->nullOnDelete();
            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('certificate_number');
            $table->string('result')->default('pass')->index();       // App\Modules\Calibration\Enums\CalibrationResult
            $table->boolean('nist_traceable')->default(true);
            $table->string('method')->nullable();
            $table->string('standard_used')->nullable();               // reference standard / equipment
            $table->string('technician')->nullable();
            $table->string('serial_number')->nullable();

            $table->date('calibrated_at');
            $table->date('due_at')->nullable()->index();
            $table->unsignedInteger('interval_months')->nullable();

            $table->json('measurements')->nullable();                  // as-found / as-left readings
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'certificate_number'], 'cal_certs_org_number_unique');
            $table->index(['organization_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_certificates');
    }
};
