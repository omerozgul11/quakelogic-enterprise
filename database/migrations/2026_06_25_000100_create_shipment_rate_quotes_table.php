<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipment rate / spot-price quotes (Shipments app). A quote captures the lane +
 * package details needed to price a shipment with a carrier, and the price the
 * carrier returned. Entered by hand today (you request a quote from the carrier
 * and record it); when a carrier has a live rating integration (e.g. DHL once an
 * API key is added) the same row is auto-filled. Additive — never alters the
 * Proposals-owned tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_rate_quotes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Optional link to an existing shipment this quote is for.
            $table->foreignId('proposal_mailing_id')->nullable()->constrained()->nullOnDelete();

            $table->string('carrier', 50)->default('dhl');
            $table->string('service_line', 20)->nullable();   // express | freight | null (general)
            $table->string('status', 20)->default('draft');   // draft | requested | quoted | expired | declined
            $table->string('reference')->nullable();           // human label for this quote

            // Lane
            $table->string('origin_city')->nullable();
            $table->string('origin_state', 60)->nullable();
            $table->string('origin_postal', 20)->nullable();
            $table->string('origin_country', 2)->default('US');
            $table->string('dest_city')->nullable();
            $table->string('dest_state', 60)->nullable();
            $table->string('dest_postal', 20)->nullable();
            $table->string('dest_country', 2)->default('US');
            $table->date('ready_date')->nullable();
            $table->string('service_level')->nullable();        // DHL product, e.g. "Express Worldwide"

            // Parcel weight / dimensions (express)
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit', 5)->default('lb');    // lb | kg
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('dim_unit', 5)->default('in');       // in | cm

            // Freight (LTL)
            $table->string('freight_class', 10)->nullable();
            $table->unsignedSmallInteger('pallet_count')->nullable();
            $table->unsignedSmallInteger('piece_count')->nullable();
            $table->json('accessorials')->nullable();           // ["liftgate_delivery","residential",...]

            // Quote result (decimal for money — never float)
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('transit_days')->nullable();
            $table->date('estimated_delivery')->nullable();
            $table->string('quote_reference')->nullable();      // carrier's quote/confirmation id
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('source', 10)->default('manual');    // manual | api
            $table->text('notes')->nullable();
            $table->json('raw_response')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'carrier']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_rate_quotes');
    }
};
