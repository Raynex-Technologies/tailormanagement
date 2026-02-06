<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_on_comment')->default(true);
            $table->boolean('notify_on_status_change')->default(true);
            $table->timestamps();

            $table->unique(['order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_watchers');
    }
};
