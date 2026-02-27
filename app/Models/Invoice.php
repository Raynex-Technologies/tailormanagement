<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_no)) {
                $invoice->invoice_no = DocNumber::invoice();
            }

            if (empty($invoice->issue_date)) {
                $orderDate = null;
                if ($invoice->order_id) {
                    $orderDate = Order::query()->whereKey($invoice->order_id)->value('order_date');
                }
                $invoice->issue_date = $orderDate ?: now()->toDateString();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'order_id',
        'invoice_no',
        'issue_date',
        'due_date',
        'subtotal',
        'discount',
        'tax_amount',
        'total',
        'notes',
        'sent_at',
        'sent_to_email',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Rebuild invoice lines/totals from the source order.
     */
    public static function syncFromOrder(Order $order, ?int $actorId = null): self
    {
        $order->load('lines');

        $invoice = self::firstOrNew(['order_id' => $order->id]);
        $isNew = ! $invoice->exists;

        if ($isNew) {
            $invoice->branch_id = $order->branch_id;
            $invoice->created_by = $actorId;
        }

        $invoice->issue_date = $order->order_date
            ? $order->order_date->toDateString()
            : ($invoice->issue_date ?: now()->toDateString());
        $invoice->due_date = $order->due_date;
        $invoice->subtotal = $order->subtotal;
        $invoice->discount = $order->discount ?? 0;
        $invoice->tax_amount = 0;
        $invoice->total = $order->total;
        $invoice->updated_by = $actorId;
        $invoice->save();

        $keptLineIds = [];

        foreach ($order->lines as $orderLine) {
            $invoiceLine = InvoiceLine::firstOrNew([
                'invoice_id' => $invoice->id,
                'order_line_id' => $orderLine->id,
            ]);

            $invoiceLine->item_name = $orderLine->item_name;
            $invoiceLine->qty = $orderLine->qty;
            $invoiceLine->unit_price = $orderLine->unit_price;
            $invoiceLine->line_total = $orderLine->line_total;
            $invoiceLine->notes = $orderLine->notes;
            $invoiceLine->save();

            $keptLineIds[] = $invoiceLine->id;
        }

        if (! empty($keptLineIds)) {
            $invoice->lines()->whereNotIn('id', $keptLineIds)->delete();
        } else {
            $invoice->lines()->delete();
        }

        return $invoice->fresh(['lines', 'order.customer', 'branch']);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('invoice_no', 'like', "%{$term}%")
                ->orWhereHas('order', function ($oq) use ($term) {
                    $oq->where('order_no', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($cq) use ($term) {
                            $cq->where('name', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                });
        });
    }
}
