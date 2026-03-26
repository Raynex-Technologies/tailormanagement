<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('inventory_categories')
                ->nullOnDelete();
            $table->text('description')->nullable()->after('slug');
            $table->boolean('storefront_is_visible')->default(false)->after('description')->index();
            $table->boolean('storefront_featured')->default(false)->after('storefront_is_visible')->index();
            $table->string('storefront_image_path')->nullable()->after('storefront_featured');
            $table->string('seo_title')->nullable()->after('storefront_image_path');
            $table->text('seo_description')->nullable()->after('seo_title');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('short_description')->nullable()->after('unit');
            $table->longText('full_description')->nullable()->after('short_description');
            $table->string('featured_image_path')->nullable()->after('full_description');
            $table->json('gallery_images')->nullable()->after('featured_image_path');
            $table->string('status', 20)->default('draft')->after('gallery_images')->index();
            $table->decimal('compare_at_price', 14, 2)->nullable()->after('default_sell_price');
            $table->boolean('track_stock')->default(true)->after('compare_at_price');
            $table->decimal('low_stock_threshold', 14, 2)->default(0)->after('reorder_level');
            $table->boolean('allow_backorders')->default(false)->after('low_stock_threshold');
            $table->boolean('storefront_is_visible')->default(false)->after('allow_backorders')->index();
            $table->boolean('is_featured')->default(false)->after('storefront_is_visible')->index();
            $table->decimal('weight', 10, 3)->nullable()->after('is_featured');
            $table->json('dimensions')->nullable()->after('weight');
            $table->unsignedBigInteger('shipping_profile_id')->nullable()->after('dimensions');
            $table->boolean('is_taxable')->default(false)->after('shipping_profile_id');
            $table->string('meta_title')->nullable()->after('is_taxable');
            $table->text('meta_description')->nullable()->after('meta_title');

            $table->unique(['branch_id', 'slug'], 'inventory_items_branch_slug_unique');
        });

        Schema::create('inventory_item_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['inventory_item_id', 'sort_order']);
        });

        Schema::create('inventory_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('sku')->nullable()->index();
            $table->decimal('price_delta', 14, 2)->default(0);
            $table->decimal('stock_qty', 14, 2)->nullable();
            $table->json('option_values')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'name'], 'inventory_item_variants_item_name_unique');
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('inventory_item_product_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'product_tag_id'], 'inventory_item_product_tag_unique');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('branch_id')
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::dropIfExists('inventory_item_product_tag');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('inventory_item_variants');
        Schema::dropIfExists('inventory_item_media');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique('inventory_items_branch_slug_unique');

            $table->dropColumn([
                'slug',
                'short_description',
                'full_description',
                'featured_image_path',
                'gallery_images',
                'status',
                'compare_at_price',
                'track_stock',
                'low_stock_threshold',
                'allow_backorders',
                'storefront_is_visible',
                'is_featured',
                'weight',
                'dimensions',
                'shipping_profile_id',
                'is_taxable',
                'meta_title',
                'meta_description',
            ]);
        });

        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');

            $table->dropColumn([
                'description',
                'storefront_is_visible',
                'storefront_featured',
                'storefront_image_path',
                'seo_title',
                'seo_description',
            ]);
        });
    }
};
