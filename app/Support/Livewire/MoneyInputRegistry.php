<?php

namespace App\Support\Livewire;

final class MoneyInputRegistry
{
    /**
     * @return list<string>
     */
    public static function fieldsFor(string $component): array
    {
        return match ($component) {
            \App\Livewire\Orders\Form::class => [
                'lines.*.unit_price',
                'discount',
                'order_expenses.*.amount',
                'deposit_amount',
            ],
            \App\Livewire\Orders\Payments\Panel::class => ['amount'],
            \App\Livewire\Orders\Show::class => ['customRequestedPaymentAmount'],
            \App\Livewire\OrderCatalog\ItemForm::class => ['defaultSellingPrice'],
            \App\Livewire\OrderCatalog\PackageForm::class => ['components.*.package_unit_price'],
            \App\Livewire\Expenses\Form::class => ['amount'],
            \App\Livewire\Inventory\Items\Index::class => [
                'default_buy_price',
                'default_sell_price',
                'receiveUnitCost',
            ],
            \App\Livewire\Inventory\Stock\Index::class => ['receiveUnitCost'],
            \App\Livewire\Invoices\Show::class => [
                'discount',
                'lines.*.unit_price',
            ],
            \App\Livewire\Payments\Index::class => ['editingAmount'],
            \App\Livewire\Pos\PosTerminal::class => [
                'discountAmount',
                'taxAmount',
                'amountPaid',
            ],
            \App\Livewire\Procurement\Requests\Form::class => ['items.*.unit_price_est'],
            \App\Livewire\Procurement\Requests\Show::class => ['reviewedItems.*.unit_price_est'],
            \App\Livewire\Procurement\Receiving\Show::class => ['receivingItems.*.unit_cost'],
            \App\Livewire\Capital\Create::class => ['initialAmount'],
            \App\Livewire\Installments\Packages\Index::class => ['price'],
            \App\Livewire\Installments\Plans\Form::class => ['package_price'],
            \App\Livewire\Installments\Plans\Show::class => ['paymentAmount'],
            \App\Livewire\Storefront\Admin\ShippingManager::class => [
                'methodAmount',
                'methodMinSubtotal',
                'methodMaxSubtotal',
                'methodFreeShippingThreshold',
                'profileHandlingFee',
            ],
            \App\Livewire\Storefront\Admin\ProductManager::class => [
                'productPrice',
                'productCompareAtPrice',
                'comboPrice',
                'couponMinSubtotal',
                'couponMaxDiscountAmount',
            ],
            \App\Livewire\Storefront\Admin\ProductForm::class => [
                'productPrice',
                'productCompareAtPrice',
            ],
            default => [],
        };
    }
}
