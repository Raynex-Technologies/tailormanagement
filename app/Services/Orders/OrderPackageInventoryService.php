<?php

namespace App\Services\Orders;

use Brick\Math\BigDecimal;
use DomainException;

class OrderPackageInventoryService
{
    public function allocate(array $snapshot, array $allocations, int $branchId): array
    {
        $known = collect($snapshot['components'])->pluck('template_item_id')->map(fn ($id) => 'component_'.$id)->all();
        if (array_diff(array_keys($allocations), $known)) {
            throw new DomainException('Unknown package inventory component.');
        }
        foreach ($snapshot['components'] as &$component) {
            if ($component['source_type'] !== 'inventory_item') {
                continue;
            }
            $quantity = BigDecimal::of((string) $component['configured_quantity'])->toScale(2);
            $rows = $allocations['component_'.$component['template_item_id']] ?? [];
            if (($component['variation_selection'] ?? null) !== 'deferred') {
                $rows = $quantity->isZero() ? [] : [['inventory_stock_unit_id' => $component['inventory_stock_unit_id'] ?? null, 'quantity' => (string) $quantity]];
            }
            $sum = BigDecimal::zero();
            $seen = [];
            $resolved = [];
            foreach ($rows as $row) {
                $qty = BigDecimal::of((string) ($row['quantity'] ?? '0'))->toScale(2);
                if (! $qty->isPositive()) {
                    throw new DomainException('Every selected variation quantity must be positive.');
                }
                if (($component['variation_selection'] ?? null) === 'deferred' && empty($row['inventory_stock_unit_id'])) {
                    throw new DomainException('Choose a variation for every package allocation.');
                }
                $unit = app(OrderInventorySelectionService::class)->resolve((int) $component['source_id'], ($row['inventory_stock_unit_id'] ?? null) ?: null, $branchId);
                if (isset($seen[$unit->id])) {
                    throw new DomainException('Combine repeated variation quantities into one allocation.');
                }
                $seen[$unit->id] = true;
                $resolved[] = [...app(OrderInventorySelectionService::class)->snapshot($unit), 'quantity' => (string) $qty];
                $sum = $sum->plus($qty);
            }
            if (! $sum->isEqualTo($quantity)) {
                throw new DomainException($component['name'].': allocated quantities must equal '.$quantity.'.');
            }
            $component['inventory_allocations'] = $resolved;
        }
        unset($component);

        return $snapshot;
    }
}
