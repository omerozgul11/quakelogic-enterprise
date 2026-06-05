<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('cage_code', 20)->nullable();
            $table->string('duns', 20)->nullable();
            $table->string('uei', 20)->nullable()->comment('SAM.gov Unique Entity Identifier');
            $table->string('ein', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 10)->default('US');
            $table->string('timezone')->default('UTC');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
