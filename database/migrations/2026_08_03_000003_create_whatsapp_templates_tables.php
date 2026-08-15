<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('whatsapp_integration_id')->constrained()->cascadeOnDelete();
            $t->string('meta_template_id')->nullable()->unique();
            $t->string('name', 512);
            $t->string('language', 16);
            $t->string('category', 32);
            $t->string('local_state', 32)->default('draft');
            $t->string('meta_status', 64)->nullable();
            $t->string('meta_quality', 64)->nullable();
            $t->json('components');
            $t->json('variable_mappings')->nullable();
            $t->json('validation_result')->nullable();
            $t->string('validation_fingerprint', 64)->nullable();
            $t->string('submission_fingerprint', 64)->nullable();
            $t->string('synced_fingerprint', 64)->nullable();
            $t->text('rejection_reason')->nullable();
            $t->json('meta_status_details')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamp('deleted_at_meta')->nullable();
            $t->timestamps();
            $t->unique(['whatsapp_integration_id', 'name', 'language']);
            $t->index(['branch_id', 'meta_status']);
        });
        Schema::create('whatsapp_template_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('whatsapp_template_id')->constrained()->cascadeOnDelete();
            $t->string('previous_status', 64)->nullable();
            $t->string('new_status', 64);
            $t->string('source', 32);
            $t->text('reason')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['whatsapp_template_id', 'occurred_at'], 'wa_template_history_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_status_histories');
        Schema::dropIfExists('whatsapp_templates');
    }
};
