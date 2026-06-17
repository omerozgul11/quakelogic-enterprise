<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Broaden the knowledge base to index ALL business data, not just proposals.
     * document_embeddings becomes polymorphic: (source_type, source_id) instead
     * of a hard proposal FK, with a human source_label for citations. This is a
     * rebuildable embeddings cache (no source-of-truth data), so we recreate it.
     */
    public function up(): void
    {
        Schema::dropIfExists('document_embeddings');

        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);            // proposal, proposal_file, opportunity, company, contact, ...
            $table->unsignedBigInteger('source_id');
            $table->string('source_label')->nullable();    // human citation label
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            $table->json('embedding');
            $table->string('model')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['source_type', 'source_id']);
            $table->index(['organization_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
