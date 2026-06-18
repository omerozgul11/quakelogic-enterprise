<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Desk module — support/service/RMA/field-service tickets plus a comment
 * thread. Additive. Tickets reference (don't own) companies/contacts/assets;
 * RMA-specific fields (returned product, serial, disposition) live on the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('asset_assets')->nullOnDelete();
            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();

            $table->string('number');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('type')->default('support')->index();      // App\Modules\ServiceDesk\Enums\TicketType
            $table->string('status')->default('new')->index();        // App\Modules\ServiceDesk\Enums\TicketStatus
            $table->string('priority')->default('normal')->index();   // App\Modules\ServiceDesk\Enums\TicketPriority
            $table->string('channel')->nullable();                    // email | phone | portal | web
            $table->string('serial_number')->nullable();
            $table->string('rma_disposition')->nullable();            // repair | replace | refund | reject

            $table->timestamp('due_at')->nullable()->index();         // SLA target
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->text('body');
            $table->boolean('is_internal')->default(false);           // internal note vs. reply to requester

            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
