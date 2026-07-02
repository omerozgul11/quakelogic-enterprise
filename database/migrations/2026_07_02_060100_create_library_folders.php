<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document Library folders — a Google-Drive-style, org-shared file store that
 * lives under the Proposals app. Folders nest (self-referential parent_id) so
 * users can organise however they like. A folder is either `shared` (visible to
 * everyone in the org with library access) or `private` (only its owner). New,
 * `library_`-prefixed table — additive, touches nothing existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_folders', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'library_folders_parent_fk')
                ->references('id')->on('library_folders')->nullOnDelete();

            $table->string('name');
            $table->string('visibility', 20)->default('shared'); // shared | private
            $table->unsignedBigInteger('owner_id')->nullable();   // set when private
            $table->foreign('owner_id', 'library_folders_owner_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'library_folders_creator_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'parent_id'], 'library_folders_org_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_folders');
    }
};
