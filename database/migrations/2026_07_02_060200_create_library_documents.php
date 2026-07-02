<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Library documents — the uploaded files themselves. Column shape mirrors the
 * proven ProposalFile / CRM ProjectFile pattern (private `local` disk, sha256
 * checksum, version families via parent_document_id + is_current_version) with
 * the additions the Library needs: a nullable folder, a description, a
 * shared/private visibility with owner, and an `ai_indexed` flag controlling
 * whether the file is fed to QuakeBot's knowledge base. Additive table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_documents', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('library_folder_id')->nullable();
            $table->foreign('library_folder_id', 'library_documents_folder_fk')
                ->references('id')->on('library_folders')->nullOnDelete();

            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();

            $table->string('visibility', 20)->default('shared'); // shared | private
            $table->unsignedBigInteger('owner_id')->nullable();   // set when private
            $table->foreign('owner_id', 'library_documents_owner_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->boolean('ai_indexed')->default(true);

            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->unsignedBigInteger('parent_document_id')->nullable();
            $table->foreign('parent_document_id', 'library_documents_parent_fk')
                ->references('id')->on('library_documents')->nullOnDelete();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by', 'library_documents_uploader_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'library_folder_id', 'is_current_version'], 'library_docs_org_folder_cur_idx');
            $table->index(['organization_id', 'visibility'], 'library_docs_org_vis_idx');
            $table->index('parent_document_id', 'library_docs_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_documents');
    }
};
