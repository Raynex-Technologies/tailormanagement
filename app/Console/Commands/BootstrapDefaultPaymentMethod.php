<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapDefaultPaymentMethod extends Command
{
    protected $signature = 'payments:bootstrap-default-method';

    protected $description = 'Ensure payment method #1 (Default) exists and backfill existing order payments to use it';

    public function handle(): int
    {
        if (! Schema::hasTable('payment_methods')) {
            $this->error('payment_methods table not found. Run migrations first.');

            return self::FAILURE;
        }

        DB::transaction(function () {
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

            if (Schema::hasTable('order_payments') && Schema::hasColumn('order_payments', 'payment_method_id')) {
                DB::table('order_payments')
                    ->whereNull('payment_method_id')
                    ->update(['payment_method_id' => 1]);
            }
        });

        $this->info('Default payment method ensured with id=1.');
        $this->info('Existing order payments without payment_method_id were backfilled to id=1.');

        return self::SUCCESS;
    }
}
