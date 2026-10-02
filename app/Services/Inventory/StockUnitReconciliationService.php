<?php

namespace App\Services\Inventory;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockUnitReconciliationService
{
    public function inspect(): array
    {
        $result = ['summary' => array_fill_keys(['items_without_stock_units', 'duplicate_simple_units', 'variants_without_stock_units', 'orphan_stock_units', 'unit_variant_parent_mismatches', 'duplicate_stock_unit_skus', 'duplicate_barcodes', 'multiple_primary_barcodes', 'unit_branch_mismatches', 'unit_balance_link_errors', 'unit_balance_mismatches', 'unit_movement_identity_errors', 'items_requiring_allocation', 'legacy_variant_units_pending_review'], 0), 'issues' => []];
        if (! Schema::hasTable('inventory_stock_units')) {
            return ['summary' => ['stock_unit_schema_pending' => 1], 'issues' => []];
        }
        $flag = function ($key, $detail) use (&$result) {
            $result['summary'][$key]++;
            $result['issues'][] = ['type' => $key, ...$detail];
        };
        $items = DB::table('inventory_items')->get()->keyBy('id');
        $variants = DB::table('inventory_item_variants')->get()->keyBy('id');
        $units = DB::table('inventory_stock_units')->get()->keyBy('id');
        $stocks = DB::table('inventory_stocks')->get();
        $baselines = DB::table('inventory_stock_unit_baselines')->get()->keyBy('inventory_stock_unit_id');
        foreach ($items as $item) {
            $owned = $units->where('inventory_item_id', $item->id);
            $simple = $owned->whereNull('inventory_item_variant_id')->where('allocation_status', '!=', 'retired');
            if ($item->variant_mode === 'simple' && $simple->isEmpty()) {
                $flag('items_without_stock_units', ['item_id' => $item->id]);
            }
            if ($simple->count() > 1 || ($item->variant_mode === 'variants' && $simple->isNotEmpty())) {
                $flag('duplicate_simple_units', ['item_id' => $item->id]);
            }
            if (in_array($item->stock_identity_status, ['allocation_required', 'review_required'], true)) {
                $flag('items_requiring_allocation', ['item_id' => $item->id, 'mode' => $item->variant_mode, 'status' => $item->stock_identity_status]);
            }
        }
        foreach ($variants as $variant) {
            if (! $units->contains('inventory_item_variant_id', $variant->id)) {
                $flag('variants_without_stock_units', ['variant_id' => $variant->id, 'is_active' => (bool) $variant->is_active]);
            }
        }
        foreach ($units->groupBy(fn ($unit) => mb_strtolower(trim($unit->sku))) as $sku => $group) {
            if ($group->count() > 1) {
                $flag('duplicate_stock_unit_skus', ['sku' => $sku, 'ids' => $group->pluck('id')->all()]);
            }
        }
        $aliases = DB::table('inventory_stock_unit_barcodes')->get();
        foreach ($aliases->groupBy(fn ($alias) => mb_strtolower($alias->barcode)) as $barcode => $group) {
            if ($group->count() > 1) {
                $flag('duplicate_barcodes', ['barcode' => $barcode]);
            }
        }
        foreach ($aliases->where('is_primary', true)->groupBy('inventory_stock_unit_id') as $id => $group) {
            if ($group->count() > 1) {
                $flag('multiple_primary_barcodes', ['unit_id' => $id]);
            }
        }
        $newNet = [];
        foreach (DB::table('inventory_transactions')->whereNotNull('inventory_stock_unit_id')->orderBy('id')->cursor() as $movement) {
            $unit = $units->get($movement->inventory_stock_unit_id);
            $item = $unit ? $items->get($unit->inventory_item_id) : null;
            if (! $item || (int) $movement->inventory_item_id !== (int) $item->id || (int) $movement->branch_id !== (int) $item->branch_id) {
                $flag('unit_movement_identity_errors', ['transaction_id' => $movement->id]);
            }
            $newNet[$movement->inventory_stock_unit_id] = (string) BigDecimal::of($newNet[$movement->inventory_stock_unit_id] ?? '0')->plus((string) $movement->qty);
        }
        foreach ($units as $unit) {
            $item = $items->get($unit->inventory_item_id);
            $variant = $unit->inventory_item_variant_id ? $variants->get($unit->inventory_item_variant_id) : null;
            if (! $item || ($unit->inventory_item_variant_id && ! $variant)) {
                $flag('orphan_stock_units', ['unit_id' => $unit->id]);

                continue;
            }
            if ($variant && (int) $variant->inventory_item_id !== (int) $item->id) {
                $flag('unit_variant_parent_mismatches', ['unit_id' => $unit->id]);
            }
            $rows = $stocks->where('inventory_stock_unit_id', $unit->id);
            if ($variant && $unit->allocation_status !== 'ready') {
                $flag('legacy_variant_units_pending_review', ['unit_id' => $unit->id, 'variant_id' => $variant->id, 'legacy_stock_qty' => $variant->stock_qty]);
                if ($rows->isNotEmpty()) {
                    $flag('unit_balance_link_errors', ['unit_id' => $unit->id, 'reason' => 'Unallocated variant has a balance']);
                }

                continue;
            }
            if ($rows->count() !== 1) {
                $flag('unit_balance_link_errors', ['unit_id' => $unit->id, 'balance_count' => $rows->count()]);

                continue;
            }
            $stock = $rows->first();
            if ((int) $stock->inventory_item_id !== (int) $item->id) {
                $flag('unit_balance_link_errors', ['unit_id' => $unit->id, 'stock_id' => $stock->id]);
            }
            if ((int) $stock->branch_id !== (int) $item->branch_id) {
                $flag('unit_branch_mismatches', ['unit_id' => $unit->id, 'stock_id' => $stock->id]);
            }
            $baseline = $baselines->get($unit->id);
            if (! $baseline || (int) $baseline->inventory_stock_id !== (int) $stock->id || (int) $baseline->branch_id !== (int) $stock->branch_id) {
                $flag('unit_balance_link_errors', ['unit_id' => $unit->id, 'reason' => 'Missing or mismatched cutover baseline']);

                continue;
            }
            $expected = BigDecimal::of((string) $baseline->qty_on_hand)->plus($newNet[$unit->id] ?? '0');
            if (! $expected->isEqualTo((string) $stock->qty_on_hand)) {
                $flag('unit_balance_mismatches', ['unit_id' => $unit->id, 'baseline_plus_new_movements' => (string) $expected, 'current' => $stock->qty_on_hand]);
            }
        }
        foreach ($stocks as $stock) {
            if ($stock->inventory_stock_unit_id && ! $units->has($stock->inventory_stock_unit_id)) {
                $flag('unit_balance_link_errors', ['stock_id' => $stock->id, 'reason' => 'Unknown unit']);
            }
        }

        return $result;
    }
}
