<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name')->unique();
            $table->string('type', 30)->default('offline')->after('account_holder_name')->index();
            $table->boolean('is_enabled')->default(true)->after('type')->index();
            $table->boolean('is_online')->default(false)->after('is_enabled')->index();
            $table->unsignedInteger('sort_order')->default(0)->after('is_online');
            $table->text('description')->nullable()->after('sort_order');
            $table->json('settings')->nullable()->after('description');
        });

        $usedCodes = [];

        DB::table('payment_methods')
            ->orderBy('id')
            ->get()
            ->each(function (object $method) use (&$usedCodes): void {
                $baseCode = Str::slug((string) $method->name, '_');

                if ($baseCode === '') {
                    $baseCode = 'method_'.$method->id;
                }

                $code = $baseCode;
                $suffix = 2;

                while (in_array($code, $usedCodes, true)) {
                    $code = $baseCode.'_'.$suffix;
                    $suffix++;
                }

                $usedCodes[] = $code;

                DB::table('payment_methods')
                    ->where('id', $method->id)
                    ->update([
                        'code' => $code,
                        'type' => 'offline',
                        'is_enabled' => true,
                        'is_online' => false,
                    ]);
            });

        DB::table('payment_methods')
            ->where('id', 1)
            ->update([
                'code' => 'cash',
                'name' => DB::raw("CASE WHEN name = 'Default' THEN 'Cash' ELSE name END"),
                'type' => 'offline',
                'is_online' => false,
                'is_enabled' => true,
                'sort_order' => 0,
            ]);

        $pesapalId = DB::table('payment_methods')->where('code', 'pesapal')->value('id');

        if ($pesapalId === null) {
            DB::table('payment_methods')->insert([
                'name' => 'Pesapal',
                'code' => 'pesapal',
                'account_number' => null,
                'account_holder_name' => null,
                'type' => 'online',
                'is_enabled' => false,
                'is_online' => true,
                'sort_order' => 10,
                'description' => 'Online card and mobile money checkout via Pesapal.',
                'settings' => json_encode(['mode' => 'sandbox']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 50);
            $table->string('merchant_reference', 120)->unique();
            $table->string('gateway_reference', 191)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('status', 30)->default('initiated')->index();
            $table->string('purpose', 40)->default('storefront_order')->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 30)->default('tailoring')->after('order_no')->index();
            $table->string('order_source', 30)->nullable()->after('order_type')->index();
            $table->string('fulfillment_status', 30)->nullable()->after('status')->index();
            $table->char('currency', 3)->nullable()->after('total');
            $table->decimal('tax_total', 14, 2)->default(0)->after('currency');
            $table->decimal('shipping_total', 14, 2)->default(0)->after('tax_total');
            $table->decimal('discount_total', 14, 2)->default(0)->after('shipping_total');
            $table->decimal('grand_total', 14, 2)->nullable()->after('discount_total');
            $table->string('shipping_method_code')->nullable()->after('grand_total');
            $table->string('shipping_method_name')->nullable()->after('shipping_method_code');
            $table->json('shipping_address')->nullable()->after('shipping_method_name');
            $table->json('billing_address')->nullable()->after('shipping_address');
            $table->string('checkout_email')->nullable()->after('billing_address')->index();
            $table->string('checkout_phone', 50)->nullable()->after('checkout_email');
            $table->text('customer_note')->nullable()->after('checkout_phone');
            $table->timestamp('placed_at')->nullable()->after('customer_note');
            $table->timestamp('payment_due_at')->nullable()->after('placed_at');
            $table->timestamp('paid_at')->nullable()->after('payment_due_at');
        });

        $currency = DB::table('business_settings')->value('storefront_currency') ?: 'TZS';

        DB::table('orders')->update([
            'order_type' => 'tailoring',
            'currency' => $currency,
            'discount_total' => DB::raw('COALESCE(discount, 0)'),
            'grand_total' => DB::raw('COALESCE(total, 0)'),
        ]);

        Schema::table('order_lines', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_variant_id')->nullable()->after('inventory_item_id')->constrained('inventory_item_variants')->nullOnDelete();
            $table->string('sku')->nullable()->after('item_name')->index();
            $table->json('meta')->nullable()->after('notes');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')->nullable()->after('payment_method_id')->constrained()->nullOnDelete();
            $table->string('gateway')->nullable()->after('payment_transaction_id');
            $table->string('gateway_reference')->nullable()->after('gateway')->index();
            $table->string('status', 30)->default('captured')->after('gateway_reference');
            $table->json('raw_payload')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_transaction_id');
            $table->dropColumn(['gateway', 'gateway_reference', 'status', 'raw_payload']);
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropConstrainedForeignId('inventory_item_variant_id');
            $table->dropColumn(['sku', 'meta']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type',
                'order_source',
                'fulfillment_status',
                'currency',
                'tax_total',
                'shipping_total',
                'discount_total',
                'grand_total',
                'shipping_method_code',
                'shipping_method_name',
                'shipping_address',
                'billing_address',
                'checkout_email',
                'checkout_phone',
                'customer_note',
                'placed_at',
                'payment_due_at',
                'paid_at',
            ]);
        });

        Schema::dropIfExists('payment_transactions');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'type',
                'is_enabled',
                'is_online',
                'sort_order',
                'description',
                'settings',
            ]);
        });
    }
};
