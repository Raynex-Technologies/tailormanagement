<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garment_categories', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('description');
            $table->string('image_alt')->nullable()->after('image_path');
        });

        Schema::table('garment_option_groups', function (Blueprint $table): void {
            $table->unsignedInteger('minimum_selections')->default(0)->after('is_required');
            $table->unsignedInteger('maximum_selections')->nullable()->after('minimum_selections');
        });

        Schema::table('garment_options', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('description');
            $table->string('image_alt')->nullable()->after('image_path');
        });

        Schema::create('fabrics', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('composition')->nullable();
            $table->text('care_information')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('fabric_garment_category', function (Blueprint $table): void {
            $table->unsignedBigInteger('fabric_id');
            $table->unsignedBigInteger('garment_category_id');
            $table->timestamps();
            $table->primary(['fabric_id', 'garment_category_id']);
        });

        Schema::create('fabric_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fabric_id')->index();
            $table->string('name');
            $table->string('color_name')->nullable();
            $table->string('swatch_hex', 9)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->unsignedBigInteger('inventory_item_variant_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->unique(['fabric_id', 'name']);
        });

        Schema::table('online_booking_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('fabric_id')->nullable()->index()->after('garment_category_id');
            $table->unsignedBigInteger('fabric_variant_id')->nullable()->index()->after('fabric_id');
        });
    }

    public function down(): void
    {
        Schema::table('online_booking_items', function (Blueprint $table): void {
            $table->dropColumn(['fabric_id', 'fabric_variant_id']);
        });
        Schema::dropIfExists('fabric_variants');
        Schema::dropIfExists('fabric_garment_category');
        Schema::dropIfExists('fabrics');
        Schema::table('garment_options', fn (Blueprint $table) => $table->dropColumn(['image_path', 'image_alt']));
        Schema::table('garment_option_groups', fn (Blueprint $table) => $table->dropColumn(['minimum_selections', 'maximum_selections']));
        Schema::table('garment_categories', fn (Blueprint $table) => $table->dropColumn(['image_path', 'image_alt']));
    }
};
