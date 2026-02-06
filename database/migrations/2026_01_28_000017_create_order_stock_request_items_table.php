<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_stock_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_requested', 14, 2);
            $table->decimal('qty_approved', 14, 2)->nullable();
            $table->decimal('qty_issued', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['order_stock_request_id', 'inventory_item_id'], 'osr_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stock_request_items');
    }
};
