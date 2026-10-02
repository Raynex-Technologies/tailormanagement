<?php

namespace App\Livewire\Inventory\Items;

use App\Services\Inventory\VariantAdministrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Variations extends Component
{
    #[Locked]
    public int $itemId;

    public string $section = 'overview';

    public ?int $movementVariant = null;

    public string $editor = '';

    public ?int $editingId = null;

    public ?int $optionId = null;

    public string $name = '';

    public int $position = 0;

    public bool $active = true;

    public array $choices = [];

    public string $sku = '';

    public ?string $price = null;

    public ?string $cost = null;

    public string $barcode = '';

    public string $quantity = '1';

    #[Locked]
    public string $currentQuantity = '0';

    public string $note = '';

    public array $allocations = [];

    #[Locked]
    public string $sourceQuantity = '0';

    public function mount(int $item): void
    {
        $this->itemId = $item;
        app(VariantAdministrationService::class)->inspect($item);
    }

    public function show(string $section): void
    {
        abort_unless(in_array($section, ['overview', 'options', 'combinations', 'pricing', 'stock', 'movements']), 404);
        $this->section = $section;
        $this->editor = '';
        $this->resetValidation();
    }

    public function open(string $editor, ?int $id = null, ?int $optionId = null): void
    {
        $service = app(VariantAdministrationService::class);
        $item = $service->inspect($this->itemId);
        abort_unless(in_array($editor, ['option', 'value', 'combination', 'identity', 'receive', 'adjust', 'allocation']), 404);
        $this->resetValidation();
        $this->editor = $editor;
        $this->editingId = $id;
        $this->optionId = $optionId;
        $this->name = '';
        $this->position = 0;
        $this->active = true;
        $this->sku = '';
        $this->price = null;
        $this->cost = null;
        $this->barcode = '';
        $this->choices = [];
        $this->quantity = '1';
        $this->note = '';
        if ($editor === 'combination') {
            $this->choices = $item->options()->where('is_active', true)->get()->mapWithKeys(fn ($option) => ['option_'.$option->id => ''])->all();
        }
        if ($editor === 'option' && $id) {
            $row = $item->options()->findOrFail($id);
            $this->name = $row->name;
            $this->position = $row->sort_order;
            $this->active = $row->is_active;
        }
        if ($editor === 'value' && $id) {
            $row = $item->options()->findOrFail($optionId)->values()->findOrFail($id);
            $this->name = $row->name;
            $this->position = $row->sort_order;
            $this->active = $row->is_active;
        }
        if (in_array($editor, ['identity', 'receive', 'adjust'])) {
            $variant = $item->variants()->findOrFail($id);
            $unit = $variant->stockUnit()->firstOrFail();
            $this->currentQuantity = (string) ($unit->stock?->qty_on_hand ?? 0);
            $this->name = $variant->display_name;
            $this->sku = $unit->sku;
            $this->price = $unit->selling_price;
            $this->cost = $unit->reference_cost;
            $this->barcode = $unit->primaryBarcode?->barcode ?? '';
        }
        if ($editor === 'allocation') {
            $this->sourceQuantity = (string) ($item->simpleStockUnit?->stock?->qty_on_hand ?? '0');
            $this->allocations = $item->variants()->where('is_active', true)->pluck('id')->mapWithKeys(fn ($id) => ['variant_'.$id => '0'])->all();
        }
    }

    public function save(): void
    {
        $s = app(VariantAdministrationService::class);
        match ($this->editor) {
            'option' => $s->option($this->itemId, $this->editingId, $this->name, $this->position, $this->active),
            'value' => $s->value($this->itemId, $this->optionId, $this->editingId, $this->name, $this->position, $this->active),
            'combination' => $s->combination($this->itemId, array_values($this->choices), $this->sku, $this->editingId),
            'identity' => $s->identity($this->itemId, $this->editingId, $this->sku, $this->price === '' ? null : $this->price, $this->cost === '' ? null : $this->cost, $this->barcode),
            'receive','adjust' => $s->stock($this->itemId, $this->editingId, $this->editor, $this->quantity, $this->cost === '' ? null : $this->cost, $this->note),
            'allocation' => $s->convert($this->itemId, collect($this->allocations)->mapWithKeys(fn ($qty, $key) => [(int) str_replace('variant_', '', $key) => $qty])->all(), $this->sourceQuantity),
            default => abort(404),
        };
        $this->editor = '';
        session()->flash('success', 'Changes saved.');
    }

    public function deleteValue(int $id): void
    {
        app(VariantAdministrationService::class)->deleteValue($this->itemId, $id);
    }

    public function deleteOption(int $id): void
    {
        app(VariantAdministrationService::class)->deleteOption($this->itemId, $id);
    }

    public function setActive(int $id, bool $active): void
    {
        app(VariantAdministrationService::class)->active($this->itemId, $id, $active);
    }

    public function viewMovements(int $id): void
    {
        app(VariantAdministrationService::class)->inspect($this->itemId)->variants()->findOrFail($id);
        $this->movementVariant = $id;
        $this->show('movements');
    }

    public function render()
    {
        $item = app(VariantAdministrationService::class)->inspect($this->itemId);
        $item->load(['options.values', 'variants.selectedValues.option', 'variants.stockUnit.stock', 'variants.stockUnit.primaryBarcode', 'simpleStockUnit.stock']);
        $variants = $item->variants;
        $activeVariants = $variants->where('is_active', true);
        $total = $item->variant_mode === 'simple' ? (string) ($item->simpleStockUnit?->stock?->qty_on_hand ?? 0) : (string) $activeVariants->reduce(fn ($sum, $v) => $sum->plus($v->stockUnit?->stock?->qty_on_hand ?? 0), \Brick\Math\BigDecimal::zero());
        $groups = [];
        $groupOption = $item->options->first();
        if ($groupOption) {
            foreach ($groupOption->values as $value) {
                $rows = $activeVariants->filter(fn ($v) => $v->selectedValues->contains('id', $value->id));
                if ($rows->isNotEmpty()) {
                    $groups[] = ['name' => $value->name, 'rows' => $rows, 'total' => (string) $rows->reduce(fn ($sum, $v) => $sum->plus($v->stockUnit?->stock?->qty_on_hand ?? 0), \Brick\Math\BigDecimal::zero())];
                }
            }
        }
        $prices = $activeVariants->map(fn ($v) => $v->stockUnit?->selling_price)->filter(fn ($p) => $p !== null);
        $low = $activeVariants->filter(fn ($v) => $v->stockUnit?->allocation_status === 'ready' && \Brick\Math\BigDecimal::of($v->stockUnit?->stock?->qty_on_hand ?? 0)->isLessThanOrEqualTo($item->reorder_level ?? 0))->count();
        $movements = $this->section === 'movements' ? $item->transactions()->when($this->movementVariant, fn ($q) => $q->where('inventory_stock_unit_id', $item->variants->firstWhere('id', $this->movementVariant)?->stockUnit?->id ?? 0))->with('stockUnit.variant.selectedValues.option')->latest('id')->limit(100)->get() : collect();

        return view('livewire.inventory.items.variations', compact('item', 'variants', 'activeVariants', 'total', 'groups', 'groupOption', 'prices', 'low', 'movements'));
    }
}
