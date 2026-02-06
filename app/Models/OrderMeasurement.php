<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_line_id',
        'measurements',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
        ];
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
