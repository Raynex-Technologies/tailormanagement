<?php

namespace Tests\Feature\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use App\Models\OrderPackageTemplateItem;
use App\Services\Orders\OrderPackagePricingService;
use DomainException;
use Tests\TestCase;

class OrderCatalogDomainTest extends TestCase
{
    public function test_catalog_item_creation_applies_type_quantity_semantics_and_allows_override(): void
    {
        $garment = OrderCatalogItem::create([
            'name' => 'Two-Piece Suit',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '500000.00',
            'requires_measurements' => true,
        ]);
        $service = OrderCatalogItem::create([
            'name' => 'Express Tailoring',
            'type' => OrderCatalogItemType::Service,
            'default_selling_price' => '50000.00',
        ]);
        $overridden = OrderCatalogItem::create([
            'name' => 'Group Fitting',
            'type' => OrderCatalogItemType::Service,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Individual,
            'default_selling_price' => '10000.00',
        ]);

        $this->assertSame(OrderCatalogQuantityBehavior::Individual, $garment->quantity_behavior);
        $this->assertSame(OrderCatalogQuantityBehavior::Bulk, $service->quantity_behavior);
        $this->assertSame(OrderCatalogQuantityBehavior::Individual, $overridden->quantity_behavior);
        $this->assertStringStartsWith('OCI-', $garment->code);
        $this->assertTrue($garment->requires_measurements);
    }

    public function test_catalog_items_and_templates_support_global_or_selected_branch_availability(): void
    {
        $globalItem = $this->catalogItem(['available_all_branches' => true]);
        $selectedItem = $this->catalogItem(['name' => 'Branch Suit', 'available_all_branches' => false]);
        $selectedItem->branches()->attach($this->branch);

        $globalTemplate = $this->template(['available_all_branches' => true]);
        $selectedTemplate = $this->template([
            'name' => 'Branch Wedding Package',
            'available_all_branches' => false,
        ]);
        $selectedTemplate->branches()->attach($this->branch);

        $this->assertTrue($globalItem->isAvailableForBranch($this->otherBranch->id));
        $this->assertTrue($selectedItem->isAvailableForBranch($this->branch->id));
        $this->assertFalse($selectedItem->isAvailableForBranch($this->otherBranch->id));
        $this->assertTrue(OrderCatalogItem::availableForBranch($this->branch->id)->whereKey($selectedItem)->exists());
        $this->assertFalse(OrderCatalogItem::availableForBranch($this->otherBranch->id)->whereKey($selectedItem)->exists());

        $this->assertTrue($globalTemplate->isAvailableForBranch($this->otherBranch->id));
        $this->assertTrue($selectedTemplate->isAvailableForBranch($this->branch->id));
        $this->assertFalse($selectedTemplate->isAvailableForBranch($this->otherBranch->id));
    }

    public function test_package_can_contain_catalog_inventory_and_mixed_components(): void
    {
        [$template, $suit, $tie, $suitComponent, $tieComponent] = $this->mixedPackage();

        $this->assertTrue($suitComponent->catalogItem->is($suit));
        $this->assertNull($suitComponent->inventoryItem);
        $this->assertTrue($tieComponent->inventoryItem->is($tie));
        $this->assertNull($tieComponent->catalogItem);
        $this->assertCount(2, $template->items);
        $this->assertSame('catalog_item', $suitComponent->sourceType());
        $this->assertSame('inventory_item', $tieComponent->sourceType());
    }

    public function test_component_rejects_missing_or_ambiguous_sources(): void
    {
        $template = $this->template();

        try {
            OrderPackageTemplateItem::create([
                'order_package_template_id' => $template->id,
                'default_quantity' => '1.00',
                'minimum_quantity' => '0.00',
                'package_unit_price' => '1.00',
            ]);
            $this->fail('A component without a source was accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('exactly one', $exception->getMessage());
        }

        $catalogItem = $this->catalogItem();
        $inventoryItem = $this->inventoryItem();

        $this->expectException(DomainException::class);
        OrderPackageTemplateItem::create([
            'order_package_template_id' => $template->id,
            'order_catalog_item_id' => $catalogItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'default_quantity' => '1.00',
            'minimum_quantity' => '0.00',
            'package_unit_price' => '1.00',
        ]);
    }

    public function test_component_rejects_invalid_quantity_ranges_and_negative_prices(): void
    {
        $template = $this->template();
        $catalogItem = $this->catalogItem();

        foreach ([
            ['minimum_quantity' => '2.00', 'default_quantity' => '1.00', 'maximum_quantity' => '3.00', 'package_unit_price' => '1.00'],
            ['minimum_quantity' => '0.00', 'default_quantity' => '4.00', 'maximum_quantity' => '3.00', 'package_unit_price' => '1.00'],
            ['minimum_quantity' => '0.00', 'default_quantity' => '1.00', 'maximum_quantity' => '3.00', 'package_unit_price' => '-1.00'],
        ] as $attributes) {
            try {
                OrderPackageTemplateItem::create([
                    'order_package_template_id' => $template->id,
                    'order_catalog_item_id' => $catalogItem->id,
                    ...$attributes,
                ]);
                $this->fail('Invalid component quantities or price were accepted.');
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_package_calculates_default_standard_savings_and_configured_totals_exactly(): void
    {
        [$template, , , , , $socksComponent] = $this->pricedWeddingPackage();

        $this->assertSame('1000000.00', $template->defaultTotal());
        $this->assertSame('1130000.00', $template->standardValue());
        $this->assertSame('130000.00', $template->savings());
        $this->assertSame('980000.00', $template->configuredTotal([
            $socksComponent->id => '1.00',
        ]));

        $this->expectException(DomainException::class);
        $template->configuredTotal([$socksComponent->id => '3.00']);
    }

    public function test_snapshot_is_deterministic_and_contains_historical_component_configuration(): void
    {
        [$template, $suit, , $suitComponent, , $socksComponent] = $this->pricedWeddingPackage();
        $snapshot = $template->snapshot([$socksComponent->id => '1.00']);

        $this->assertSame($template->id, $snapshot['template_id']);
        $this->assertSame(1, $snapshot['revision']);
        $this->assertSame('1000000.00', $snapshot['original_package_total']);
        $this->assertSame('980000.00', $snapshot['configured_package_total']);
        $this->assertSame($suit->code, $snapshot['components'][0]['source_code']);
        $this->assertSame('2', $snapshot['components'][0]['configured_quantity']);
        $this->assertSame('catalog_item', $snapshot['components'][0]['source_type']);
        $this->assertSame('450000.00', $snapshot['components'][0]['package_unit_price']);
        $this->assertSame($suitComponent->id, $snapshot['components'][0]['template_item_id']);
        $this->assertSame('1', $snapshot['components'][2]['configured_quantity']);
    }

    public function test_package_instance_remains_independent_of_later_template_changes(): void
    {
        [$template, , , $suitComponent] = $this->pricedWeddingPackage();
        $order = $this->order();
        $instance = app(OrderPackagePricingService::class)->createInstance($order, $template);

        $template->update(['name' => 'Changed Package']);
        $suitComponent->update(['package_unit_price' => '475000.00']);
        $template->bumpRevision();

        $instance->refresh();
        $this->assertSame('Wedding Package', $instance->package_name);
        $this->assertSame(1, $instance->source_template_revision);
        $this->assertSame('1000000.00', $instance->original_package_total);
        $this->assertSame('450000.00', $instance->component_snapshot[0]['package_unit_price']);
        $this->assertSame(2, $template->revision);
    }

    public function test_archiving_and_nullable_historical_source_relationships_preserve_order_data(): void
    {
        $catalogItem = $this->catalogItem();
        $order = $this->order();
        $line = $order->lines()->create([
            'order_catalog_item_id' => $catalogItem->id,
            'item_name' => 'Historical Suit',
            'qty' => '1.00',
            'unit_price' => '500000.00',
            'line_total' => '500000.00',
        ]);

        $catalogItem->archive();
        $this->assertFalse(OrderCatalogItem::active()->whereKey($catalogItem)->exists());
        $this->assertTrue(OrderCatalogItem::archived()->whereKey($catalogItem)->exists());
        $this->assertSame('Historical Suit', $line->fresh()->item_name);

        $catalogItem->delete();
        $this->assertNull($line->fresh()->order_catalog_item_id);
        $this->assertSame('500000.00', $line->fresh()->unit_price);

        $template = $this->template();
        $instance = app(OrderPackagePricingService::class)->createInstance($order, $template);
        $template->delete();

        $this->assertNull($instance->fresh()->order_package_template_id);
        $this->assertSame('Wedding Package', $instance->fresh()->package_name);
    }

    public function test_order_line_catalog_and_package_provenance_is_optional_for_ordinary_lines(): void
    {
        $line = $this->order()->lines()->create([
            'item_name' => 'Custom Alteration',
            'qty' => '1.00',
            'unit_price' => '20000.00',
            'line_total' => '20000.00',
        ]);

        $this->assertNull($line->order_catalog_item_id);
        $this->assertNull($line->order_package_instance_id);
        $this->assertNull($line->order_package_template_item_id);
        $this->assertNull($line->catalogItem);
        $this->assertNull($line->packageInstance);
    }

    private function catalogItem(array $attributes = []): OrderCatalogItem
    {
        return OrderCatalogItem::create([
            'name' => 'Two-Piece Suit',
            'description' => 'Tailored two-piece suit.',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '500000.00',
            'requires_measurements' => true,
            ...$attributes,
        ]);
    }

    private function template(array $attributes = []): OrderPackageTemplate
    {
        return OrderPackageTemplate::create([
            'name' => 'Wedding Package',
            'description' => 'Wedding essentials.',
            ...$attributes,
        ]);
    }

    private function inventoryItem(array $attributes = []): InventoryItem
    {
        return InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tie',
            'default_sell_price' => '40000.00',
            ...$attributes,
        ]);
    }

    private function mixedPackage(): array
    {
        $template = $this->template();
        $suit = $this->catalogItem();
        $tie = $this->inventoryItem();
        $suitComponent = $template->items()->create([
            'order_catalog_item_id' => $suit->id,
            'minimum_quantity' => '1.00',
            'default_quantity' => '2.00',
            'maximum_quantity' => '2.00',
            'package_unit_price' => '450000.00',
            'sort_order' => 1,
        ]);
        $tieComponent = $template->items()->create([
            'inventory_item_id' => $tie->id,
            'minimum_quantity' => '1.00',
            'default_quantity' => '2.00',
            'maximum_quantity' => '3.00',
            'package_unit_price' => '30000.00',
            'sort_order' => 2,
        ]);

        return [$template->fresh('items'), $suit, $tie, $suitComponent, $tieComponent];
    }

    private function pricedWeddingPackage(): array
    {
        [$template, $suit, $tie, $suitComponent, $tieComponent] = $this->mixedPackage();
        $socks = $this->inventoryItem([
            'name' => 'Socks',
            'sku' => 'SOCKS-001',
            'default_sell_price' => '25000.00',
        ]);
        $socksComponent = $template->items()->create([
            'inventory_item_id' => $socks->id,
            'minimum_quantity' => '0.00',
            'default_quantity' => '2.00',
            'maximum_quantity' => '2.00',
            'package_unit_price' => '20000.00',
            'sort_order' => 3,
        ]);

        return [
            $template->fresh('items'),
            $suit,
            $tie,
            $suitComponent,
            $tieComponent,
            $socksComponent,
        ];
    }

    private function order(): Order
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        return Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'order_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
        ]);
    }
}
