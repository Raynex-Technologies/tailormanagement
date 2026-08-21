<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageInstance;
use App\Models\OrderPackageTemplate;
use App\Models\OrderPackageTemplateItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class OrderPackagePricingService
{
    /**
     * Calculate the commercial values used by catalog administration.
     *
     * @param  iterable<array<string, mixed>|OrderPackageTemplateItem>  $components
     * @return array{standard_value: string, package_price: string, difference: string}
     */
    public function summarizeComponents(iterable $components): array
    {
        $standardValue = BigDecimal::zero();
        $packagePrice = BigDecimal::zero();

        foreach ($components as $component) {
            if ($component instanceof OrderPackageTemplateItem) {
                $quantity = (string) $component->default_quantity;
                $standardUnitPrice = $component->standardUnitPrice();
                $packageUnitPrice = (string) $component->package_unit_price;
            } else {
                $quantity = (string) ($component['default_quantity'] ?? '0');
                $standardUnitPrice = (string) ($component['standard_unit_price'] ?? '0');
                $packageUnitPrice = (string) ($component['package_unit_price'] ?? '0');
            }

            $quantityValue = $this->decimal($quantity);
            $standardValue = $standardValue->plus($this->decimal($standardUnitPrice)->multipliedBy($quantityValue));
            $packagePrice = $packagePrice->plus($this->decimal($packageUnitPrice)->multipliedBy($quantityValue));
        }

        return [
            'standard_value' => $this->money($standardValue),
            'package_price' => $this->money($packagePrice),
            'difference' => $this->money($standardValue->minus($packagePrice)),
        ];
    }

    /** @return array{standard_value: string, package_price: string, difference: string} */
    public function summary(OrderPackageTemplate $template): array
    {
        return $this->summarizeComponents($this->components($template));
    }

    public function componentPackageTotal(string $quantity, string $unitPrice): string
    {
        return $this->money($this->decimal($unitPrice)->multipliedBy($this->decimal($quantity)));
    }

    /**
     * Reconfigure a package using only its captured historical snapshot.
     *
     * @param  array<string, mixed>  $originalSnapshot
     * @param  array<int|string, int|float|string>  $configuredQuantities
     * @return array<string, mixed>
     */
    public function configureCapturedSnapshot(array $originalSnapshot, array $configuredQuantities): array
    {
        $components = collect($originalSnapshot['components'] ?? []);
        $knownIds = $components->pluck('template_item_id')->map(fn ($id) => (int) $id)->all();
        $this->assertKnownComponents($knownIds, $configuredQuantities);
        $configuredTotal = BigDecimal::zero();
        $configuredComponents = [];

        foreach ($components as $component) {
            $componentId = (int) $component['template_item_id'];
            $quantity = BigDecimal::of((string) ($configuredQuantities[$componentId] ?? $component['configured_quantity'] ?? $component['default_quantity']));
            $minimum = BigDecimal::of((string) $component['minimum_quantity']);
            $maximum = filled($component['maximum_quantity'] ?? null)
                ? BigDecimal::of((string) $component['maximum_quantity'])
                : null;

            if ($quantity->isLessThan($minimum)) {
                throw new DomainException("Configured quantity for {$component['name']} is below its minimum.");
            }
            if ($maximum !== null && $quantity->isGreaterThan($maximum)) {
                throw new DomainException("Configured quantity for {$component['name']} exceeds its maximum.");
            }

            $packageLineTotal = BigDecimal::of((string) $component['package_unit_price'])->multipliedBy($quantity);
            $standardLineTotal = BigDecimal::of((string) $component['standard_unit_price'])->multipliedBy($quantity);
            $configuredTotal = $configuredTotal->plus($packageLineTotal);
            $component['configured_quantity'] = $this->quantity((string) $quantity);
            $component['package_line_total'] = $this->money($packageLineTotal);
            $component['standard_line_total'] = $this->money($standardLineTotal);
            $configuredComponents[] = $component;
        }

        return [
            ...$originalSnapshot,
            'configured_package_total' => $this->money($configuredTotal),
            'components' => $configuredComponents,
        ];
    }

    public function defaultTotal(OrderPackageTemplate $template): string
    {
        return $this->summary($template)['package_price'];
    }

    public function standardValue(OrderPackageTemplate $template): string
    {
        return $this->summary($template)['standard_value'];
    }

    public function savings(OrderPackageTemplate $template): string
    {
        return $this->summary($template)['difference'];
    }

    public function configuredTotal(OrderPackageTemplate $template, array $configuredQuantities): string
    {
        return $this->sumComponents($template, $configuredQuantities);
    }

    public function snapshot(OrderPackageTemplate $template, array $configuredQuantities = []): array
    {
        $components = $this->components($template);
        $this->assertKnownComponents($components->pluck('id')->all(), $configuredQuantities);

        $componentSnapshots = [];

        foreach ($components as $component) {
            $quantity = $this->configuredQuantity($component, $configuredQuantities);
            $source = $component->source();
            $packageLineTotal = BigDecimal::of((string) $component->package_unit_price)
                ->multipliedBy($quantity);
            $standardLineTotal = BigDecimal::of($component->standardUnitPrice())
                ->multipliedBy($quantity);

            $componentSnapshots[] = [
                'template_item_id' => $component->id,
                'source_type' => $component->sourceType(),
                'source_id' => $source->id,
                'source_code' => $source instanceof OrderCatalogItem ? $source->code : $source->sku,
                'name' => $source->name,
                'description' => $source instanceof OrderCatalogItem
                    ? $source->description
                    : $source->short_description,
                'image_path' => $source instanceof OrderCatalogItem
                    ? $source->image_path
                    : $source->featured_image_path,
                'catalog_item_type' => $source instanceof OrderCatalogItem ? $source->type->value : null,
                'quantity_behavior' => $source instanceof OrderCatalogItem
                    ? $source->quantity_behavior->value
                    : 'bulk',
                'requires_measurements' => $source instanceof OrderCatalogItem
                    ? $source->requires_measurements
                    : false,
                'minimum_quantity' => $this->quantity((string) $component->minimum_quantity),
                'default_quantity' => $this->quantity((string) $component->default_quantity),
                'maximum_quantity' => $component->maximum_quantity === null
                    ? null
                    : $this->quantity((string) $component->maximum_quantity),
                'configured_quantity' => $this->quantity((string) $quantity),
                'standard_unit_price' => $this->money(BigDecimal::of($component->standardUnitPrice())),
                'package_unit_price' => $this->money(BigDecimal::of((string) $component->package_unit_price)),
                'standard_line_total' => $this->money($standardLineTotal),
                'package_line_total' => $this->money($packageLineTotal),
                'sort_order' => $component->sort_order,
            ];
        }

        return [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'revision' => $template->revision,
            'name' => $template->name,
            'description' => $template->description,
            'cover_image_path' => $template->cover_image_path,
            'original_package_total' => $this->defaultTotal($template),
            'configured_package_total' => $this->configuredTotal($template, $configuredQuantities),
            'standard_value' => $this->standardValue($template),
            'savings' => $this->savings($template),
            'components' => $componentSnapshots,
        ];
    }

    public function createInstance(
        Order $order,
        OrderPackageTemplate $template,
        array $configuredQuantities = [],
        ?User $configuredBy = null
    ): OrderPackageInstance {
        $snapshot = $this->snapshot($template, $configuredQuantities);

        return OrderPackageInstance::create([
            'order_id' => $order->id,
            'order_package_template_id' => $template->id,
            'source_template_revision' => $template->revision,
            'package_name' => $template->name,
            'package_description' => $template->description,
            'cover_image_path' => $template->cover_image_path,
            'original_package_total' => $snapshot['original_package_total'],
            'configured_package_total' => $snapshot['configured_package_total'],
            'component_snapshot' => $snapshot['components'],
            'original_component_snapshot' => $snapshot['components'],
            'configured_component_snapshot' => $snapshot['components'],
            'configured_by' => $configuredBy?->id,
        ]);
    }

    protected function sumComponents(OrderPackageTemplate $template, array $configuredQuantities): string
    {
        $components = $this->components($template);
        $this->assertKnownComponents($components->pluck('id')->all(), $configuredQuantities);
        $total = BigDecimal::zero();

        foreach ($components as $component) {
            $quantity = $this->configuredQuantity($component, $configuredQuantities);
            $total = $total->plus(
                BigDecimal::of((string) $component->package_unit_price)->multipliedBy($quantity)
            );
        }

        return $this->money($total);
    }

    protected function configuredQuantity(
        OrderPackageTemplateItem $component,
        array $configuredQuantities
    ): BigDecimal {
        $rawQuantity = array_key_exists($component->id, $configuredQuantities)
            ? $configuredQuantities[$component->id]
            : $component->default_quantity;
        $quantity = BigDecimal::of((string) $rawQuantity);
        $minimum = BigDecimal::of((string) $component->minimum_quantity);
        $maximum = $component->maximum_quantity === null
            ? null
            : BigDecimal::of((string) $component->maximum_quantity);

        if ($quantity->isLessThan($minimum)) {
            throw new DomainException("Configured quantity for component {$component->id} is below its minimum.");
        }

        if ($maximum !== null && $quantity->isGreaterThan($maximum)) {
            throw new DomainException("Configured quantity for component {$component->id} exceeds its maximum.");
        }

        return $quantity;
    }

    protected function assertKnownComponents(array $componentIds, array $configuredQuantities): void
    {
        $known = array_map('intval', $componentIds);

        foreach (array_keys($configuredQuantities) as $componentId) {
            if (! in_array((int) $componentId, $known, true)) {
                throw new DomainException("Unknown package component {$componentId}.");
            }
        }
    }

    protected function components(OrderPackageTemplate $template)
    {
        if ($template->relationLoaded('items')) {
            return $template->items;
        }

        return $template->items()
            ->with(['catalogItem', 'inventoryItem'])
            ->get();
    }

    protected function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HALF_UP);
    }

    protected function quantity(string $value): string
    {
        return (string) BigDecimal::of($value)->stripTrailingZeros();
    }

    protected function decimal(mixed $value): BigDecimal
    {
        try {
            return BigDecimal::of(filled($value) ? (string) $value : '0');
        } catch (\Throwable) {
            return BigDecimal::zero();
        }
    }
}
