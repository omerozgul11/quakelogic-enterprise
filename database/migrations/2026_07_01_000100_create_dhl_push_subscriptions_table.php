<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_id')->nullable()->index(); // DHL's subscription UUID
            $table->string('type')->default('shipment');            // shipment | account
            $table->string('tracking_number')->nullable()->index();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending');           // pending | validating | ready | failed | removed
            $table->string('secret')->nullable();                   // validation token from DHL
            $table->string('callback_url')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhl_push_subscriptions');
    }
};
