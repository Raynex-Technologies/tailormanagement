<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('twilio_whatsapp_configs')) {
            return;
        }

        Schema::create('twilio_whatsapp_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('account_sid', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('from_number', 50)->nullable();
            $table->string('messaging_service_sid', 255)->nullable();
            $table->string('content_template_language', 10)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intentionally no-op: database safety rules prohibit dropping stored settings data.
    }
};
