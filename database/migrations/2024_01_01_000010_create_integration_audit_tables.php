<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('disconnected');
            $table->json('config')->nullable()->comment('Non-sensitive config');
            $table->json('encrypted_credentials')->nullable()->comment('Encrypted tokens/keys');
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_error')->nullable();
            $table->json('sync_settings')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'type']);
        });

        Schema::create('sam_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users');
            $table->string('status')->default('pending');
            $table->json('query_params')->nullable();
            $table->integer('total_records')->nullable();
            $table->integer('imported_records')->default(0);
            $table->integer('updated_records')->default(0);
            $table->integer('skipped_records')->default(0);
            $table->integer('error_records')->default(0);
            $table->text('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sam_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sam_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('action')->comment('created|updated|skipped|error');
            $table->json('raw_data')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('bidprime_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users');
            $table->string('status')->default('pending');
            $table->json('query_params')->nullable();
            $table->integer('total_records')->nullable();
            $table->integer('imported_records')->default(0);
            $table->integer('updated_records')->default(0);
            $table->integer('skipped_records')->default(0);
            $table->text('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bidprime_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidprime_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('action')->comment('created|updated|skipped|error');
            $table->json('raw_data')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 1024);
            $table->json('events');
            $table->string('secret', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempt')->default(1);
            $table->boolean('success')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->morphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'event']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('type');
            $table->string('format');
            $table->json('params')->nullable();
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('bidprime_import_items');
        Schema::dropIfExists('bidprime_imports');
        Schema::dropIfExists('sam_import_items');
        Schema::dropIfExists('sam_imports');
        Schema::dropIfExists('integrations');
    }
};
