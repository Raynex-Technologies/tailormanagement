<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_no', 50)->unique();
            $table->foreignId('accountant_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable()->index();
            $table->decimal('initial_amount', 14, 2);
            $table->decimal('spent_amount', 14, 2)->default(0);
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->string('status', 50)->default('open')->index(); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('carried_over_to_id')->nullable()->constrained('capital_allocations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_allocations');
    }
};
