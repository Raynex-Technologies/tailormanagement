<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 30)->index();
            $table->decimal('default_selling_price', 14, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('requires_measurements')->default(false);
            $table->string('quantity_behavior', 30)->index();
            $table->boolean('available_all_branches')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('order_catalog_item_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['order_catalog_item_id', 'branch_id'], 'order_catalog_item_branch_unique');
            $table->index(['branch_id', 'order_catalog_item_id'], 'order_catalog_branch_item_idx');
        });

        Schema::create('order_package_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('available_all_branches')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('order_package_template_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['order_package_template_id', 'branch_id'], 'order_package_template_branch_unique');
            $table->index(['branch_id', 'order_package_template_id'], 'order_package_branch_template_idx');
        });

        Schema::create('order_package_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_catalog_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('default_quantity', 14, 2);
            $table->decimal('minimum_quantity', 14, 2)->default(0);
            $table->decimal('maximum_quantity', 14, 2)->nullable();
            $table->decimal('package_unit_price', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['order_package_template_id', 'order_catalog_item_id'],
                'order_package_template_catalog_item_unique'
            );
            $table->unique(
                ['order_package_template_id', 'inventory_item_id'],
                'order_package_template_inventory_item_unique'
            );
            $table->index(['order_package_template_id', 'sort_order'], 'order_package_template_sort_idx');
        });

        Schema::create('order_package_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_package_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('source_template_revision');
            $table->string('package_name');
            $table->text('package_description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->decimal('original_package_total', 14, 2);
            $table->decimal('configured_package_total', 14, 2);
            $table->json('component_snapshot');
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'order_package_instances_order_created_idx');
            $table->index(
                ['order_package_template_id', 'source_template_revision'],
                'order_package_instances_source_revision_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_package_instances');
        Schema::dropIfExists('order_package_template_items');
        Schema::dropIfExists('order_package_template_branch');
        Schema::dropIfExists('order_package_templates');
        Schema::dropIfExists('order_catalog_item_branch');
        Schema::dropIfExists('order_catalog_items');
    }
};
