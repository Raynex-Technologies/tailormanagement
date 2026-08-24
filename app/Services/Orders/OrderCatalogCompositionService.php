<?php

namespace App\Services\Orders;

use App\Enums\OrderCatalogQuantityBehavior;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use Brick\Math\BigDecimal;
use DomainException;

class OrderCatalogCompositionService
{
    public function catalogItemForSelection(int $itemId, int $branchId): OrderCatalogItem
    {
        return OrderCatalogItem::query()
            ->active()
            ->availableForBranch($branchId)
            ->findOrFail($itemId);
    }

    public function packageForSelection(int $templateId, int $branchId): OrderPackageTemplate
    {
        return OrderPackageTemplate::query()
            ->active()
            ->availableForBranch($branchId)
            ->with(['branches:id', 'items.catalogItem', 'items.inventoryItem'])
            ->findOrFail($templateId);
    }

    /** @return array<int, array<string, mixed>> */
    public function directCatalogLines(OrderCatalogItem $item, string|int|float $quantity): array
    {
        $quantityValue = BigDecimal::of((string) $quantity);
        if ($quantityValue->isLessThanOrEqualTo(0)) {
            throw new DomainException('Quantity must be greater than zero.');
        }

        $base = [
            'id' => null,
            'inventory_item_id' => null,
            'order_catalog_item_id' => $item->id,
            'order_package_instance_id' => null,
            'order_package_template_item_id' => null,
            'package_key' => null,
            'sku' => $item->code,
            'assigned_tailor_id' => null,
            'unit_price' => (string) $item->default_selling_price,
            'notes' => '',
            'requires_measurements' => $item->requires_measurements,
            'catalog_item_type' => $item->type->value,
            'garment_category_id' => $item->garment_category_id,
            'measurements' => [['key' => '', 'value' => '']],
        ];

        if ($item->quantity_behavior === OrderCatalogQuantityBehavior::Bulk) {
            return [[
                ...$base,
                'item_name' => $item->name,
                'qty' => (string) $quantityValue,
                'line_total' => app(OrderPackagePricingService::class)->componentPackageTotal((string) $quantityValue, (string) $item->default_selling_price),
            ]];
        }

        if ($quantityValue->stripTrailingZeros()->getScale() > 0) {
            throw new DomainException('Individual catalog items require a whole-number quantity.');
        }

        $count = $quantityValue->toInt();
        $lines = [];
        for ($number = 1; $number <= $count; $number++) {
            $lines[] = [
                ...$base,
                'item_name' => $count > 1 ? "{$item->name} #{$number}" : $item->name,
                'qty' => '1',
                'line_total' => (string) $item->default_selling_price,
            ];
        }

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    public function packageLines(array $configuredSnapshot, string $packageKey): array
    {
        $lines = [];

        foreach ($configuredSnapshot['components'] ?? [] as $component) {
            $quantity = BigDecimal::of((string) $component['configured_quantity']);
            if ($quantity->isZero()) {
                continue;
            }

            $base = [
                'id' => null,
                'inventory_item_id' => $component['source_type'] === 'inventory_item' ? (int) $component['source_id'] : null,
                'order_catalog_item_id' => $component['source_type'] === 'catalog_item' ? (int) $component['source_id'] : null,
                'order_package_instance_id' => null,
                'order_package_template_item_id' => (int) $component['template_item_id'],
                'package_key' => $packageKey,
                'sku' => $component['source_code'],
                'assigned_tailor_id' => null,
                'unit_price' => (string) $component['package_unit_price'],
                'notes' => '',
                'requires_measurements' => (bool) ($component['requires_measurements'] ?? false),
                'catalog_item_type' => $component['catalog_item_type'] ?? null,
                'garment_category_id' => $component['garment_category_id'] ?? null,
                'measurements' => [['key' => '', 'value' => '']],
            ];

            if (($component['quantity_behavior'] ?? 'bulk') === OrderCatalogQuantityBehavior::Individual->value) {
                if ($quantity->stripTrailingZeros()->getScale() > 0) {
                    throw new DomainException("{$component['name']} requires a whole-number quantity.");
                }

                for ($number = 1; $number <= $quantity->toInt(); $number++) {
                    $lines[] = [
                        ...$base,
                        'item_name' => $quantity->isGreaterThan(1) ? "{$component['name']} #{$number}" : $component['name'],
                        'qty' => '1',
                        'line_total' => (string) $component['package_unit_price'],
                        'package_unit_index' => $number,
                    ];
                }
            } else {
                $lines[] = [
                    ...$base,
                    'item_name' => $component['name'],
                    'qty' => (string) $quantity,
                    'line_total' => (string) $component['package_line_total'],
                    'package_unit_index' => null,
                ];
            }
        }

        return $lines;
    }
}
