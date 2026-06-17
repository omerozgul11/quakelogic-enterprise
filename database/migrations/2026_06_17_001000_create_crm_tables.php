<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM-OWNED tables for the /crm section, added to the shared
 * `quakelogic_enterprise` database. The CRM reuses the existing Proposals-owned
 * `companies` and `contacts` tables (as Clients & Contacts) and references
 * `organizations` / `users`; everything genuinely new to the CRM lives behind a
 * `crm_` prefix here. Additive only — never drops or alters Proposals' tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Leads — top of the sales pipeline. May reference an existing company /
        // contact, or carry its own raw contact details before it's converted.
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->string('title');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();

            // Pipeline stage (see App\Enums\LeadStatus).
            $table->string('status')->default('new')->index();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        // Projects — the "Project Manager" replacement core.
        Schema::create('crm_projects', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('planned')->index();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        // Tasks — belong to a project. Dedicated to the CRM (kept separate from
        // the Proposals-domain polymorphic `tasks` table).
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_project_id', 'status']);
        });

        // Invoices & Estimates — one table, distinguished by `kind`.
        Schema::create('crm_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('crm_project_id')->nullable()->constrained('crm_projects')->nullOnDelete();

            $table->string('number');
            $table->string('kind')->default('invoice')->index();   // estimate | invoice
            $table->string('status')->default('draft')->index();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('crm_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_payments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();

            $table->decimal('amount', 18, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();      // card | check | wire | cash | other
            $table->string('reference')->nullable();
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_payments');
        Schema::dropIfExists('crm_invoice_items');
        Schema::dropIfExists('crm_invoices');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_projects');
        Schema::dropIfExists('crm_leads');
    }
};
