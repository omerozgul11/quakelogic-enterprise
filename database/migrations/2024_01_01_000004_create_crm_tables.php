<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->string('name');
            $table->string('acronym', 30)->nullable();
            $table->string('agency_type')->nullable();
            $table->string('federal_code', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 10)->default('US');
            $table->text('notes')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->string('name');
            $table->string('company_type')->nullable();
            $table->string('industry')->nullable();
            $table->string('cage_code', 20)->nullable();
            $table->string('uei', 20)->nullable();
            $table->string('duns', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 10)->default('US');
            $table->decimal('annual_revenue', 18, 2)->nullable();
            $table->integer('employee_count')->nullable();
            $table->text('notes')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('title')->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_key_contact')->default(false);
            $table->text('notes')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'last_name', 'first_name']);
            $table->index(['agency_id']);
            $table->index(['company_id']);
        });

        Schema::create('consultants', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->string('partnership_type')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->string('vendor_type')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->morphs('subject');
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('consultants');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('agencies');
    }
};
