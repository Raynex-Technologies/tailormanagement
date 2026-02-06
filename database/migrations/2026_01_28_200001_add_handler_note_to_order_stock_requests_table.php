<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_stock_requests', function (Blueprint $table) {
            $table->text('handler_note')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('order_stock_requests', function (Blueprint $table) {
            $table->dropColumn('handler_note');
        });
    }
};
