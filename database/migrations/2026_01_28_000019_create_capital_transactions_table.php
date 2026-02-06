<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_allocation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index(); // debit|credit|adjustment|closing
            $table->decimal('amount', 14, 2);
            $table->nullableMorphs('reference'); // reference_type, reference_id
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['capital_allocation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_transactions');
    }
};
