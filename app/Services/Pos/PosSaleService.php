<?php

namespace App\Services\Pos;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\PosSale;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Services\Sms\SmsService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected SmsService $smsService
    ) {}

    /**
     * @param  array<int, array{inventory_item_id:int, quantity:numeric, discount_amount?:numeric}>  $cart
     */
    public function complete(
        array $cart,
        User $cashier,
        ?int $customerId,
        float $discountAmount,
        float $taxAmount,
        float $amountPaid,
        string $paymentMethod,
        ?string $paymentReference = null,
        ?string $notes = null
    ): PosSale {
        if ($cart === []) {
            throw ValidationException::withMessages([
                'cart' => ['Add at least one item before completing the sale.'],
            ]);
        }

        if (! in_array($paymentMethod, ['cash', 'mobile_money', 'card', 'bank_transfer', 'other'], true)) {
            throw ValidationException::withMessages([
                'paymentMethod' => ['Select a valid payment method.'],
            ]);
        }

        if ($paymentMethod !== 'cash' && blank($paymentReference)) {
            throw ValidationException::withMessages([
                'paymentReference' => ['Payment reference is required for non-cash payments.'],
            ]);
        }

        $branchId = BranchContext::getEffectiveBranchId();

        if ($customerId !== null) {
            Customer::query()->whereKey($customerId)->firstOrFail();
        }

        $sale = DB::transaction(function () use (
            $cart,
            $cashier,
            $customerId,
            $discountAmount,
            $taxAmount,
            $amountPaid,
            $paymentMethod,
            $paymentReference,
            $notes,
            $branchId
        ) {
            $normalizedItems = [];
            $subtotal = 0.0;

            foreach ($cart as $index => $line) {
                $itemId = (int) ($line['inventory_item_id'] ?? 0);
                $quantity = (float) ($line['quantity'] ?? 0);
                $lineDiscount = max(0, (float) ($line['discount_amount'] ?? 0));

                if ($itemId <= 0 || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        "cart.{$index}" => ['Each sale item must have a valid item and quantity.'],
                    ]);
                }

                $item = InventoryItem::query()
                    ->with('stock')
                    ->whereKey($itemId)
                    ->where('is_active', true)
                    ->firstOrFail();

                $available = (float) (($item->stock?->qty_on_hand ?? 0) - ($item->stock?->qty_reserved ?? 0));

                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["{$item->name} has only {$available} available."],
                    ]);
                }

                $unitPrice = (float) ($item->default_sell_price ?? 0);
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        'cart' => ["{$item->name} has an invalid selling price."],
                    ]);
                }

                $lineTotal = max(0, ($unitPrice * $quantity) - $lineDiscount);
                $subtotal += $lineTotal;

                $normalizedItems[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = max(0, $discountAmount);
            $taxAmount = max(0, $taxAmount);
            $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);

            if ($amountPaid < $totalAmount) {
                throw ValidationException::withMessages([
                    'amountPaid' => ['Amount paid must cover the total payable.'],
                ]);
            }

            $sale = PosSale::create([
                'branch_id' => $branchId,
                'sale_number' => $this->nextSaleNumber(),
                'receipt_token' => $this->newReceiptToken(),
                'customer_id' => $customerId,
                'user_id' => $cashier->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'change_amount' => max(0, $amountPaid - $totalAmount),
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference ? Str::limit(trim($paymentReference), 255, '') : null,
                'status' => 'completed',
                'notes' => $notes,
                'sold_at' => now(),
            ]);

            foreach ($normalizedItems as $line) {
                /** @var InventoryItem $item */
                $item = $line['item'];

                $sale->items()->create([
                    'inventory_item_id' => $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->sku,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                ]);

                $this->stockMovementService->issue(
                    item: $item,
                    qty: $line['quantity'],
                    note: "POS sale {$sale->sale_number}",
                    actor: $cashier,
                    reference: $sale
                );
            }

            return $sale->load(['items', 'customer', 'user']);
        });

        $this->sendSaleCompletedSms($sale, $cashier);

        return $sale;
    }

    protected function sendSaleCompletedSms(PosSale $sale, User $cashier): void
    {
        $sale->loadMissing(['items', 'customer', 'user']);

        if (! $sale->customer || blank($sale->customer->phone)) {
            return;
        }

        $settings = BusinessSetting::instance();

        $items = $sale->items
            ->map(fn ($item) => "{$item->item_name} x".rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.'))
            ->implode(', ');

        $this->smsService->sendTemplate(
            templateCode: 'pos_sale_completed',
            to: $sale->customer->phone,
            data: [
                'customer_name' => $sale->customer->name,
                'sale_number' => $sale->sale_number,
                'items' => $items,
                'subtotal' => money_tzs($sale->subtotal),
                'discount_amount' => money_tzs($sale->discount_amount),
                'tax_amount' => money_tzs($sale->tax_amount),
                'total_amount' => money_tzs($sale->total_amount),
                'amount_paid' => money_tzs($sale->amount_paid),
                'change_amount' => money_tzs($sale->change_amount),
                'payment_method' => Str::of((string) $sale->payment_method)->replace('_', ' ')->title()->toString(),
                'cashier_name' => $sale->user?->name ?? $cashier->name,
                'business_name' => $settings->business_name ?: config('app.name', 'TailorPro'),
            ],
            reference: $sale,
            actor: $cashier,
        );
    }

    protected function nextSaleNumber(): string
    {
        $year = now()->year;
        $pattern = "POS-{$year}-%";

        $maxSequence = DB::table('pos_sales')
            ->where('sale_number', 'like', $pattern)
            ->pluck('sale_number')
            ->map(function ($number) use ($year) {
                if (! is_string($number) || ! preg_match('/^POS-'.$year.'-(\d+)$/', $number, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        do {
            $maxSequence++;
            $candidate = sprintf('POS-%d-%06d', $year, $maxSequence);
        } while (DB::table('pos_sales')->where('sale_number', $candidate)->exists());

        return $candidate;
    }

    protected function newReceiptToken(): string
    {
        do {
            $token = Str::random(48);
        } while (DB::table('pos_sales')->where('receipt_token', $token)->exists());

        return $token;
    }
}
