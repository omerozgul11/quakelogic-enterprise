<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasheets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product_name');
            $table->string('model_number')->nullable();
            $table->string('tagline')->nullable();
            $table->string('status')->default('draft')->index(); // draft | generated
            $table->text('input_notes')->nullable();   // pasted technical details
            $table->longText('source_text')->nullable(); // extracted text from spec docs
            $table->json('sections')->nullable();        // generated datasheet content
            $table->json('media')->nullable();           // uploaded spec docs + product images
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasheets');
    }
};
