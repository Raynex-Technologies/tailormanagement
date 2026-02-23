<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('account_number', 100)->nullable();
            $table->string('account_holder_name', 191)->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_methods')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Default',
                'account_number' => null,
                'account_holder_name' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
