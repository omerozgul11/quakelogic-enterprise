<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Tracker module — categories (with budgets), expenses, recurring costs
 * and receipt attachments. Additive only, all `expense_`-prefixed; it creates
 * brand-new tables and never alters or drops anything that already exists.
 * Long auto-generated identifiers are named explicitly to stay <= 64 chars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('name', 160);
            $table->string('color', 20)->nullable();
            $table->decimal('budget_amount', 18, 2)->nullable();      // null = no budget set
            $table->string('budget_period', 20)->default('monthly');  // monthly|quarterly|yearly
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
            $table->index('organization_id');
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();

            // Optional CRM links so a recurring cost can be attributed to a client/project/proposal.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('crm_project_id')->nullable()->constrained('crm_projects')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('proposal_submissions')->nullOnDelete();

            $table->string('name');
            $table->string('vendor')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 30)->nullable();     // App\Modules\ExpenseTracker\Enums\PaymentMethod
            $table->boolean('is_billable')->default(false);

            $table->string('frequency', 20)->default('monthly')->index(); // RecurringFrequency
            $table->unsignedInteger('interval_count')->default(1);        // every N frequency units
            $table->date('start_date');
            $table->date('end_date')->nullable();                 // null = open-ended
            $table->date('next_run_date');                        // scheduler driver
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('auto_approve')->default(false);      // generated expense lands approved vs draft
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'next_run_date'], 'recurring_expenses_due_index');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');          // who recorded the row
            $table->foreignId('owner_id')->constrained('users');            // who incurred the cost
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();

            // Optional CRM links + billable flag for contract-cost tracking.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('crm_project_id')->nullable()->constrained('crm_projects')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('proposal_submissions')->nullOnDelete();

            $table->string('number');                              // EXP-YYYY-NNNN
            $table->string('vendor')->nullable();                 // merchant / payee
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 30)->nullable();     // PaymentMethod
            $table->string('status')->default('draft')->index();  // ExpenseStatus
            $table->boolean('is_billable')->default(false);
            $table->date('expense_date');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'expense_category_id']);
            $table->index(['organization_id', 'expense_date']);
            $table->index('owner_id');
        });

        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');

            $table->string('display_name');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();

            $table->timestamps();

            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
