<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('orders')) {
            return;
        }

        $rows = DB::table('invoices')
            ->join('orders', 'orders.id', '=', 'invoices.order_id')
            ->whereNotNull('orders.order_date')
            ->select('invoices.id as invoice_id', 'orders.order_date')
            ->get();

        foreach ($rows as $row) {
            DB::table('invoices')
                ->where('id', $row->invoice_id)
                ->update(['issue_date' => $row->order_date]);
        }
    }

    public function down(): void
    {
        // No-op: cannot reliably restore previous issue dates.
    }
};
