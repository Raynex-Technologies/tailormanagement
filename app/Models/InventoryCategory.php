<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryCategory extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
