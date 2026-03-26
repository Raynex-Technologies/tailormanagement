<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('storefront_enabled')->default(false)->after('tax_rate');
            $table->boolean('storefront_catalog_mode')->default(false)->after('storefront_enabled');
            $table->boolean('custom_order_portal_enabled')->default(true)->after('storefront_catalog_mode');
            $table->boolean('guest_checkout_enabled')->default(true)->after('custom_order_portal_enabled');
            $table->boolean('allow_cash_on_delivery')->default(false)->after('guest_checkout_enabled');
            $table->text('storefront_maintenance_message')->nullable()->after('allow_cash_on_delivery');
            $table->char('storefront_currency', 3)->default('TZS')->after('storefront_maintenance_message');
            $table->string('storefront_contact_email')->nullable()->after('storefront_currency');
            $table->string('storefront_contact_phone', 50)->nullable()->after('storefront_contact_email');
            $table->text('storefront_address')->nullable()->after('storefront_contact_phone');
            $table->string('storefront_logo_path')->nullable()->after('storefront_address');
            $table->string('storefront_favicon_path')->nullable()->after('storefront_logo_path');
            $table->string('storefront_hero_media_path')->nullable()->after('storefront_favicon_path');
            $table->string('storefront_seo_title')->nullable()->after('storefront_hero_media_path');
            $table->text('storefront_seo_description')->nullable()->after('storefront_seo_title');
            $table->string('storefront_social_image_path')->nullable()->after('storefront_seo_description');
            $table->boolean('storefront_announcement_bar_enabled')->default(false)->after('storefront_social_image_path');
            $table->string('storefront_announcement_text')->nullable()->after('storefront_announcement_bar_enabled');
            $table->string('storefront_announcement_link')->nullable()->after('storefront_announcement_text');
            $table->foreignId('storefront_default_branch_id')
                ->nullable()
                ->after('storefront_announcement_link')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_default_branch_id');

            $table->dropColumn([
                'storefront_enabled',
                'storefront_catalog_mode',
                'custom_order_portal_enabled',
                'guest_checkout_enabled',
                'allow_cash_on_delivery',
                'storefront_maintenance_message',
                'storefront_currency',
                'storefront_contact_email',
                'storefront_contact_phone',
                'storefront_address',
                'storefront_logo_path',
                'storefront_favicon_path',
                'storefront_hero_media_path',
                'storefront_seo_title',
                'storefront_seo_description',
                'storefront_social_image_path',
                'storefront_announcement_bar_enabled',
                'storefront_announcement_text',
                'storefront_announcement_link',
            ]);
        });
    }
};
