<?php

namespace App\Livewire\Concerns;

use App\Models\InventoryItem;
use App\Services\Orders\OrderInventorySelectionService;
use Livewire\Attributes\Locked;

trait SelectsOrderInventory
{
    public bool $showInventoryVariationSelector = false;

    #[Locked]
    public ?int $inventorySelectionItemId = null;

    #[Locked]
    public string $inventorySelectionTarget = 'direct';

    public array $inventoryOptionChoices = [];

    protected function inventorySelectionResolver(): \App\Services\Inventory\InventorySelectionService
    {
        return app(OrderInventorySelectionService::class);
    }

    protected function beginInventorySelection(int $itemId, string $target = 'direct'): void
    {
        $this->authorizeInventorySelection();
        $item = InventoryItem::withoutBranchScope()->where('branch_id', $this->inventorySelectionBranch())->where('is_active', true)->findOrFail($itemId);
        app(\App\Services\Inventory\StockUnitResolver::class)->authorizeItem($item);
        $this->inventorySelectionItemId = $item->id;
        $this->inventorySelectionTarget = $target;
        $this->inventoryOptionChoices = [];
        $this->resetErrorBag('inventory');
        if ($item->variant_mode === 'simple') {
            $this->acceptInventoryUnit($this->inventorySelectionResolver()->resolve($item->id, null, $this->inventorySelectionBranch()), $target);

            return;
        }
        $this->showInventoryVariationSelector = true;
    }

    public function inventorySelectionProduct(): ?InventoryItem
    {
        if (! $this->showInventoryVariationSelector || ! $this->inventorySelectionItemId) {
            return null;
        }

        return InventoryItem::withoutBranchScope()->where('branch_id', $this->inventorySelectionBranch())
            ->with(['options.values', 'variants.selectedValues.option', 'variants.stockUnit.stock'])->findOrFail($this->inventorySelectionItemId);
    }

    public function chooseInventoryOption(int $optionId, int $valueId): void
    {
        $this->authorizeInventorySelection();
        $product = $this->inventorySelectionProduct();
        $options = $product->options->where('is_active', true)->sortBy('sort_order');
        $option = $options->firstWhere('id', $optionId);
        abort_unless($option && $option->values->where('is_active', true)->contains('id', $valueId), 422);
        $clear = false;
        foreach ($options as $candidate) {
            $clear = $clear || $candidate->id === $optionId;
            if ($clear) {
                unset($this->inventoryOptionChoices['option_'.$candidate->id]);
            }
        }
        $this->inventoryOptionChoices['option_'.$optionId] = $valueId;
    }

    public function confirmInventoryVariation(): void
    {
        $this->authorizeInventorySelection();
        $product = $this->inventorySelectionProduct();
        $ids = collect($this->inventoryOptionChoices)->map(fn ($id) => (int) $id)->sort()->values()->all();
        $options = $product->options->where('is_active', true);
        if ($options->count() !== count($ids) || $options->contains(fn ($option) => ! $option->values->where('is_active', true)->contains('id', (int) ($this->inventoryOptionChoices['option_'.$option->id] ?? 0)))) {
            $this->addError('inventory', __('Choose an active value for each option.'));

            return;
        }
        $variants = $product->variants->filter(fn ($variant) => $variant->selectedValues->pluck('id')->sort()->values()->all() === $ids);
        if ($ids === [] || $variants->count() !== 1) {
            $this->addError('inventory', __('Choose one real, offered variation.'));

            return;
        }
        $variant = $variants->first();
        abort_unless($variant->stockUnit, 422);
        $unit = $this->inventorySelectionResolver()->resolve($product->id, $variant->stockUnit->id, $this->inventorySelectionBranch());
        $this->acceptInventoryUnit($unit, $this->inventorySelectionTarget);
        $this->showInventoryVariationSelector = false;
    }
}
