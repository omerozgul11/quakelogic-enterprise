<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance / AR module. Deliberately does NOT add another invoice or payment
 * table — it reuses the existing crm_invoices / crm_payments (one owner per the
 * master architecture). It only adds what was genuinely missing: gateway
 * payment intents (online collection) and credit notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A gateway checkout/payment attempt against an invoice. On capture it
        // links to the crm_payments row that was recorded.
        Schema::create('finance_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->foreignId('crm_payment_id')->nullable()->constrained('crm_payments')->nullOnDelete();

            $table->string('provider');                               // fake | stripe | paypal | square
            $table->string('reference')->nullable();                  // gateway transaction id
            $table->string('checkout_url')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending')->index();    // App\Modules\Finance\Enums\PaymentIntentStatus
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('finance_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('crm_invoice_id')->nullable()->constrained('crm_invoices')->nullOnDelete();

            $table->string('number');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('reason')->nullable();
            $table->string('status')->default('open')->index();       // App\Modules\Finance\Enums\CreditNoteStatus
            $table->date('issued_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_credit_notes');
        Schema::dropIfExists('finance_payment_intents');
    }
};
