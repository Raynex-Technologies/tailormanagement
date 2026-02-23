<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_payments')) {
            return;
        }

        if (! Schema::hasColumn('order_payments', 'payment_method_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->foreignId('payment_method_id')
                    ->nullable()
                    ->after('amount')
                    ->constrained('payment_methods')
                    ->nullOnDelete();
            });
        }

        DB::table('order_payments')
            ->whereNull('payment_method_id')
            ->update(['payment_method_id' => 1]);

        if (Schema::hasColumn('order_payments', 'method')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropColumn('method');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_payments')) {
            return;
        }

        if (! Schema::hasColumn('order_payments', 'method')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->string('method', 50)->default('default')->after('amount');
            });
        }

        DB::table('order_payments')->update(['method' => 'default']);

        if (Schema::hasColumn('order_payments', 'payment_method_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }
    }
};
