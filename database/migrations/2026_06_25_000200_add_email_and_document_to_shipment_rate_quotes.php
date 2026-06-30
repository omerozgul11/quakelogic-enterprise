<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spot quotes are requested by email and returned as a PDF rate sheet — there's
 * no carrier API. Add the carrier-contact email + a "when we asked" timestamp,
 * and a single attached rate-sheet document (the PDF DHL emails back, which the
 * AI extractor reads to pre-fill the price/transit/validity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_rate_quotes', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('reference');
            $table->timestamp('requested_at')->nullable()->after('quoted_at');

            $table->string('document_path')->nullable()->after('raw_response');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime', 100)->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime');
            $table->timestamp('document_uploaded_at')->nullable()->after('document_size');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_rate_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email', 'requested_at',
                'document_path', 'document_name', 'document_mime', 'document_size', 'document_uploaded_at',
            ]);
        });
    }
};
