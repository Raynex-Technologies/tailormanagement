<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use App\Support\StorefrontMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'phone',
        'alternate_phone',
        'email',
        'tin_number',
        'address',
        'logo_path',
        'ui_primary_color',
        'ui_secondary_color_1',
        'ui_secondary_color_2',
        'allow_order_dates_flexibility',
        'invoice_template_id',
        'email_from_name',
        'email_from_address',
        'email_reply_to',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_timeout',
        'incoming_enabled',
        'incoming_protocol',
        'incoming_host',
        'incoming_port',
        'incoming_username',
        'incoming_password',
        'incoming_encryption',
        'tax_enabled',
        'tax_name',
        'tax_rate',
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
        'storefront_default_branch_id',
    ];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'allow_order_dates_flexibility' => 'boolean',
            'tax_rate' => 'decimal:2',
            'storefront_enabled' => 'boolean',
            'storefront_catalog_mode' => 'boolean',
            'custom_order_portal_enabled' => 'boolean',
            'guest_checkout_enabled' => 'boolean',
            'allow_cash_on_delivery' => 'boolean',
            'storefront_announcement_bar_enabled' => 'boolean',
            'mail_port' => 'integer',
            'mail_timeout' => 'integer',
            'incoming_enabled' => 'boolean',
            'incoming_port' => 'integer',
            'mail_password' => 'encrypted',
            'incoming_password' => 'encrypted',
        ];
    }

    public function invoiceTemplate(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class);
    }

    public function storefrontDefaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'storefront_default_branch_id');
    }

    /**
     * Singleton settings row.
     */
    public static function instance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'business_name' => config('app.name', 'Tailoring Business'),
                'tax_enabled' => false,
                'allow_order_dates_flexibility' => false,
                'tax_name' => 'VAT',
                'tax_rate' => 0,
                'storefront_enabled' => false,
                'storefront_catalog_mode' => false,
                'custom_order_portal_enabled' => true,
                'guest_checkout_enabled' => true,
                'allow_cash_on_delivery' => false,
                'storefront_currency' => 'TZS',
            ]
        );
    }

    public function getLogoUrlAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicUrl($this->logo_path);
    }

    public function getLogoFilePathAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicPath($this->logo_path);
    }

    public function getStorefrontLogoUrlAttribute(): ?string
    {
        return StorefrontMedia::url($this->storefront_logo_path);
    }

    public function getStorefrontFaviconUrlAttribute(): ?string
    {
        return StorefrontMedia::url($this->storefront_favicon_path);
    }

    public function getStorefrontHeroMediaUrlAttribute(): ?string
    {
        return StorefrontMedia::url($this->storefront_hero_media_path);
    }

    public function getStorefrontSocialImageUrlAttribute(): ?string
    {
        return StorefrontMedia::url($this->storefront_social_image_path);
    }

    public function setStorefrontLogoPathAttribute(mixed $value): void
    {
        $this->attributes['storefront_logo_path'] = StorefrontMedia::normalizePath($value);
    }

    public function setStorefrontFaviconPathAttribute(mixed $value): void
    {
        $this->attributes['storefront_favicon_path'] = StorefrontMedia::normalizePath($value);
    }

    public function setStorefrontHeroMediaPathAttribute(mixed $value): void
    {
        $this->attributes['storefront_hero_media_path'] = StorefrontMedia::normalizePath($value);
    }

    public function setStorefrontSocialImagePathAttribute(mixed $value): void
    {
        $this->attributes['storefront_social_image_path'] = StorefrontMedia::normalizePath($value);
    }

    public function setLogoPathAttribute(mixed $value): void
    {
        $this->attributes['logo_path'] = app(ImageUploadService::class)->normalizePublicPath($value);
    }

    public function isStorefrontOpen(): bool
    {
        return (bool) $this->storefront_enabled;
    }
}
