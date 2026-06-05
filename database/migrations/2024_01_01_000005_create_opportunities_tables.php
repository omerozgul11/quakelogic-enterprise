<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');

            $table->string('title');
            $table->string('solicitation_number')->nullable()->index();
            $table->string('opportunity_number')->nullable()->index();
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->string('source_url', 1024)->nullable();

            $table->string('status')->default('new')->index();
            $table->string('capture_stage')->nullable()->index();
            $table->string('set_aside_type')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('naics_code', 20)->nullable()->index();
            $table->string('psc_code', 20)->nullable()->index();

            $table->string('agency_name')->nullable();
            $table->string('sub_agency_name')->nullable();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('place_of_performance_city')->nullable();
            $table->string('place_of_performance_state')->nullable();
            $table->string('place_of_performance_country', 10)->nullable();

            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->decimal('estimated_value_low', 18, 2)->nullable();
            $table->decimal('estimated_value_high', 18, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('probability_of_win', 5, 2)->nullable();

            $table->date('posted_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->date('response_deadline')->nullable();
            $table->date('award_date')->nullable();
            $table->date('period_of_performance_start')->nullable();
            $table->date('period_of_performance_end')->nullable();

            $table->text('description')->nullable();
            $table->text('scope')->nullable();
            $table->text('requirements_summary')->nullable();
            $table->text('notes')->nullable();
            $table->text('go_no_go_notes')->nullable();

            $table->string('go_no_go_decision')->nullable();
            $table->foreignId('go_no_go_decided_by')->nullable()->constrained('users');
            $table->timestamp('go_no_go_decided_at')->nullable();

            $table->json('raw_source_data')->nullable();
            $table->boolean('is_duplicate_flagged')->default(false);
            $table->unsignedBigInteger('duplicate_of')->nullable();
            $table->string('canonical_hash')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'source']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('opportunity_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('amendment_number')->nullable();
            $table->string('change_type')->nullable();
            $table->text('description')->nullable();
            $table->date('new_due_date')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunity_watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('opportunity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('opportunity_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('strength')->nullable();
            $table->string('weakness')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunity_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('company_name');
            $table->string('role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('go_no_go_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users');
            $table->string('decision');
            $table->text('rationale')->nullable();
            $table->decimal('strategic_fit_score', 5, 2)->nullable();
            $table->decimal('win_probability', 5, 2)->nullable();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->decimal('estimated_margin', 5, 2)->nullable();
            $table->json('criteria_scores')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('go_no_go_reviews');
        Schema::dropIfExists('opportunity_partners');
        Schema::dropIfExists('opportunity_competitors');
        Schema::dropIfExists('opportunity_assignments');
        Schema::dropIfExists('opportunity_watchlists');
        Schema::dropIfExists('opportunity_amendments');
        Schema::dropIfExists('opportunities');
    }
};
