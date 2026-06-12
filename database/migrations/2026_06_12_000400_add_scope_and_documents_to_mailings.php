<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_mailings', function (Blueprint $table) {
            // Domestic vs international — used to separate the dashboard + filter.
            $table->string('scope')->default('domestic')->after('carrier')->index();
        });

        Schema::create('mailing_documents', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('proposal_mailing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('display_name');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            // label | customs | receipt | other
            $table->string('document_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('proposal_mailing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_documents');
        Schema::table('proposal_mailings', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
