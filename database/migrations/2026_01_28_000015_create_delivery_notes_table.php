<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_note_no', 50)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_by_name')->nullable();
            $table->string('received_by_phone', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
