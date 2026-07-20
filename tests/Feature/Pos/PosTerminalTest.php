<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\PosTerminal;
use App\Models\Customer;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\PosSale;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Pos\PosSaleService;
use App\Services\Sms\SmsNotificationGate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PosTerminalTest extends TestCase
{
    public function test_authorized_user_can_view_pos_screen(): void
    {
        $this->actingAsRole('sales', $this->branch);

        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Point of Sale');
    }

    public function test_unauthorized_user_cannot_view_pos_screen(): void
    {
        $this->actingAsRole('tailor', $this->branch);

        $this->get(route('pos.index'))->assertForbidden();
    }

    public function test_item_can_be_added_to_sale_and_sale_is_completed(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 10, price: 2500);

        Livewire::actingAs($user)
            ->test(PosTerminal::class)
            ->call('addItem', $item->id)
            ->assertSet('amountPaid', 2500.0)
            ->set('amountPaid', 3000)
            ->set('paymentMethod', 'cash')
            ->call('completeSale')
            ->assertHasNoErrors()
            ->assertDispatched('open-pos-receipt');

        $sale = PosSale::query()->first();

        $this->assertNotNull($sale);
        $this->assertSame($user->id, $sale->user_id);
        $this->assertDatabaseHas('pos_sale_items', [
            'pos_sale_id' => $sale->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'unit_price' => 2500,
            'quantity' => 1,
            'line_total' => 2500,
        ]);
    }

    public function test_stock_is_deducted_after_sale(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 8, price: 1000);

        app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 3]],
            cashier: $user,
            customerId: null,
            discountAmount: 0,
            taxAmount: 0,
            amountPaid: 3000,
            paymentMethod: 'cash'
        );

        $this->assertSame(5.0, (float) $item->stock()->first()->qty_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'reference_type' => PosSale::class,
            'qty' => -3,
        ]);
    }

    public function test_sale_cannot_be_completed_with_insufficient_stock(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 1, price: 1000);

        $this->expectException(ValidationException::class);

        try {
            app(PosSaleService::class)->complete(
                cart: [['inventory_item_id' => $item->id, 'quantity' => 2]],
                cashier: $user,
                customerId: null,
                discountAmount: 0,
                taxAmount: 0,
                amountPaid: 2000,
                paymentMethod: 'cash'
            );
        } finally {
            $this->assertSame(1.0, (float) $item->stock()->first()->qty_on_hand);
            $this->assertDatabaseCount('pos_sales', 0);
        }
    }

    public function test_sale_can_be_completed_without_customer_as_walk_in(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 5, price: 1500);

        $sale = app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 1]],
            cashier: $user,
            customerId: null,
            discountAmount: 0,
            taxAmount: 0,
            amountPaid: 1500,
            paymentMethod: 'cash'
        );

        $this->assertNull($sale->customer_id);
        $this->get(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertSee('Walk-in Customer');
    }

    public function test_non_cash_sale_can_be_completed_without_payment_reference(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 5, price: 1500);

        $sale = app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 1]],
            cashier: $user,
            customerId: null,
            discountAmount: 0,
            taxAmount: 0,
            amountPaid: 1500,
            paymentMethod: 'mobile_money',
            paymentReference: null
        );

        $this->assertSame('mobile_money', $sale->payment_method);
        $this->assertNull($sale->payment_reference);
    }

    public function test_sale_can_be_completed_with_selected_customer(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 5, price: 1500);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $sale = app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 1]],
            cashier: $user,
            customerId: $customer->id,
            discountAmount: 0,
            taxAmount: 0,
            amountPaid: 1500,
            paymentMethod: 'cash'
        );

        $this->assertSame($customer->id, $sale->customer_id);
        $this->get(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertSee($customer->name);
    }

    public function test_new_customer_can_be_created_from_pos_flow(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);

        Livewire::actingAs($user)
            ->test(PosTerminal::class)
            ->call('openCustomerModal')
            ->set('newCustomerName', 'Jane Walkup')
            ->set('newCustomerPhone', '+255712345678')
            ->set('newCustomerEmail', 'jane@example.com')
            ->call('createCustomer')
            ->assertHasNoErrors()
            ->assertSet('selectedCustomerName', 'Jane Walkup');

        $this->assertDatabaseHas('customers', [
            'branch_id' => $this->branch->id,
            'name' => 'Jane Walkup',
            'phone' => '+255712345678',
        ]);
    }

    public function test_receipt_details_show_correct_totals(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 5, price: 2000);

        $sale = app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 2]],
            cashier: $user,
            customerId: null,
            discountAmount: 500,
            taxAmount: 0,
            amountPaid: 5000,
            paymentMethod: 'cash'
        );

        $this->get(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertSee($sale->sale_number)
            ->assertSee('Tsh 4,000')
            ->assertSee('Tsh 3,500')
            ->assertSee('Tsh 1,500');
    }

    public function test_pos_sale_completed_sms_template_is_logged_with_sale_variables(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $item = $this->createSellableItem(stock: 5, price: 2000);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Jane Buyer',
            'phone' => '+255712345678',
        ]);

        $this->assertArrayHasKey('pos_sale_completed', SmsTemplate::defaultTemplates());
        $this->assertContains('sale_number', SmsTemplate::variablesForCategory('pos_sale_completed'));

        $sale = app(PosSaleService::class)->complete(
            cart: [['inventory_item_id' => $item->id, 'quantity' => 2]],
            cashier: $user,
            customerId: $customer->id,
            discountAmount: 500,
            taxAmount: 0,
            amountPaid: 5000,
            paymentMethod: 'cash'
        );

        $log = SmsLog::query()
            ->where('template_code', 'pos_sale_completed')
            ->where('reference_type', PosSale::class)
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(SmsNotificationGate::SMS_GLOBAL_DISABLED, $log->skip_reason);
        $this->assertStringContainsString('Jane Buyer', $log->message);
        $this->assertStringContainsString($sale->sale_number, $log->message);
        $this->assertStringContainsString($item->name.' x2', $log->message);
        $this->assertStringContainsString('Tsh 3,500', $log->message);
        $this->assertStringContainsString('Tsh 5,000', $log->message);
    }

    protected function createSellableItem(float $stock, float $price): InventoryItem
    {
        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);

        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
            'default_sell_price' => $price,
            'is_active' => true,
        ]);

        $item->stock()->update([
            'qty_on_hand' => $stock,
            'qty_reserved' => 0,
        ]);

        return $item->refresh();
    }
}
