<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('qty_on_hand', 14, 2)->default(0);
            $table->decimal('qty_reserved', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
