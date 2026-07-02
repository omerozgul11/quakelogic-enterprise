<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File attachments for procurement documents (purchase requests, quotations,
 * purchase orders, bills). Polymorphic so one table serves every document type,
 * matching the legacy per-document files. Files live on the private `local`
 * disk; download is only ever through an authorized controller action.
 * Additive, `procurement_`-prefixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Polymorphic parent (PR / Quotation / PO / Bill). Short explicit
            // index name to stay under MariaDB's 64-char limit.
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->index(['attachable_type', 'attachable_id'], 'pur_attach_morph_idx');

            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by', 'pur_attach_uploader_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_attachments');
    }
};
