<?php

namespace Tests\Feature\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\DeliveryNotes\Show as DeliveryNoteShow;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Orders\Show as OrderShow;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageInstance;
use App\Models\OrderPackageTemplate;
use App\Models\PaymentMethod;
use App\Services\Sms\Templates\OrderSmsTemplates;
use App\Support\InvoicePdfRenderer;
use App\Support\Orders\OrderPackagePresenter;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPackagePresentationTest extends TestCase
{
    public function test_legacy_and_direct_lines_render_as_ordinary_order_items(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $catalogItem = $this->catalogItem('Direct Catalog Shirt', OrderCatalogQuantityBehavior::Individual);
        $inventoryItem = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Direct Inventory Buttons',
        ]);
        $order = $this->newOrder($customer, $user->id);

        $order->lines()->create([
            'item_name' => 'Legacy Bespoke Line',
            'qty' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);
        $order->lines()->create([
            'order_catalog_item_id' => $catalogItem->id,
            'item_name' => 'Direct Catalog Shirt',
            'qty' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
        ]);
        $order->lines()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => 'Direct Inventory Buttons',
            'qty' => 2,
            'unit_price' => 5000,
            'line_total' => 10000,
        ]);
        $order->recalculateTotals();

        Livewire::test(OrderShow::class, ['order' => $order->fresh()])
            ->assertSee('Legacy Bespoke Line')
            ->assertSee('Direct Catalog Shirt')
            ->assertSee('Direct Inventory Buttons')
            ->assertDontSee('Configured package value')
            ->assertDontSee('Additional Items');

        $this->assertSame('230000.00', (string) $order->fresh()->subtotal);
    }

    public function test_order_show_groups_mixed_package_contents_and_explains_customization(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $fixture = $this->packageOrder($user->id, customized: true);

        Livewire::test(OrderShow::class, ['order' => $fixture['order']])
            ->assertSee('Premium Wedding Package')
            ->assertSee('Package revision 3')
            ->assertSee('Configured package value')
            ->assertSee('930,000')
            ->assertSee('Original package value')
            ->assertSee('960,000')
            ->assertSee('Adjustment')
            ->assertSee('Socks Care reduced from 2 to 1')
            ->assertSee('Two-Piece Suit #1')
            ->assertSee('Two-Piece Suit #2')
            ->assertSee('Socks Care')
            ->assertSee('Additional Items')
            ->assertSee('White Shirt');

        $this->assertSame('1050000.00', (string) $fixture['order']->fresh()->subtotal);
        $this->assertSame(
            '1050000.00',
            (string) $fixture['order']->lines()->sum('line_total')
        );
    }

    public function test_unchanged_and_missing_source_packages_use_snapshots_without_misleading_state(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $fixture = $this->packageOrder($user->id, customized: false);
        $fixture['template']->update([
            'name' => 'Renamed Current Template',
            'archived_at' => now(),
        ]);
        $fixture['instance']->update([
            'order_package_template_id' => null,
            'cover_image_path' => 'order-packages/missing-history-image.jpg',
        ]);

        $presentation = app(OrderPackagePresenter::class)->forOrder(
            $fixture['order']->fresh(['lines', 'packageInstances'])
        );

        $this->assertSame('Premium Wedding Package', $presentation['groups'][0]['package']['name']);
        $this->assertFalse($presentation['groups'][0]['package']['is_customized']);
        $this->assertNull($presentation['groups'][0]['package']['image_url']);

        Livewire::test(OrderShow::class, ['order' => $fixture['order']->fresh()])
            ->assertSee('Premium Wedding Package')
            ->assertSee('A complete historical wedding set.')
            ->assertDontSee('Renamed Current Template')
            ->assertDontSee('Customized:')
            ->assertDontSee('ARCHIVED PACKAGE');
    }

    public function test_invoice_show_and_every_active_invoice_template_group_without_changing_totals(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $fixture = $this->packageOrder($user->id, customized: true);
        $invoice = Invoice::syncFromOrder($fixture['order']->fresh(['lines']), $user->id)
            ->fresh(['lines.orderLine', 'order.customer', 'order.branch', 'order.packageInstances', 'branch']);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->assertSee('Premium Wedding Package')
            ->assertSee('Package-linked invoice items are grouped below and remain read-only.')
            ->assertSee('Configured package value')
            ->assertSee('Additional Items');

        $settings = BusinessSetting::instance();
        $paymentMethods = PaymentMethod::forInvoiceDocument();
        $templates = \App\Models\InvoiceTemplate::query()->where('is_active', true)->get();
        $this->assertNotEmpty($templates);

        foreach ($templates as $template) {
            $html = view('invoices.print', [
                'invoice' => $invoice,
                'settings' => $settings,
                'template' => $template,
                'paymentMethods' => $paymentMethods,
                'downloadMode' => false,
            ])->render();

            $this->assertStringContainsString('Premium Wedding Package', $html, $template->slug);
            $this->assertStringContainsString('Configured package value', $html, $template->slug);
            $this->assertStringContainsString('Additional Items', $html, $template->slug);
        }

        $pdf = app(InvoicePdfRenderer::class)->render($invoice, $settings, $paymentMethods, $templates->first());
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame('1050000.00', (string) $invoice->fresh()->subtotal);
        $this->assertSame('1050000.00', (string) $invoice->lines()->sum('line_total'));
    }

    public function test_delivery_note_groups_package_contents_and_preserves_canonical_quantities(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $fixture = $this->packageOrder($user->id, customized: true);
        $deliveryNote = DeliveryNote::create([
            'branch_id' => $this->branch->id,
            'order_id' => $fixture['order']->id,
            'delivered_at' => now(),
            'delivered_by' => $user->id,
            'received_by_name' => 'Package Customer',
        ]);

        Livewire::test(DeliveryNoteShow::class, ['deliveryNote' => $deliveryNote])
            ->assertSee('Premium Wedding Package')
            ->assertSee('Two-Piece Suit #1')
            ->assertSee('Two-Piece Suit #2')
            ->assertSee('Socks Care')
            ->assertSee('Additional Items')
            ->assertSee('White Shirt');

        $response = $this->get(route('delivery-notes.print', $deliveryNote));
        $response->assertOk();
        $response->assertSee('Premium Wedding Package');
        $response->assertSee('Two-Piece Suit #1');
        $response->assertSee('Socks Care');
        $response->assertSee('<td>1</td>', false);

        $this->assertSame('1.00', (string) $fixture['socks_line']->fresh()->qty);
    }

    public function test_loaded_presenter_is_query_free_and_sms_uses_concise_package_wording(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $fixture = $this->packageOrder($user->id, customized: true);
        $order = $fixture['order']->fresh(['customer', 'lines', 'packageInstances']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $presentation = app(OrderPackagePresenter::class)->forOrder($order);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
        $this->assertCount(2, $presentation['groups']);

        $garments = OrderSmsTemplates::replacementsForOrder($order)['garments'];
        $this->assertStringContainsString('Premium Wedding Package', $garments);
        $this->assertStringContainsString('2x Two-Piece Suit', $garments);
        $this->assertStringContainsString('1x Socks Care', $garments);
        $this->assertStringContainsString('White Shirt', $garments);
    }

    /** @return array<string, mixed> */
    private function packageOrder(int $userId, bool $customized): array
    {
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Package Customer',
        ]);
        $garment = $this->catalogItem('Two-Piece Suit', OrderCatalogQuantityBehavior::Individual);
        $socks = $this->catalogItem('Socks Care', OrderCatalogQuantityBehavior::Bulk);
        $template = OrderPackageTemplate::create([
            'name' => 'Premium Wedding Package',
            'description' => 'Current template description.',
            'revision' => 3,
            'available_all_branches' => true,
        ]);
        $garmentComponent = $template->items()->create([
            'order_catalog_item_id' => $garment->id,
            'minimum_quantity' => 1,
            'default_quantity' => 2,
            'maximum_quantity' => 3,
            'package_unit_price' => 450000,
            'sort_order' => 1,
        ]);
        $socksComponent = $template->items()->create([
            'order_catalog_item_id' => $socks->id,
            'minimum_quantity' => 0,
            'default_quantity' => 2,
            'maximum_quantity' => 4,
            'package_unit_price' => 30000,
            'sort_order' => 2,
        ]);
        $order = $this->newOrder($customer, $userId);
        $configuredSocks = $customized ? 1 : 2;
        $originalSnapshot = [
            [
                'template_item_id' => $garmentComponent->id,
                'source_type' => 'catalog_item',
                'source_id' => $garment->id,
                'name' => 'Two-Piece Suit',
                'quantity_behavior' => 'individual',
                'default_quantity' => '2',
                'configured_quantity' => '2',
                'package_unit_price' => '450000.00',
            ],
            [
                'template_item_id' => $socksComponent->id,
                'source_type' => 'catalog_item',
                'source_id' => $socks->id,
                'name' => 'Socks Care',
                'quantity_behavior' => 'bulk',
                'default_quantity' => '2',
                'configured_quantity' => '2',
                'package_unit_price' => '30000.00',
            ],
        ];
        $configuredSnapshot = $originalSnapshot;
        $configuredSnapshot[1]['configured_quantity'] = (string) $configuredSocks;
        $configuredTotal = 900000 + ($configuredSocks * 30000);
        $instance = OrderPackageInstance::create([
            'order_id' => $order->id,
            'order_package_template_id' => $template->id,
            'source_template_revision' => 3,
            'package_name' => 'Premium Wedding Package',
            'package_description' => 'A complete historical wedding set.',
            'cover_image_path' => null,
            'original_package_total' => 960000,
            'configured_package_total' => $configuredTotal,
            'component_snapshot' => $configuredSnapshot,
            'original_component_snapshot' => $originalSnapshot,
            'configured_component_snapshot' => $configuredSnapshot,
            'configured_by' => $userId,
        ]);

        foreach ([1, 2] as $index) {
            $order->lines()->create([
                'order_catalog_item_id' => $garment->id,
                'order_package_instance_id' => $instance->id,
                'order_package_template_item_id' => $garmentComponent->id,
                'item_name' => 'Two-Piece Suit',
                'qty' => 1,
                'unit_price' => 450000,
                'line_total' => 450000,
                'meta' => ['package_unit_index' => $index],
            ]);
        }
        $socksLine = $order->lines()->create([
            'order_catalog_item_id' => $socks->id,
            'order_package_instance_id' => $instance->id,
            'order_package_template_item_id' => $socksComponent->id,
            'item_name' => 'Socks Care',
            'qty' => $configuredSocks,
            'unit_price' => 30000,
            'line_total' => $configuredSocks * 30000,
        ]);
        $order->lines()->create([
            'item_name' => 'White Shirt',
            'qty' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
        ]);
        $order->recalculateTotals();

        return [
            'order' => $order->fresh(),
            'template' => $template,
            'instance' => $instance,
            'socks_line' => $socksLine,
        ];
    }

    private function catalogItem(string $name, OrderCatalogQuantityBehavior $quantityBehavior): OrderCatalogItem
    {
        return OrderCatalogItem::create([
            'name' => $name,
            'type' => $quantityBehavior === OrderCatalogQuantityBehavior::Individual
                ? OrderCatalogItemType::Garment
                : OrderCatalogItemType::Service,
            'default_selling_price' => 100000,
            'requires_measurements' => $quantityBehavior === OrderCatalogQuantityBehavior::Individual,
            'quantity_behavior' => $quantityBehavior,
            'available_all_branches' => true,
        ]);
    }

    private function newOrder(Customer $customer, int $userId): Order
    {
        return Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'due_date' => now()->addWeek(),
            'created_by' => $userId,
        ]);
    }
}
