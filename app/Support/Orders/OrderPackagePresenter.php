<?php

namespace App\Support\Orders;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderPackageInstance;
use App\Services\Media\ImageUploadService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrderPackagePresenter
{
    public function __construct(private readonly ImageUploadService $images) {}

    /**
     * @return array{groups: array<int, array<string, mixed>>, line_context: array<int, array<string, mixed>>, has_packages: bool}
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['lines', 'packageInstances']);

        return $this->build(
            $order->lines,
            $order->packageInstances,
            fn (OrderLine $line): ?int => $line->order_package_instance_id,
            fn (OrderLine $line): OrderLine => $line,
        );
    }

    /**
     * @return array{groups: array<int, array<string, mixed>>, line_context: array<int, array<string, mixed>>, has_packages: bool}
     */
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines.orderLine', 'order.packageInstances']);

        return $this->build(
            $invoice->lines,
            $invoice->order?->packageInstances ?? collect(),
            fn (InvoiceLine $line): ?int => $line->orderLine?->order_package_instance_id,
            fn (InvoiceLine $line): ?OrderLine => $line->orderLine,
        );
    }

    public function messageSummary(Order $order): string
    {
        $presentation = $this->forOrder($order);
        $parts = [];

        foreach ($presentation['groups'] as $group) {
            if ($group['type'] === 'package') {
                $components = collect($group['package']['configured_components'])
                    ->filter(fn (array $component): bool => $this->decimal($component['configured_quantity'] ?? 0)->isGreaterThan(0))
                    ->map(fn (array $component): string => $this->formatQuantity($component['configured_quantity'] ?? 0).'x '.($component['name'] ?? __('Item')))
                    ->implode(', ');

                $parts[] = $group['package']['name'].($components !== '' ? " ({$components})" : '');

                continue;
            }

            foreach ($group['lines'] as $line) {
                $record = $line['record'];
                $quantity = $this->formatQuantity($record->qty);
                $parts[] = $quantity === '1'
                    ? $line['display_name']
                    : $line['display_name']." x{$quantity}";
            }
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  Collection<int, OrderPackageInstance>  $instances
     * @return array{groups: array<int, array<string, mixed>>, line_context: array<int, array<string, mixed>>, has_packages: bool}
     */
    private function build(Collection $records, Collection $instances, \Closure $packageId, \Closure $sourceLine): array
    {
        $instancesById = $instances->keyBy('id');
        $groups = [];
        $groupIndexes = [];

        foreach ($records as $record) {
            $instanceId = $packageId($record);
            $instance = $instanceId ? $instancesById->get($instanceId) : null;
            $groupKey = $instance ? 'package-'.$instance->id : 'ordinary';

            if (! array_key_exists($groupKey, $groupIndexes)) {
                $groupIndexes[$groupKey] = count($groups);
                $groups[] = $instance
                    ? [
                        'type' => 'package',
                        'key' => $groupKey,
                        'package' => $this->package($instance),
                        'lines' => [],
                    ]
                    : [
                        'type' => 'ordinary',
                        'key' => $groupKey,
                        'label' => __('Additional Items'),
                        'lines' => [],
                    ];
            }

            $groups[$groupIndexes[$groupKey]]['lines'][] = [
                'record' => $record,
                'source_line' => $sourceLine($record),
                'display_name' => trim((string) $record->item_name) ?: __('Order item'),
            ];
        }

        $groups = array_values(array_filter($groups, fn (array $group): bool => $group['lines'] !== []));
        $hasPackages = collect($groups)->contains(fn (array $group): bool => $group['type'] === 'package');
        $lineContext = [];

        foreach ($groups as &$group) {
            if ($group['type'] === 'package') {
                $this->applyIndividualLabels($group);
            }

            foreach ($group['lines'] as $index => $line) {
                $lineContext[$line['record']->getKey()] = [
                    'package' => $group['type'] === 'package' ? $group['package'] : null,
                    'starts_package' => $group['type'] === 'package' && $index === 0,
                    'starts_ordinary' => $group['type'] === 'ordinary' && $hasPackages && $index === 0,
                    'display_name' => $line['display_name'],
                ];
            }
        }
        unset($group);

        return [
            'groups' => $groups,
            'line_context' => $lineContext,
            'has_packages' => $hasPackages,
        ];
    }

    /** @return array<string, mixed> */
    private function package(OrderPackageInstance $instance): array
    {
        $originalComponents = $instance->original_component_snapshot ?: $instance->component_snapshot ?: [];
        $configuredComponents = $instance->configured_component_snapshot ?: $instance->component_snapshot ?: [];
        $customizations = $this->customizations($originalComponents, $configuredComponents);
        $originalTotal = $this->money($instance->original_package_total);
        $configuredTotal = $this->money($instance->configured_package_total);
        $adjustment = $this->decimal($configuredTotal)->minus($this->decimal($originalTotal));
        $totalChanged = ! $adjustment->isEqualTo(BigDecimal::zero());

        $imageUrl = $this->images->publicPath($instance->cover_image_path) !== null
            ? $this->images->publicUrl($instance->cover_image_path)
            : null;

        return [
            'id' => $instance->id,
            'name' => trim((string) $instance->package_name) ?: __('Package'),
            'description' => trim((string) $instance->package_description),
            'revision' => $instance->source_template_revision,
            'image_url' => $imageUrl,
            'original_total' => $originalTotal,
            'configured_total' => $configuredTotal,
            'adjustment' => $this->money($adjustment),
            'is_customized' => $customizations !== [] || $totalChanged,
            'customizations' => $customizations,
            'original_components' => $originalComponents,
            'configured_components' => $configuredComponents,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $original
     * @param  array<int, array<string, mixed>>  $configured
     * @return array<int, string>
     */
    private function customizations(array $original, array $configured): array
    {
        $originalByKey = collect($original)->keyBy(fn (array $component): string => $this->componentKey($component));
        $configuredByKey = collect($configured)->keyBy(fn (array $component): string => $this->componentKey($component));
        $changes = [];

        foreach ($originalByKey->keys()->merge($configuredByKey->keys())->unique() as $key) {
            $before = $originalByKey->get($key);
            $after = $configuredByKey->get($key);
            $beforeQuantity = $this->decimal($before['configured_quantity'] ?? $before['default_quantity'] ?? 0);
            $afterQuantity = $this->decimal($after['configured_quantity'] ?? 0);

            if ($beforeQuantity->isEqualTo($afterQuantity)) {
                continue;
            }

            $name = trim((string) ($after['name'] ?? $before['name'] ?? __('Item')));
            $beforeLabel = $this->formatQuantity((string) $beforeQuantity);
            $afterLabel = $this->formatQuantity((string) $afterQuantity);

            if ($afterQuantity->isZero()) {
                $changes[] = __(':item removed (was :before)', ['item' => $name, 'before' => $beforeLabel]);
            } elseif ($beforeQuantity->isZero()) {
                $changes[] = __(':item added at :after', ['item' => $name, 'after' => $afterLabel]);
            } elseif ($afterQuantity->isLessThan($beforeQuantity)) {
                $changes[] = __(':item reduced from :before to :after', ['item' => $name, 'before' => $beforeLabel, 'after' => $afterLabel]);
            } else {
                $changes[] = __(':item increased from :before to :after', ['item' => $name, 'before' => $beforeLabel, 'after' => $afterLabel]);
            }
        }

        return $changes;
    }

    /** @param array<string, mixed> $component */
    private function componentKey(array $component): string
    {
        if (filled($component['template_item_id'] ?? null)) {
            return 'template-'.(int) $component['template_item_id'];
        }

        return ($component['source_type'] ?? 'source').'-'.($component['source_id'] ?? md5(json_encode($component)));
    }

    /** @param array<string, mixed> $group */
    private function applyIndividualLabels(array &$group): void
    {
        $components = collect($group['package']['configured_components'])
            ->keyBy(fn (array $component): int => (int) ($component['template_item_id'] ?? 0));
        $counters = [];

        foreach ($group['lines'] as &$line) {
            $sourceLine = $line['source_line'];
            $componentId = (int) ($sourceLine?->order_package_template_item_id ?? 0);
            $component = $components->get($componentId);

            if (($component['quantity_behavior'] ?? 'bulk') !== 'individual') {
                continue;
            }

            $counters[$componentId] = ($counters[$componentId] ?? 0) + 1;
            $unitIndex = (int) data_get($sourceLine?->meta, 'package_unit_index', $counters[$componentId]);

            if ($unitIndex > 0 && ! preg_match('/#\d+$/', $line['display_name'])) {
                $line['display_name'] .= ' #'.$unitIndex;
            }
        }
        unset($line);
    }

    private function decimal(mixed $value): BigDecimal
    {
        try {
            return BigDecimal::of(filled($value) ? (string) $value : '0');
        } catch (\Throwable) {
            return BigDecimal::zero();
        }
    }

    private function money(mixed $value): string
    {
        return (string) $this->decimal($value)->toScale(2, RoundingMode::HALF_UP);
    }

    private function formatQuantity(mixed $value): string
    {
        return (string) $this->decimal($value)->stripTrailingZeros();
    }
}
