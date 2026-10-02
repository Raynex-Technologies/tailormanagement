<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItemOptionValue extends Model
{
    protected $fillable = ['inventory_item_option_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function option()
    {
        return $this->belongsTo(InventoryItemOption::class, 'inventory_item_option_id');
    }
}
