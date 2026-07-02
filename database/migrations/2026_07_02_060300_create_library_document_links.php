<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a library document to another record — a proposal, purchase order,
 * project, opportunity, company, contact, invoice or supplier. `linkable_type`
 * holds the Library's own short type key (e.g. 'purchase_order'), resolved
 * through App\Support\Library\LinkTargets rather than Eloquent's global morph
 * map, so this stays fully isolated from the app's existing polymorphs.
 * Additive table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_document_links', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('library_document_id');
            $table->foreign('library_document_id', 'library_doc_links_doc_fk')
                ->references('id')->on('library_documents')->cascadeOnDelete();

            $table->string('linkable_type', 40);
            $table->unsignedBigInteger('linkable_id');
            $table->string('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'library_doc_links_creator_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['library_document_id', 'linkable_type', 'linkable_id'], 'library_doc_links_unique');
            $table->index(['linkable_type', 'linkable_id'], 'library_doc_links_target_idx');
            $table->index('organization_id', 'library_doc_links_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_document_links');
    }
};
