<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity Assignment module — richer user profiles. These power the daily
 * match-scoring engine (relevance per user), the AI assignment recommendations,
 * and the executive workload view:
 *  - department, product/industry expertise, geographic focus: matching signals.
 *  - min/max_opportunity_value: the value band a user wants to pursue.
 *  - workload_score: cached count of a user's active responsibilities so the
 *    assignment engine can balance load without recomputing every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('title');
            $table->json('product_expertise')->nullable()->after('pipeline_keywords');
            $table->json('industry_expertise')->nullable()->after('product_expertise');
            $table->json('geographic_focus')->nullable()->after('industry_expertise');
            $table->decimal('min_opportunity_value', 18, 2)->nullable()->after('geographic_focus');
            $table->decimal('max_opportunity_value', 18, 2)->nullable()->after('min_opportunity_value');
            $table->unsignedInteger('workload_score')->default(0)->after('max_opportunity_value');
            $table->timestamp('workload_updated_at')->nullable()->after('workload_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'department', 'product_expertise', 'industry_expertise', 'geographic_focus',
                'min_opportunity_value', 'max_opportunity_value', 'workload_score', 'workload_updated_at',
            ]);
        });
    }
};
