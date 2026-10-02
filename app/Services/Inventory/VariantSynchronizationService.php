<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantSynchronizationService
{
    public static function combination(array $values): string
    {
        if (! empty($values['combination_key'])) {
            return $values['combination_key'];
        }
        $options = $values['option_values'] ?? [];
        if (is_string($options)) {
            $options = json_decode($options, true) ?: [];
        }
        foreach (['size', 'color'] as $key) {
            $options[$key] = $values[$key] ?? ($options[$key] ?? null);
        }
        $options = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $options);
        $options = array_filter($options, fn ($value) => $value !== '');
        ksort($options);

        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    public function sync(InventoryItem $item, array $payloads): array
    {
        return DB::transaction(function () use ($item, $payloads) {
            $retired = [];
            $item = InventoryItem::withoutBranchScope()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->variant_mode !== 'simple' || $item->variants()->whereNotNull('combination_key')->exists()) {
                throw ValidationException::withMessages(['productSizes' => 'Operational variants must be managed through Inventory, not the legacy storefront generator.']);
            }
            $existing = $item->variants()->lockForUpdate()->get()->groupBy(fn ($variant) => self::combination($variant->toArray()));
            $seen = [];
            $keep = [];
            foreach ($payloads as $payload) {
                $key = self::combination($payload);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $matches = $existing->get($key, collect());
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['productSizes' => 'Duplicate existing variant combinations require reconciliation before editing.']);
                }
                if ($variant = $matches->first()) {
                    // Preserve historical meaning, SKU, price, and legacy quantity.
                    $variant->update(['is_active' => true]);
                } else {
                    $base = $payload['sku'];
                    $suffix = 2;
                    while (DB::table('inventory_item_variants')->where('sku', $payload['sku'])->exists()) {
                        $payload['sku'] = $base.'-'.$suffix++;
                    }
                    $variant = $item->variants()->create($payload);
                }
                $keep[] = $variant->id;
            }
            foreach ($existing->flatten(1) as $variant) {
                if (in_array($variant->id, $keep, true)) {
                    continue;
                }
                // Inspect persistent references before retiring; never delete either kind.
                $references = [];
                foreach (['order_lines', 'cart_items', 'fabric_variants'] as $table) {
                    $references[$table] = DB::table($table)->where('inventory_item_variant_id', $variant->id)->count();
                }
                $retired[$variant->id] = $references;
                $variant->update(['is_active' => false]);
            }

            return $retired;
        });
    }
}
