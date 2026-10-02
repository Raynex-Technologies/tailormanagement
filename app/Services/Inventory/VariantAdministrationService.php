<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemOption;
use App\Models\InventoryItemOptionValue;
use App\Models\InventoryItemVariant;
use App\Models\InventoryStock;
use App\Models\InventoryStockUnit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VariantAdministrationService
{
    public function inspect(int $itemId): InventoryItem
    {
        Gate::authorize('inventory.view');
        $item = InventoryItem::withoutBranchScope()->findOrFail($itemId);
        app(StockUnitResolver::class)->authorizeItem($item);

        return $item;
    }

    private function edit(int $itemId, callable $action): mixed
    {
        Gate::authorize('inventory.items.manage');

        return DB::transaction(function () use ($itemId, $action) {
            $item = InventoryItem::withoutBranchScope()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            app(StockUnitResolver::class)->authorizeItem($item);

            return $action($item);
        });
    }

    public function option(int $itemId, ?int $id, string $name, int $position = 0, bool $active = true): InventoryItemOption
    {
        return $this->edit($itemId, function ($item) use ($id, $name, $position, $active) {
            $name = trim($name);
            Validator::make(compact('name', 'position'), ['name' => 'required|string|max:100', 'position' => 'integer|min:0|max:10000'])->validate();
            if ($item->options()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                $this->fail('This option already exists.');
            }
            $option = $id ? $item->options()->findOrFail($id) : new InventoryItemOption(['inventory_item_id' => $item->id]);
            $option->fill(['name' => $name, 'sort_order' => $position, 'is_active' => $active])->save();

            return $option;
        });
    }

    public function value(int $itemId, int $optionId, ?int $id, string $name, int $position = 0, bool $active = true): InventoryItemOptionValue
    {
        return $this->edit($itemId, function ($item) use ($optionId, $id, $name, $position, $active) {
            $option = $item->options()->findOrFail($optionId);
            $name = trim($name);
            Validator::make(compact('name', 'position'), ['name' => 'required|string|max:100', 'position' => 'integer|min:0|max:10000'])->validate();
            if ($option->values()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                $this->fail('This value already exists.');
            }
            $value = $id ? $option->values()->findOrFail($id) : new InventoryItemOptionValue(['inventory_item_option_id' => $option->id]);
            $value->fill(['name' => $name, 'sort_order' => $position, 'is_active' => $active])->save();

            return $value;
        });
    }

    public function deleteValue(int $itemId, int $valueId): void
    {
        $this->edit($itemId, function ($item) use ($valueId) {
            $value = InventoryItemOptionValue::whereHas('option', fn ($q) => $q->where('inventory_item_id', $item->id))->findOrFail($valueId);
            $count = DB::table('inventory_item_variant_option_value')->where('inventory_item_option_value_id', $value->id)->count();
            if ($count) {
                $this->fail($value->name.' is used by '.$count.' product variations. Deactivate the value to preserve their identity.');
            }
            $value->delete();
        });
    }

    public function deleteOption(int $itemId, int $optionId): void
    {
        $this->edit($itemId, function ($item) use ($optionId) {
            $option = $item->options()->findOrFail($optionId);
            if (DB::table('inventory_item_variant_option_value')->whereIn('inventory_item_option_value_id', $option->values()->select('id'))->exists()) {
                $this->fail('This option is used by variations. Deactivate it instead.');
            }
            $option->values()->delete();
            $option->delete();
        });
    }

    public function combination(int $itemId, array $valueIds, string $sku, ?int $existingId = null): InventoryItemVariant
    {
        return $this->edit($itemId, function ($item) use ($valueIds, $sku, $existingId) {
            $ids = array_map('intval', $valueIds);
            sort($ids);
            if (! $ids || count($ids) !== count(array_unique($ids))) {
                $this->fail('Choose one value for each option.');
            }
            $options = $item->options()->where('is_active', true)->with('values')->get();
            $values = InventoryItemOptionValue::with('option')->whereIn('id', $ids)->where('is_active', true)->get();
            if ($values->count() !== count($ids) || $values->pluck('inventory_item_option_id')->sort()->values()->all() !== $options->pluck('id')->sort()->values()->all()) {
                $this->fail('Choose one active value from each of the active options on this product.');
            }
            $key = hash('sha256', implode(':', $ids));
            if ($item->variants()->where('combination_key', $key)->exists()) {
                $this->fail('This combination already exists. Reactivate it if retired.');
            }
            if ($existingId) {
                $variant = $item->variants()->whereNull('combination_key')->findOrFail($existingId);
                $variant->stockUnit()->firstOrFail();
                $variant->update(['combination_key' => $key]);
                $variant->selectedValues()->attach($ids);

                return $variant;
            }
            if ($item->variants()->whereNull('combination_key')->exists()) {
                $this->fail('Map existing variation values before adding new combinations. This preserves their identities.');
            }
            Validator::make(['sku' => $sku], ['sku' => 'required|string|max:100'])->validate();
            if (InventoryItemVariant::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()) {
                $this->fail('SKU already identifies another variation.');
            }
            $variant = $item->variants()->create(['name' => $values->sortBy(fn ($v) => $v->option->sort_order)->pluck('name')->implode(' / '), 'combination_key' => $key, 'sku' => $sku, 'price_delta' => 0, 'stock_qty' => null, 'is_active' => true]);
            $variant->selectedValues()->attach($ids);
            $ready = $item->variant_mode === 'variants' && $item->stock_identity_status === 'ready';
            $unit = InventoryStockUnit::create(['inventory_item_id' => $item->id, 'inventory_item_variant_id' => $variant->id, 'sku' => $sku, 'selling_price' => $item->default_sell_price, 'reference_cost' => $item->default_buy_price, 'is_active' => $ready, 'allocation_status' => $ready ? 'ready' : 'allocation_required']);
            if ($ready) {
                $this->zeroBalance($item, $unit);
            }

            return $variant;
        });
    }

    private function zeroBalance(InventoryItem $item, InventoryStockUnit $unit): InventoryStock
    {
        $stock = InventoryStock::create(['branch_id' => $item->branch_id, 'inventory_item_id' => $item->id, 'inventory_stock_unit_id' => $unit->id, 'qty_on_hand' => 0, 'qty_reserved' => 0]);
        DB::table('inventory_stock_unit_baselines')->insert(['inventory_stock_unit_id' => $unit->id, 'inventory_stock_id' => $stock->id, 'branch_id' => $item->branch_id, 'qty_on_hand' => 0, 'qty_reserved' => 0, 'legacy_movement_net' => 0, 'reason' => 'new_variation_zero_balance', 'created_at' => now()]);

        return $stock;
    }

    public function identity(int $itemId, int $variantId, string $sku, ?string $price, ?string $cost, ?string $barcode): void
    {
        $this->edit($itemId, function ($item) use ($variantId, $sku, $price, $cost, $barcode) {
            Validator::make(['sku' => $sku, 'price' => $price, 'cost' => $cost], ['sku' => 'required|string|max:100', 'price' => 'nullable|numeric|min:0|decimal:0,2', 'cost' => 'nullable|numeric|min:0|decimal:0,2'])->validate();
            $variant = $item->variants()->findOrFail($variantId);
            $unit = $variant->stockUnit()->firstOrFail();
            if (InventoryItemVariant::where('id', '!=', $variant->id)->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()) {
                $this->fail('SKU already identifies another variation.');
            }
            $unit->update(['sku' => $sku, 'selling_price' => $price, 'reference_cost' => $cost]);
            $variant->update(['sku' => $sku]);
            if ($barcode !== null && $barcode !== '') {
                app(StockUnitBarcodeService::class)->assign($unit, $barcode);
            }
        });
    }

    public function active(int $itemId, int $variantId, bool $active): void
    {
        $this->edit($itemId, function ($item) use ($variantId, $active) {
            $variant = $item->variants()->findOrFail($variantId);
            $unit = $variant->stockUnit()->lockForUpdate()->firstOrFail();
            $stock = $unit->stock()->lockForUpdate()->first();
            if (! $active && $stock && (BigDecimal::of($stock->qty_on_hand)->isPositive() || BigDecimal::of($stock->qty_reserved)->isPositive())) {
                $this->fail('This variation still has stock or reservations. Resolve its stock before retiring it.');
            }
            $variant->update(['is_active' => $active]);
            $unit->update(['is_active' => $active && $unit->allocation_status === 'ready']);
        });
    }

    public function stock(int $itemId, int $variantId, string $action, string $quantity, ?string $cost, string $note): void
    {
        if (! in_array($action, ['receive', 'adjust'], true)) {
            $this->fail('Choose Receive or Adjust.');
        }
        Gate::authorize('inventory.stock.'.$action);
        DB::transaction(function () use ($itemId, $variantId, $action, $quantity, $cost, $note) {
            $item = $this->inspect($itemId);
            $variant = $item->variants()->findOrFail($variantId);
            $unit = $variant->stockUnit()->firstOrFail();
            if (! $variant->is_active || ! $unit->is_active) {
                $this->fail('Reactivate this variation before changing stock.');
            }
            if ($action === 'receive') {
                app(StockMovementService::class)->receive($unit, $quantity, $cost, $note, auth()->user());
            } else {
                Validator::make(['note' => $note], ['note' => 'required|string|max:1000'])->validate();
                app(StockMovementService::class)->adjust($unit, $quantity, $note, auth()->user());
            }
        });
    }

    public function convert(int $itemId, array $allocations, string $expectedQuantity): void
    {
        Gate::authorize('inventory.stock.adjust');
        $this->edit($itemId, function ($item) use ($allocations, $expectedQuantity) {
            if ($item->variant_mode !== 'simple') {
                $this->fail('This product has already changed type. Reload the page.');
            }
            $source = app(StockUnitResolver::class)->forItem($item);
            $stock = $source->stock()->lockForUpdate()->firstOrFail();
            app(StockUnitBackfillService::class)->validateBalance($item, $stock);
            if (! BigDecimal::of($stock->qty_reserved)->isZero()) {
                $this->fail('Resolve existing reservations before enabling variations.');
            }
            // Outstanding simple issues can still be returned by legacy consumers; do not strand them.
            $outstanding = DB::table('inventory_transactions')->where('inventory_item_id', $item->id)->whereIn('type', ['issue', 'return'])->selectRaw('reference_type, reference_id, SUM(qty) AS net')->groupBy('reference_type', 'reference_id')->get();
            if ($outstanding->contains(fn ($row) => ! BigDecimal::of((string) $row->net)->isZero())) {
                $this->fail('Resolve outstanding simple-product issues/returns before conversion. Historical rows will remain unchanged.');
            }
            if (! BigDecimal::of($expectedQuantity)->isEqualTo($stock->qty_on_hand)) {
                $this->fail('Stock changed. Reload and review the allocation again.');
            }
            $variants = $item->variants()->with('stockUnit')->get();
            if ($variants->isEmpty() || $variants->contains(fn ($v) => ! $v->combination_key || ! $v->stockUnit)) {
                $this->fail('Configure real combinations first. Legacy combinations require explicit identity review.');
            }
            $active = $variants->where('is_active', true);
            $sum = BigDecimal::zero();
            $normalized = [];
            foreach ($active as $variant) {
                $qty = (string) ($allocations[$variant->id] ?? '0');
                Validator::make(['quantity' => $qty], ['quantity' => 'required|numeric|min:0|decimal:0,2'])->validate();
                $normalized[$variant->id] = (string) BigDecimal::of($qty)->toScale(2);
                $sum = $sum->plus($qty);
            }
            if (array_diff(array_map('intval', array_keys($allocations)), $active->pluck('id')->all())) {
                $this->fail('Allocation contains an unknown or retired variation.');
            }
            if ($active->isEmpty() || ! $sum->isEqualTo($stock->qty_on_hand)) {
                $this->fail('Allocate exactly the current physical stock before confirming.');
            }
            $record = DB::table('inventory_variant_allocations')->insertGetId(['inventory_item_id' => $item->id, 'source_stock_unit_id' => $source->id, 'created_by' => auth()->id(), 'quantity' => $stock->qty_on_hand, 'allocations' => json_encode($normalized), 'created_at' => now(), 'updated_at' => now()]);
            if ($sum->isPositive()) {
                app(StockMovementService::class)->adjust($source, (string) $sum->negated(), 'Variation allocation #'.$record, auth()->user());
            }
            $source->update(['is_active' => false, 'allocation_status' => 'retired']);
            // Reviewed transition boundary: only after locked, exact allocation and reservation checks.
            DB::table('inventory_items')->where('id', $item->id)->update(['variant_mode' => 'variants', 'stock_identity_status' => 'ready']);
            $item->refresh();
            foreach ($variants as $variant) {
                $unit = $variant->stockUnit;
                $unit->update(['is_active' => $variant->is_active, 'allocation_status' => 'ready']);
                $this->zeroBalance($item, $unit);
                $qty = $normalized[$variant->id] ?? '0';
                if (BigDecimal::of($qty)->isPositive()) {
                    app(StockMovementService::class)->adjust($unit, $qty, 'Variation allocation #'.$record, auth()->user());
                }
            }
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['variation' => $message]);
    }
}
