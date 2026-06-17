<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Knowledge base / RAG store. Each row is a text chunk of a proposal plus its
     * embedding vector (stored as JSON). MariaDB 10.11 has no native vector type,
     * so similarity is computed in PHP over the org-scoped set — fine for the
     * modest corpus sizes here.
     */
    public function up(): void
    {
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            $table->json('embedding');
            $table->string('model')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('proposal_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
