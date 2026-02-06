<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->po_no)) {
                $po->po_no = DocNumber::purchaseOrder();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'po_no',
        'supplier_id',
        'purchase_request_id',
        'status',
        'subtotal',
        'total',
        'expected_date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'expected_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
