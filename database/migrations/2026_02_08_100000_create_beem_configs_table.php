<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beem_configs', function (Blueprint $table) {
            $table->id();
            $table->string('api_key', 255)->nullable();
            $table->string('secret_key', 255)->nullable();
            $table->string('sender_name', 50)->nullable();
            $table->boolean('sms_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beem_configs');
    }
};
