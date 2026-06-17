<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost line items for a proposal — the direct costs of delivering the bid
     * (equipment, shipping/import, travel, installation, labor, overhead, …).
     * Summed against the proposal_value bid, these give a quick potential-profit
     * and margin estimate per proposal. Amounts are stored in the proposal's own
     * currency (no per-line currency) so margin math stays consistent.
     */
    public function up(): void
    {
        Schema::create('proposal_costs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('description');
            $table->string('category')->default('other'); // CostCategory enum
            $table->decimal('amount', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['proposal_submission_id', 'sort_order']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_costs');
    }
};
