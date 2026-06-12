<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipments-OWNED tables, added to the shared `quakelogic_enterprise` database.
 * FKs reference existing Proposals-owned tables (organizations, users,
 * proposal_submissions) which already exist in the shared schema. This migration
 * is additive only — Shipments never drops or alters Proposals' tables, and
 * never runs migrate:fresh against the shared DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_mailings', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The mailed proposal this shipment belongs to (nullable: a mailing
            // can be logged before it is linked). nullOnDelete so removing a
            // proposal never deletes its delivery audit trail.
            $table->foreignId('proposal_submission_id')->nullable()
                ->constrained('proposal_submissions')->nullOnDelete();

            $table->string('carrier')->default('ups');
            $table->string('ups_tracking_number')->index();

            $table->string('recipient_name')->nullable();
            $table->text('recipient_address')->nullable();

            // The deadline the shipment is judged against (copied from the
            // proposal's due_date when linked, or entered manually).
            $table->date('deadline')->nullable()->index();

            $table->string('status')->default('label_created')->index();
            $table->date('scheduled_delivery')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by')->nullable();
            // Cached on-time result, set once delivered (null while in transit).
            $table->boolean('on_time')->nullable();
            $table->string('proof_url')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'ups_tracking_number']);
        });

        Schema::create('mailing_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_mailing_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('description');
            $table->string('location')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Idempotent polling: the same carrier event is never inserted twice.
            $table->unique(['proposal_mailing_id', 'code', 'occurred_at'], 'mailing_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_tracking_events');
        Schema::dropIfExists('proposal_mailings');
    }
};
