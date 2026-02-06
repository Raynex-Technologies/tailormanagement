<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('name')->index();
            $table->string('unit', 50)->default('pcs');
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->decimal('default_buy_price', 14, 2)->nullable();
            $table->decimal('default_sell_price', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
