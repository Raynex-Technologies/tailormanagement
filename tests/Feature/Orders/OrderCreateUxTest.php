<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderCatalogItemType;
use App\Livewire\Orders\Form as OrderForm;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\PaymentMethod;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateUxTest extends TestCase
{
    public function test_compact_order_setup_and_empty_contents_render_for_global_and_branch_fixed_users(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $global = Livewire::test(OrderForm::class)
            ->assertSet('lines', [])
            ->assertSet('order_expenses', [])
            ->assertSee('Order Setup')
            ->assertSeeHtml('data-order-branch-selector')
            ->assertSeeHtml('data-order-contents-empty')
            ->assertSeeHtml('data-order-commercial-summary')
            ->assertSeeHtml('data-order-additional-information')
            ->assertSeeHtml('data-order-deposit')
            ->assertDontSee('Branch Assignment')
            ->assertDontSee('Order Details');

        $html = $global->html();
        $this->assertLessThan(strpos($html, 'data-order-contents-empty'), strpos($html, 'data-order-setup'));
        $this->assertLessThan(strpos($html, 'data-order-commercial-summary'), strpos($html, 'data-order-contents-empty'));
        $this->assertSame(1, substr_count($html, 'data-form-actions="order"'));
        $this->assertStringNotContainsString('form="order-form"', $html);
        $this->assertStringContainsString('data-order-commercial-footer', $html);
        $this->assertStringNotContainsString('ml-auto w-full max-w-sm', $html);
        $this->assertStringContainsString('data-order-notes', $html);
        $this->assertStringContainsString('data-order-expenses', $html);
        $this->assertStringContainsString('rows="2"', $html);
        $this->assertStringContainsString('px-4 py-5 text-center', $html);
        $this->assertStringNotContainsString('Controls customer, catalog, inventory and tailor availability.', $html);
        $this->assertStringContainsString('Sets available order options.', $html);
        $this->assertStringContainsString('aria-label="Order branch"', $html);
        $this->assertStringContainsString('aria-label="Search existing customer"', $html);
        $this->assertStringContainsString('id="order-customer-heading" class="sr-only"', $html);
        $this->assertStringContainsString('items-center justify-center gap-2 self-end', $html);
        $this->assertStringContainsString('data-money-input', $html);
        $this->assertStringContainsString('inputmode="decimal"', $html);

        auth()->logout();
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrderForm::class)
            ->assertDontSeeHtml('data-order-branch-selector')
            ->assertSeeHtml('data-order-branch-context')
            ->assertSee('Branch: Main Branch');
    }

    public function test_customer_search_is_branch_scoped_and_selected_customer_is_compact(): void
    {
        $allowed = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Scoped Customer',
            'phone' => '+255700111222',
            'email' => 'scoped@example.test',
        ]);
        Customer::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'name' => 'Scoped Customer Other Branch',
        ]);
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrderForm::class)
            ->set('customerSearch', 'Scoped Customer')
            ->assertSee('Scoped Customer')
            ->assertDontSee('Scoped Customer Other Branch')
            ->call('selectCustomer', $allowed->id)
            ->assertSet('customer_id', $allowed->id)
            ->assertSeeHtml('data-selected-customer')
            ->assertSee('+255700111222')
            ->assertSee('scoped@example.test')
            ->call('clearSelectedCustomer')
            ->assertSet('customer_id', null)
            ->assertSet('customerSearch', '');
    }

    public function test_inline_new_customer_disclosure_and_validation_remain_functional(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrderForm::class)
            ->call('toggleNewCustomerForm')
            ->assertSet('showNewCustomerForm', true)
            ->assertSeeHtml('data-new-customer-form')
            ->assertSeeHtml('aria-expanded="true"')
            ->call('addLine')
            ->set('lines.0.item_name', 'Custom suit')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 100000)
            ->call('save')
            ->assertHasErrors(['newCustomerName', 'newCustomerPhone'])
            ->assertSet('showNewCustomerForm', true)
            ->call('toggleNewCustomerForm')
            ->assertSet('showNewCustomerForm', false)
            ->assertSet('newCustomerName', '')
            ->assertSet('newCustomerPhone', '');
    }

    public function test_successful_branch_change_clears_stale_customer_and_picker_state(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Previous Branch Customer',
        ]);
        Customer::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'name' => 'New Branch Customer',
        ]);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->set('assigned_tailor_id', $tailor->id)
            ->call('toggleCatalogPicker')
            ->set('catalogSearch', 'previous results')
            ->set('inventorySearch', 'previous inventory')
            ->set('showDirectCatalogConfigurator', true)
            ->set('selectedCatalogItemId', 999999)
            ->set('showPackageConfigurator', true)
            ->set('configuringPackageKey', 'stale-package')
            ->set('packageConfigurator', ['name' => 'Stale package'])
            ->set('packageQuantities', [1 => 2])
            ->set('branch_id', $this->otherBranch->id)
            ->assertSet('branch_id', $this->otherBranch->id)
            ->assertSet('customer_id', null)
            ->assertSet('customerSearch', '')
            ->assertSet('showCustomerDropdown', false)
            ->assertSet('assigned_tailor_id', null)
            ->assertSet('showCatalogPicker', false)
            ->assertSet('catalogSearch', '')
            ->assertSet('inventorySearch', '')
            ->assertSet('showDirectCatalogConfigurator', false)
            ->assertSet('selectedCatalogItemId', null)
            ->assertSet('showPackageConfigurator', false)
            ->assertSet('configuringPackageKey', null)
            ->assertSet('packageConfigurator', [])
            ->assertSet('packageQuantities', [])
            ->set('customerSearch', 'New Branch Customer')
            ->assertSee('New Branch Customer')
            ->assertDontSee('Previous Branch Customer');
    }

    public function test_branch_change_does_not_discard_an_unsaved_new_customer_draft(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(OrderForm::class)
            ->call('toggleNewCustomerForm')
            ->set('newCustomerName', 'Unsaved Customer')
            ->set('newCustomerPhone', '+255700123456')
            ->set('branch_id', $this->otherBranch->id)
            ->assertSet('branch_id', $this->branch->id)
            ->assertSet('showNewCustomerForm', true)
            ->assertSet('newCustomerName', 'Unsaved Customer')
            ->assertHasErrors('branch_id');
    }

    public function test_cross_branch_customer_id_is_rejected_even_if_livewire_state_is_tampered(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $otherCustomer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $otherCustomer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Tamper-resistant order')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 50000)
            ->call('save')
            ->assertHasErrors('customer_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_only_explicit_actions_create_lines_from_the_empty_state(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $catalogItem = OrderCatalogItem::create([
            'name' => 'Catalog Suit',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '250000.00',
            'requires_measurements' => true,
            'available_all_branches' => true,
        ]);

        Livewire::test(OrderForm::class)
            ->assertSet('lines', [])
            ->call('addLine')
            ->assertCount('lines', 1)
            ->call('removeLine', 0)
            ->assertSet('lines', [])
            ->call('toggleCatalogPicker')
            ->call('setCatalogTab', 'catalog')
            ->assertSee('Catalog Suit')
            ->call('configureDirectCatalogItem', $catalogItem->id)
            ->call('confirmDirectCatalogItem')
            ->assertCount('lines', 1)
            ->assertSet('lines.0.order_catalog_item_id', $catalogItem->id)
            ->assertSet('lines.0.item_name', 'Catalog Suit');
    }

    public function test_compact_create_and_edit_sections_preserve_financial_and_secondary_data(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $paymentMethod = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Cash'],
            ['is_enabled' => true]
        );

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Compact setup suit')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', '1,250,000.50')
            ->set('discount', '250,000.25')
            ->set('notes', 'Notes remain additional information.')
            ->call('addOrderExpense')
            ->set('order_expenses.0.notes', 'Labour Charge')
            ->set('order_expenses.0.amount', '12,000.50')
            ->set('deposit_amount', '500,000.25')
            ->set('deposit_payment_method_id', $paymentMethod->id)
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->with(['lines', 'orderExpenses', 'payments'])->latest('id')->firstOrFail();
        $this->assertSame('1000000.25', (string) $order->total);
        $this->assertSame('Notes remain additional information.', $order->notes);
        $this->assertSame('12000.50', (string) $order->orderExpenses->sole()->amount);
        $this->assertSame('500000.25', (string) $order->payments->sole()->amount);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->assertSet('isEdit', true)
            ->assertCount('lines', 1)
            ->assertSeeHtml('data-selected-customer')
            ->assertSeeHtml('data-order-branch-context')
            ->assertDontSeeHtml('data-order-branch-selector')
            ->assertDontSeeHtml('data-order-deposit')
            ->set('notes', 'Updated from compact edit.')
            ->set('lines.0.unit_price', '1,300,000.50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated from compact edit.', $order->fresh()->notes);
        $this->assertSame('1050000.25', (string) $order->fresh()->total);
        $this->assertSame($user->id, $order->created_by);
    }
}
