<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'cms_page_id',
        'label',
        'url',
        'route_name',
        'target',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function cmsPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class);
    }

    public function getResolvedUrlAttribute(): string
    {
        if (filled($this->url)) {
            return $this->url;
        }

        if (filled($this->route_name) && \Route::has($this->route_name)) {
            return route($this->route_name);
        }

        if ($this->cmsPage) {
            return route('storefront.page', $this->cmsPage->slug);
        }

        return '#';
    }
}
