<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            $table->string('twilio_account_sid', 34)->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('twilio_from', 32)->nullable()->unique();
        });
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('twilio_content_sid', 34)->nullable()->index();
            $table->string('twilio_account_sid', 34)->nullable();
            $table->string('twilio_content_fingerprint', 64)->nullable();
            $table->string('twilio_status', 64)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Twilio credentials and template provenance require a reviewed rollback.');
    }
};
