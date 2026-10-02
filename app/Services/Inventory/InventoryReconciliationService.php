<?php

namespace App\Services\Inventory;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class InventoryReconciliationService
{
    /** Read-only evidence. Differences are never repaired or assigned to variants. */
    public function inspect(): array
    {
        $report = ['summary' => [], 'issues' => [], 'variants' => []];
        $items = DB::table('inventory_items')->orderBy('id')->get()->keyBy('id');
        $stocks = DB::table('inventory_stocks')->orderBy('id')->get()->groupBy('inventory_item_id');
        $variants = DB::table('inventory_item_variants')->orderBy('id')->get();
        $variantIds = $variants->keyBy('id');
        $report['summary'] = array_fill_keys([
            'items_checked', 'stock_rows_checked', 'missing_stock_rows', 'duplicate_stock_rows',
            'orphan_stock_rows', 'negative_stock', 'invalid_reservations', 'balance_mismatches',
            'branch_mismatches', 'orphan_movements', 'variants_with_legacy_stock', 'duplicate_variant_skus',
            'null_variant_skus', 'duplicate_combinations', 'variant_stock_mismatches',
            'missing_variant_references', 'variant_parent_mismatches', 'orphan_variants',
            'legacy_active_reservations', 'reservation_snapshot_mismatches',
        ], 0);
        $report['summary']['items_checked'] = $items->count();
        $report['summary']['stock_rows_checked'] = $stocks->flatten(1)->count();
        $flag = function (string $type, array $detail) use (&$report) {
            $report['summary'][$type]++;
            $report['issues'][] = ['type' => $type, ...$detail];
        };
        $ledger = [];
        foreach (DB::table('inventory_transactions')->orderBy('id')->cursor() as $movement) {
            $id = $movement->inventory_item_id;
            $ledger[$id] = (string) BigDecimal::of($ledger[$id] ?? '0')->plus((string) $movement->qty);
            if (! isset($items[$id])) {
                $flag('orphan_movements', ['transaction_id' => $movement->id, 'item_id' => $id]);
            } elseif ((int) $movement->branch_id !== (int) $items[$id]->branch_id) {
                $flag('branch_mismatches', ['table' => 'inventory_transactions', 'id' => $movement->id, 'item_id' => $id]);
            }
        }
        foreach ($stocks as $itemId => $rows) {
            if (! isset($items[$itemId])) {
                foreach ($rows as $row) {
                    $flag('orphan_stock_rows', ['stock_id' => $row->id, 'item_id' => $itemId]);
                }
            }
            if ($rows->count() > 1 && ($items[$itemId]->variant_mode ?? 'simple') === 'simple') {
                $flag('duplicate_stock_rows', ['item_id' => $itemId, 'stock_ids' => $rows->pluck('id')->all()]);
            }
            foreach ($rows as $stock) {
                if (BigDecimal::of((string) $stock->qty_on_hand)->isNegative()) {
                    $flag('negative_stock', ['stock_id' => $stock->id]);
                }
                if (BigDecimal::of((string) $stock->qty_reserved)->isNegative() || BigDecimal::of((string) $stock->qty_reserved)->isGreaterThan((string) $stock->qty_on_hand)) {
                    $flag('invalid_reservations', ['stock_id' => $stock->id, 'reserved' => $stock->qty_reserved, 'on_hand' => $stock->qty_on_hand]);
                }
                if (isset($items[$itemId]) && (int) $stock->branch_id !== (int) $items[$itemId]->branch_id) {
                    $flag('branch_mismatches', ['table' => 'inventory_stocks', 'id' => $stock->id, 'item_id' => $itemId]);
                }
            }
        }
        foreach ($items as $item) {
            $stock = $stocks->get($item->id, collect())->first();
            if (! $stock) {
                $flag('missing_stock_rows', ['item_id' => $item->id, 'sku' => $item->sku]);

                continue;
            }
            $net = BigDecimal::of($ledger[$item->id] ?? '0')->toScale(2, RoundingMode::HALF_UP);
            $physical = $stocks->get($item->id, collect())->reduce(fn ($sum, $row) => $sum->plus((string) $row->qty_on_hand), BigDecimal::zero());
            $difference = $physical->minus($net);
            if (! $difference->isZero()) {
                $flag('balance_mismatches', ['item_id' => $item->id, 'sku' => $item->sku, 'balance' => $stock->qty_on_hand, 'movement_net' => (string) $net, 'difference' => (string) $difference, 'status' => 'RECONCILIATION REQUIRED']);
            }
        }
        $references = [];
        foreach (['order_lines', 'cart_items', 'fabric_variants', 'purchase_request_items', 'purchase_order_items', 'goods_receipt_items', 'order_stock_request_items'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn($table, 'inventory_item_variant_id')) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull('inventory_item_variant_id')->orderBy('id')->cursor() as $row) {
                $id = $row->inventory_item_variant_id;
                $references[$id][$table] = ($references[$id][$table] ?? 0) + 1;
                if (! isset($variantIds[$id])) {
                    $flag('missing_variant_references', ['table' => $table, 'id' => $row->id, 'variant_id' => $id]);
                } elseif (isset($row->inventory_item_id) && (int) $row->inventory_item_id !== (int) $variantIds[$id]->inventory_item_id) {
                    $flag('variant_parent_mismatches', ['table' => $table, 'id' => $row->id, 'variant_id' => $id]);
                }
            }
        }
        foreach ($variants->groupBy(fn ($v) => mb_strtolower(trim((string) $v->sku))) as $sku => $rows) {
            if ($sku !== '' && $rows->count() > 1) {
                $flag('duplicate_variant_skus', ['sku' => $sku, 'variant_ids' => $rows->pluck('id')->all()]);
            }
        }
        foreach ($variants->groupBy('inventory_item_id') as $itemId => $rows) {
            $item = $items->get($itemId);
            $parentStock = $stocks->get($itemId, collect())->first()?->qty_on_hand;
            $entry = ['item_id' => $itemId, 'sku' => $item?->sku, 'parent_stock' => $parentStock, 'variant_count' => $rows->count(), 'variants' => []];
            $total = BigDecimal::zero();
            $hasLegacy = false;
            foreach ($rows as $variant) {
                if (! $item) {
                    $flag('orphan_variants', ['variant_id' => $variant->id, 'item_id' => $itemId]);
                }
                if (trim((string) $variant->sku) === '') {
                    $flag('null_variant_skus', ['variant_id' => $variant->id]);
                }
                if ($variant->stock_qty !== null) {
                    $hasLegacy = true;
                    $total = $total->plus((string) $variant->stock_qty);
                    $flag('variants_with_legacy_stock', ['variant_id' => $variant->id, 'stock_qty' => $variant->stock_qty]);
                }
                $entry['variants'][] = ['id' => $variant->id, 'sku' => $variant->sku, 'is_active' => (bool) $variant->is_active, 'stock_qty' => $variant->stock_qty, 'combination' => VariantSynchronizationService::combination((array) $variant), 'references' => $references[$variant->id] ?? []];
            }
            foreach ($rows->groupBy(fn ($v) => ($v->combination_key ?? null) ?: VariantSynchronizationService::combination((array) $v)) as $key => $duplicates) {
                if ($duplicates->count() > 1) {
                    $flag('duplicate_combinations', ['item_id' => $itemId, 'combination' => $key, 'variant_ids' => $duplicates->pluck('id')->all()]);
                }
            }
            if ($hasLegacy && $parentStock !== null && ! $total->isEqualTo((string) $parentStock)) {
                $flag('variant_stock_mismatches', ['item_id' => $itemId, 'parent_stock' => $parentStock, 'legacy_variant_total' => (string) $total]);
            }
            $report['variants'][] = $entry;
        }
        $latest = DB::table('order_status_histories')->whereIn('status', ['inventory_reserved', 'inventory_released', 'inventory_committed'])->selectRaw('MAX(id) as id')->groupBy('order_id');
        $held = [];
        foreach (DB::table('order_status_histories')->whereIn('id', $latest)->where('status', 'inventory_reserved')->get() as $history) {
            $quantities = (json_decode($history->metadata ?? '{}', true) ?: [])['inventory_quantities'] ?? null;
            if (! is_array($quantities)) {
                $flag('legacy_active_reservations', ['order_id' => $history->order_id]);

                continue;
            }
            foreach ($quantities as $id => $qty) {
                $held[$id] = (string) BigDecimal::of($held[$id] ?? '0')->plus((string) $qty);
            }
        }
        foreach ($stocks as $id => $rows) {
            if (! BigDecimal::of((string) $rows->first()->qty_reserved)->isEqualTo($held[$id] ?? '0')) {
                $flag('reservation_snapshot_mismatches', ['item_id' => $id, 'reserved' => $rows->first()->qty_reserved, 'snapshot_total' => $held[$id] ?? '0']);
            }
        }

        $report['stock_units'] = app(StockUnitReconciliationService::class)->inspect();

        $procurement = app(ProcurementReconciliationService::class)->inspect();
        $report['stock_units']['summary'] += $procurement['summary'];
        $report['stock_units']['issues'] = [...$report['stock_units']['issues'], ...$procurement['issues']];

        return $report;
    }
}
