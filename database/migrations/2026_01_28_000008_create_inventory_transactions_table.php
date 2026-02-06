<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index(); // receive|issue|adjust|return
            $table->decimal('qty', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->nullableMorphs('reference'); // reference_type, reference_id
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
