<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryStockUnit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockUnitBackfillService
{
    public function run(bool $dryRun = true): array
    {
        $report = ['dry_run' => $dryRun, 'items_checked' => 0, 'simple_units_prepared' => 0, 'variant_units_prepared' => 0,
            'simple_products_processed' => 0, 'variant_products_detected' => 0, 'records_skipped' => 0, 'sku_conflicts' => 0, 'barcode_conflicts' => 0,
            'allocation_review_items' => 0, 'balances_changed' => 0, 'problems' => []];
        // Preflight all source identities before creating any unit; no first-wins SKU renaming.
        $owners = [];
        foreach (DB::table('inventory_items')->get() as $item) {
            if ($item->variant_mode === 'simple') {
                $owners[mb_strtolower(trim($item->sku))][] = 'item:'.$item->id;
            }
        }
        foreach (DB::table('inventory_item_variants')->get() as $variant) {
            $owners[mb_strtolower(trim((string) $variant->sku))][] = 'variant:'.$variant->id;
        }
        foreach (DB::table('inventory_stock_units')->get() as $unit) {
            $owners[mb_strtolower(trim($unit->sku))][] = $unit->inventory_item_variant_id ? 'variant:'.$unit->inventory_item_variant_id : 'item:'.$unit->inventory_item_id;
        }
        foreach (InventoryItem::withoutBranchScope()->orderBy('id')->cursor() as $item) {
            $report['items_checked']++;
            if ($item->variant_mode === 'variants' && $item->stock_identity_status === 'ready') {
                $report['variant_products_detected']++;

                continue;
            }
            if ($item->variant_mode === 'simple') {
                $report['simple_products_processed']++;
            }
            if ($item->variant_mode === 'variants' || $item->variants()->exists()) {
                $report['variant_products_detected']++;
            }
            $before = $report;
            try {
                DB::transaction(function () use ($item, $owners, $dryRun, &$report) {
                    $item = InventoryItem::withoutBranchScope()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                    $variants = $item->variants()->orderBy('id')->lockForUpdate()->get();
                    $stocks = InventoryStock::withoutBranchScope()->where('inventory_item_id', $item->id)->lockForUpdate()->get();
                    if ($stocks->count() > 1) {
                        $this->fail('Multiple parent balances require review.');
                    }
                    $stock = $stocks->first();
                    if ($stock) {
                        $this->validateBalance($item, $stock);
                    }
                    // Unknown combinations are evidence, not an invitation to pick a row.
                    if ($variants->groupBy(fn ($v) => VariantSynchronizationService::combination($v->toArray()))->contains(fn ($rows) => $rows->count() > 1)) {
                        $this->fail('Duplicate variant combinations require review.');
                    }
                    $candidates = [];
                    if ($item->variant_mode === 'simple') {
                        $candidates[] = [null, $item->sku, $item->default_sell_price];
                    }
                    foreach ($variants as $variant) {
                        $price = $item->default_sell_price === null ? null : (string) BigDecimal::of($item->default_sell_price)->plus($variant->price_delta);
                        $candidates[] = [$variant, $variant->sku, $price];
                    }
                    foreach ($candidates as [$variant, $sku, $price]) {
                        $key = mb_strtolower(trim((string) $sku));
                        if ($key === '' || strlen((string) $sku) > 100) {
                            $this->fail('Missing or unusable SKU; no replacement generated.');
                        }
                        if (count(array_unique($owners[$key] ?? [])) > 1) {
                            $this->fail('SKU conflict: '.$sku.'. No identity was renamed.');
                        }
                        if ($price !== null && BigDecimal::of($price)->isNegative()) {
                            $this->fail('Negative effective selling price requires review.');
                        }
                    }
                    foreach ($candidates as [$variant, $sku, $price]) {
                        $existing = InventoryStockUnit::where('inventory_item_id', $item->id)->where('inventory_item_variant_id', $variant?->id)->first();
                        if (! $existing) {
                            if ($variant) {
                                $report['variant_units_prepared']++;
                            } else {
                                $report['simple_units_prepared']++;
                            }
                        }
                        if ($dryRun) {
                            continue;
                        }
                        $unit = $existing ?? InventoryStockUnit::create([
                            'inventory_item_id' => $item->id, 'inventory_item_variant_id' => $variant?->id,
                            'sku' => $sku, 'selling_price' => $price, 'reference_cost' => $item->default_buy_price,
                            'is_active' => $variant ? false : $item->is_active,
                            'allocation_status' => $variant ? 'allocation_required' : 'ready',
                        ]);
                        // Existing explicit variant prices are never recalculated on rerun.
                        if (! $variant && $stock) {
                            $this->attachBalance($item, $unit, $stock);
                        }
                    }
                    if ($variants->isNotEmpty() || $item->variant_mode === 'variants') {
                        $report['allocation_review_items']++;
                    }
                    if (! $dryRun) {
                        $status = $item->variant_mode === 'variants' ? 'allocation_required' : ($variants->isEmpty() ? 'ready' : 'review_required');
                        DB::table('inventory_items')->where('id', $item->id)->update(['stock_identity_status' => $status]);
                    }
                });
            } catch (\Throwable $exception) {
                $report = $before;
                $report['records_skipped']++;
                $message = $exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage();
                if (str_contains($message, 'SKU conflict')) {
                    $report['sku_conflicts']++;
                }
                $report['problems'][] = ['item_id' => $item->id, 'sku' => $item->sku, 'message' => $exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage()];
            }
        }

        return $report;
    }

    /** New simple products only; never silently backfill an existing missing identity. */
    public function createSimpleIdentity(InventoryItem $item): InventoryStockUnit
    {
        return DB::transaction(function () use ($item) {
            $item = InventoryItem::withoutBranchScope()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->variant_mode !== 'simple') {
                $this->fail('An exact real variant is required.');
            }
            $unit = InventoryStockUnit::where('inventory_item_id', $item->id)->whereNull('inventory_item_variant_id')->first();
            if ($unit) {
                return $unit;
            }
            if (DB::table('inventory_item_variants')->whereRaw('LOWER(sku) = ?', [mb_strtolower($item->sku)])->exists()) {
                $this->fail('SKU conflicts with an existing variant.');
            }
            $unit = InventoryStockUnit::create(['inventory_item_id' => $item->id, 'sku' => $item->sku,
                'selling_price' => $item->default_sell_price, 'reference_cost' => $item->default_buy_price,
                'is_active' => $item->is_active, 'allocation_status' => 'ready']);
            DB::table('inventory_items')->where('id', $item->id)->update(['stock_identity_status' => 'ready']);

            return $unit;
        });
    }

    public function attachBalance(InventoryItem $item, InventoryStockUnit $unit, InventoryStock $stock): void
    {
        $this->validateBalance($item, $stock);
        if ($stock->inventory_stock_unit_id !== null && (int) $stock->inventory_stock_unit_id !== $unit->id) {
            $this->fail('Balance already belongs to another stock identity.');
        }
        if ($unit->inventory_item_variant_id || $unit->inventory_item_id !== $item->id) {
            $this->fail('Parent balance cannot be allocated to a variant automatically.');
        }
        if ($stock->inventory_stock_unit_id === null) {
            // Identity-only update. Preserve stock row ID and both quantities exactly.
            DB::table('inventory_stocks')->where('id', $stock->id)->update(['inventory_stock_unit_id' => $unit->id]);
            $stock->inventory_stock_unit_id = $unit->id;
        }
        if (! DB::table('inventory_stock_unit_baselines')->where('inventory_stock_unit_id', $unit->id)->exists()) {
            $net = BigDecimal::zero();
            $last = null;
            foreach (DB::table('inventory_transactions')->where('inventory_item_id', $item->id)->whereNull('inventory_stock_unit_id')->orderBy('id')->cursor() as $movement) {
                $net = $net->plus((string) $movement->qty);
                $last = $movement->id;
            }
            DB::table('inventory_stock_unit_baselines')->insert([
                'inventory_stock_unit_id' => $unit->id, 'inventory_stock_id' => $stock->id, 'branch_id' => $item->branch_id,
                'qty_on_hand' => $stock->qty_on_hand, 'qty_reserved' => $stock->qty_reserved,
                'legacy_movement_net' => (string) $net, 'last_legacy_transaction_id' => $last,
                'reason' => 'migration_cutover_snapshot', 'created_at' => now(),
            ]);
        }
    }

    public function validateBalance(InventoryItem $item, InventoryStock $stock): void
    {
        $onHand = BigDecimal::of($stock->qty_on_hand);
        $reserved = BigDecimal::of($stock->qty_reserved);
        if ((int) $stock->branch_id !== (int) $item->branch_id || $onHand->isNegative() || $reserved->isNegative() || $reserved->isGreaterThan($onHand)) {
            $this->fail('Invalid stock balance or branch mismatch; reconciliation required.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock_unit' => $message]);
    }
}
