<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            $table->string('webhook_status', 32)->default('not_configured')->after('connection_status');
            $table->timestamp('webhook_checked_at')->nullable()->after('webhook_status');
            $table->text('webhook_error_message')->nullable()->after('webhook_checked_at');
        });
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32);
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_integration_id', 'phone']);
            $table->index(['branch_id', 'last_customer_message_at']);
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_message_id')->nullable()->unique();
            $table->string('direction', 16);
            $table->string('message_type', 32)->default('text');
            $table->string('phone', 32)->index();
            $table->text('body')->nullable();
            $table->string('status', 24)->index();
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->nullableMorphs('context');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('meta_timestamp')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'phone', 'created_at']);
        });
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_integration_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64)->unique();
            $table->string('event_type', 32)->default('notification');
            $table->json('payload');
            $table->timestamp('accepted_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            $table->dropColumn(['webhook_status', 'webhook_checked_at', 'webhook_error_message']);
        });
    }
};
