<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->json('measurements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_measurements');
    }
};
