<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 100)->unique();
            $table->char('currency', 3)->default('TZS');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_variant_id')->nullable()->constrained('inventory_item_variants')->nullOnDelete();
            $table->string('line_key');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('compare_at_price', 14, 2)->nullable();
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'line_key'], 'cart_items_cart_line_key_unique');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 50)->nullable();
            $table->string('country', 3);
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 50)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default_shipping']);
            $table->index(['user_id', 'is_default_billing']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('countries')->nullable();
            $table->json('regions')->nullable();
            $table->json('postal_codes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 30)->default('flat_rate')->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('TZS');
            $table->decimal('min_subtotal', 14, 2)->nullable();
            $table->decimal('max_subtotal', 14, 2)->nullable();
            $table->decimal('min_weight', 14, 3)->nullable();
            $table->decimal('max_weight', 14, 3)->nullable();
            $table->unsignedInteger('min_items')->nullable();
            $table->unsignedInteger('max_items')->nullable();
            $table->decimal('free_shipping_threshold', 14, 2)->nullable();
            $table->string('estimated_delivery_window')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('handling_fee', 14, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shipping_profile_id', 'inventory_item_id'], 'shipping_profile_items_unique');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreign('shipping_profile_id')
                ->references('id')
                ->on('shipping_profiles')
                ->nullOnDelete();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('shipping_method_code')->nullable();
            $table->string('shipping_method_name')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->index();
            $table->string('title')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_customer_visible')->default(true)->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('custom_order_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('stage_key')->index();
            $table->string('stage_label');
            $table->text('note')->nullable();
            $table->boolean('is_customer_visible')->default(true)->index();
            $table->decimal('requested_payment_amount', 14, 2)->nullable();
            $table->string('requested_payment_note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_progress_updates');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('shipments');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['shipping_profile_id']);
        });

        Schema::dropIfExists('shipping_profile_items');
        Schema::dropIfExists('shipping_profiles');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
