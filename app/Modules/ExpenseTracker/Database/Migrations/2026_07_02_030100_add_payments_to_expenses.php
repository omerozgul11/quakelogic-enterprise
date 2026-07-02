<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Turns an expense into a payable invoice: track how much has been paid so the
 | payment status (Due / Partially paid / Paid) is derived from amount vs
 | amount_paid, plus an optional due date. Individual payments live in
 | expense_payments so partial payments accumulate and stay auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_paid', 18, 2)->default(0)->after('currency');
            $table->timestamp('paid_at')->nullable()->after('reimbursed_at'); // set when fully paid
            $table->date('due_date')->nullable()->after('expense_date');
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('paid_on');
            $table->string('method', 30)->nullable();     // App\Modules\ExpenseTracker\Enums\PaymentMethod
            $table->string('reference')->nullable();       // cheque #, txn id, etc.
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'expense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'due_date']);
            $table->dropColumn(['amount_paid', 'paid_at', 'due_date']);
        });
    }
};
