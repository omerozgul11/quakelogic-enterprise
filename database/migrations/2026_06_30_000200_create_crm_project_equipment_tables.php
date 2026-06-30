<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the Project Field Information System: the equipment being installed
 * and the shipments that bring it to site. Both hang off crm_projects; equipment
 * may reference the shipment that carried it and (optionally) a tracked Asset.
 *
 * Purely additive. Shipments are created before equipment so the FK resolves.
 * Weights/dimensions are free strings so units (lbs/kg, in/cm) stay explicit on
 * the briefing. asset_id is a soft reference (no DB FK) to keep this decoupled
 * from the Asset Management module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_shipments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('direction')->default('inbound'); // inbound | outbound | internal
            $table->string('carrier')->nullable();           // App\Enums\Carrier value
            $table->string('service')->nullable();           // Ground, Freight LTL, …
            $table->string('tracking_number')->nullable();
            $table->string('status')->default('preparing');  // ProjectShipmentStatus

            $table->date('shipped_date')->nullable();
            $table->date('expected_arrival')->nullable();
            $table->date('arrived_date')->nullable();

            $table->string('crate_number')->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->string('pallet_info')->nullable();

            $table->string('weight')->nullable();
            $table->string('gross_weight')->nullable();
            $table->string('net_weight')->nullable();
            $table->string('shipping_weight')->nullable();
            $table->string('dimensions')->nullable();

            $table->string('bill_of_lading')->nullable();
            $table->string('packing_list')->nullable();

            $table->text('forklift_instructions')->nullable();
            $table->text('lift_points')->nullable();
            $table->string('shock_indicator')->nullable();   // none | intact | tripped
            $table->string('tilt_indicator')->nullable();    // none | intact | tripped

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id']);
            $table->index(['crm_project_id', 'status']);
        });

        Schema::create('crm_project_equipment', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('crm_project_shipment_id')->nullable()->constrained('crm_project_shipments')->nullOnDelete();
            $table->unsignedBigInteger('asset_id')->nullable(); // soft link to AssetManagement asset
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('product')->nullable();
            $table->string('model')->nullable();
            $table->string('revision')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('firmware')->nullable();
            $table->string('software_version')->nullable();
            $table->string('asset_tag')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            $table->string('power')->nullable();
            $table->string('voltage')->nullable();
            $table->string('weight')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('center_of_gravity')->nullable();
            $table->string('lift_points')->nullable();
            $table->text('rigging_instructions')->nullable();
            $table->string('installation_location')->nullable();

            $table->string('calibration_status')->nullable();
            $table->date('calibration_due')->nullable();
            $table->string('warranty_status')->nullable();
            $table->date('warranty_expires')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_equipment');
        Schema::dropIfExists('crm_project_shipments');
    }
};
