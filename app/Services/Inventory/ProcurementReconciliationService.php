<?php

namespace App\Services\Inventory;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcurementReconciliationService
{
    public function inspect(): array
    {
        $result = ['summary' => array_fill_keys(['procurement_identity_errors', 'procurement_branch_errors', 'receipt_source_errors', 'receipt_movement_errors', 'request_movement_errors', 'procurement_legacy_item_lines'], 0), 'issues' => []];
        if (! Schema::hasColumn('goods_receipt_items', 'inventory_stock_unit_id')) {
            return ['summary' => ['procurement_identity_schema_pending' => 1], 'issues' => []];
        }
        $flag = function ($type, $table, $id) use (&$result) {
            $result['summary'][$type]++;
            $result['issues'][] = compact('type', 'table', 'id');
        };
        $units = DB::table('inventory_stock_units')->get()->keyBy('id');
        $items = DB::table('inventory_items')->get()->keyBy('id');
        $canonicalReceipts = DB::table('goods_receipt_items')->whereNotNull('inventory_stock_unit_id')->distinct()->pluck('goods_receipt_id')->flip();
        $allReceipts = DB::table('goods_receipts')->pluck('id')->flip();
        $newReceipts = DB::table('goods_receipts')->whereNotNull('operation_key')->pluck('id')->flip();
        $canonicalReceipts = $canonicalReceipts->union($newReceipts);
        $purchaseLines = DB::table('purchase_order_items')->get()->keyBy('id');
        $receiptTotals = [];
        $requestTotals = [];
        foreach (['purchase_request_items' => ['purchase_requests', 'purchase_request_id'], 'purchase_order_items' => ['purchase_orders', 'purchase_order_id'], 'goods_receipt_items' => ['goods_receipts', 'goods_receipt_id'], 'order_stock_request_items' => ['order_stock_requests', 'order_stock_request_id']] as $table => [$parentTable, $parentKey]) {
            $parents = DB::table($parentTable)->get()->keyBy('id');
            foreach (DB::table($table)->orderBy('id')->cursor() as $line) {
                $parent = $parents->get($line->$parentKey);
                if (! $line->inventory_stock_unit_id && ! $line->inventory_item_variant_id) {
                    if ($line->inventory_item_id) {
                        if ($table === 'goods_receipt_items' && $newReceipts->has($line->goods_receipt_id)) {
                            $flag('procurement_identity_errors', $table, $line->id);
                        } else {
                            $result['summary']['procurement_legacy_item_lines']++;
                        }
                    }

                    continue;
                }
                $unit = $units->get($line->inventory_stock_unit_id);
                $item = $items->get($line->inventory_item_id);
                if (! $unit || (int) $unit->inventory_item_id !== (int) $line->inventory_item_id || (int) $unit->inventory_item_variant_id !== (int) $line->inventory_item_variant_id) {
                    $flag('procurement_identity_errors', $table, $line->id);
                }
                if (! $parent || ! $item || (int) $parent->branch_id !== (int) $item->branch_id) {
                    $flag('procurement_branch_errors', $table, $line->id);
                }
                if ($table === 'goods_receipt_items') {
                    $source = $purchaseLines->get($line->purchase_order_item_id);
                    if (! $source || ! $parent || (int) $source->purchase_order_id !== (int) $parent->purchase_order_id || (int) $source->inventory_item_id !== (int) $line->inventory_item_id || ($source->inventory_stock_unit_id !== null && (int) $source->inventory_stock_unit_id !== (int) $line->inventory_stock_unit_id)) {
                        $flag('receipt_source_errors', $table, $line->id);
                    }
                    $key = $line->goods_receipt_id.':'.$line->inventory_stock_unit_id;
                    $receiptTotals[$key] = [
                        BigDecimal::of($receiptTotals[$key][0] ?? 0)->plus($line->qty_received),
                        BigDecimal::of($receiptTotals[$key][1] ?? 0)->plus($line->line_total),
                    ];
                }
                if ($table === 'order_stock_request_items') {
                    $key = $line->order_stock_request_id.':'.$line->inventory_stock_unit_id;
                    $requestTotals[$key] = BigDecimal::of($requestTotals[$key] ?? 0)->plus($line->qty_issued ?? 0);
                }
            }
        }
        $receiptMorph = (new \App\Models\GoodsReceipt)->getMorphClass();
        $requestMorph = (new \App\Models\OrderStockRequest)->getMorphClass();
        foreach (DB::table('inventory_transactions')->whereIn('reference_type', [$receiptMorph, $requestMorph])->whereNotNull('inventory_stock_unit_id')->orderBy('id')->cursor() as $movement) {
            $key = $movement->reference_id.':'.$movement->inventory_stock_unit_id;
            if ($movement->reference_type === $receiptMorph && $movement->type === 'receive') {
                if ($allReceipts->has($movement->reference_id) && ! $canonicalReceipts->has($movement->reference_id)) {
                    continue;
                }
                $receiptTotals[$key] = [BigDecimal::of($receiptTotals[$key][0] ?? 0)->minus($movement->qty), BigDecimal::of($receiptTotals[$key][1] ?? 0)->minus($movement->total_cost ?? 0)];
            }
            if ($movement->reference_type === $requestMorph && $movement->type === 'issue') {
                // Historical request lines have no canonical identity; do not infer allocations for them.
                if (isset($requestTotals[$key])) {
                    $requestTotals[$key] = $requestTotals[$key]->plus($movement->qty);
                }
            }
        }
        foreach ($receiptTotals as $key => [$qty, $cost]) {
            if (! $qty->isZero() || ! $cost->isZero()) {
                $flag('receipt_movement_errors', 'goods_receipts', $key);
            }
        }
        foreach ($requestTotals as $key => $qty) {
            if (! $qty->isZero()) {
                $flag('request_movement_errors', 'order_stock_requests', $key);
            }
        }

        return $result;
    }
}
