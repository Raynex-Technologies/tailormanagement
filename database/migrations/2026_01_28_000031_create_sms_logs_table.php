<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('beem');
            $table->string('to', 50)->index();
            $table->text('message');
            $table->string('status', 50)->default('queued')->index(); // queued|sent|failed
            $table->string('provider_message_id', 100)->nullable()->index();
            $table->text('provider_response')->nullable();
            $table->nullableMorphs('reference'); // reference_type, reference_id
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
