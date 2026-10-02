<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItemOption extends Model
{
    protected $fillable = ['inventory_item_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function values()
    {
        return $this->hasMany(InventoryItemOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
