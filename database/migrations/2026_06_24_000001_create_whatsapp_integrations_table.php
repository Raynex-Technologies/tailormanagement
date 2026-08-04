<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('webhook_key')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('waba_id')->nullable()->unique();
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('meta_app_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('webhook_verify_token')->nullable();
            $table->string('connection_status', 32)->default('not_configured');
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_integrations');
    }
};
