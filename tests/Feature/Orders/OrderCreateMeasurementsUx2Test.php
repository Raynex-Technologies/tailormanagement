<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Livewire\Orders\Form as OrderForm;
use App\Models\Customer;
use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Services\Orders\OrderMeasurementSavebackService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OrderCreateMeasurementsUx2Test extends TestCase
{
    public function test_new_customer_modal_persists_immediately_selects_customer_and_retains_invalid_state(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        $component = Livewire::test(OrderForm::class)
            ->call('openNewCustomerModal')
            ->assertSet('showNewCustomerModal', true)
            ->assertSeeHtml('data-new-customer-modal')
            ->set('newCustomerName', 'Joachim Malesa')
            ->set('newCustomerPhone', '+255700123456')
            ->set('newCustomerEmail', 'invalid-email')
            ->set('newCustomerAddress', 'Dar es Salaam')
            ->call('createNewCustomer')
            ->assertHasErrors('newCustomerEmail')
            ->assertSet('newCustomerName', 'Joachim Malesa')
            ->assertSet('showNewCustomerModal', true)
            ->set('newCustomerEmail', 'joachim@example.test')
            ->call('createNewCustomer')
            ->assertHasNoErrors()
            ->assertSet('showNewCustomerModal', false)
            ->assertSet('customerSearch', 'Joachim Malesa')
            ->assertSeeHtml('data-selected-customer');

        $customer = Customer::query()->where('email', 'joachim@example.test')->sole();
        $this->assertSame($this->branch->id, $customer->branch_id);
        $component->assertSet('customer_id', $customer->id);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        Livewire::test(OrderForm::class)
            ->call('openNewCustomerModal')
            ->set('newCustomerName', 'Duplicate Phone')
            ->set('newCustomerPhone', '+255700123456')
            ->call('createNewCustomer')
            ->assertHasErrors('newCustomerPhone')
            ->assertSet('showNewCustomerModal', true);
    }

    public function test_immediately_created_customer_saves_order_without_false_branch_error(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrderForm::class)
            ->call('openNewCustomerModal')
            ->set('newCustomerName', 'Immediate Customer')
            ->set('newCustomerPhone', '+255710111222')
            ->call('createNewCustomer')
            ->call('addLine')
            ->set('lines.0.item_name', 'Custom suit')
            ->set('lines.0.qty', '1')
            ->set('lines.0.unit_price', '125,000')
            ->call('save')
            ->assertHasNoErrors(['customer_id', 'save']);

        $customer = Customer::query()->where('phone', '+255710111222')->sole();
        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_shared_live_money_markup_and_authoritative_exact_line_totals(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Three-piece suit')
            ->set('lines.0.qty', '3')
            ->set('lines.0.unit_price', '780,000')
            ->assertSet('lines.0.unit_price', '780000')
            ->assertSet('lines.0.line_total', 2340000.0)
            ->assertSeeHtml('data-line-total-preview')
            ->assertSee('multiplyAndFormat', escape: false)
            ->set('lines.0.qty', '1.5')
            ->set('lines.0.unit_price', '1,250,000.50')
            ->assertSet('lines.0.line_total', 1875000.75)
            ->call('save')
            ->assertHasNoErrors();

        $line = Order::query()->latest('id')->firstOrFail()->lines()->sole();
        $this->assertSame('1250000.50', (string) $line->unit_price);
        $this->assertSame('1875000.75', (string) $line->line_total);

        $script = file_get_contents(resource_path('js/modules/money-inputs.js'));
        $this->assertStringContainsString("addEventListener('input'", $script);
        $this->assertStringContainsString('setSelectionRange', $script);
        $this->assertStringContainsString('BigInt', $script);
        $this->assertStringContainsString('tailor-money-input', $script);
        $this->assertStringNotContainsString("addEventListener('focusin'", $script);
    }

    public function test_measurement_modal_is_compact_uses_definition_units_and_updates_only_target_garment(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $category = $this->category('Suit');
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST', 'default_unit' => 'in', 'unit' => 'in']);
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST', 'default_unit' => 'cm', 'unit' => 'cm']);
        $category->measurementFields()->attach($chest->id, ['is_required' => true, 'sort_order' => 1]);
        $category->measurementFields()->attach($waist->id, ['is_required' => false, 'sort_order' => 2]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);

        $component = Livewire::test(OrderForm::class)
            ->call('configureDirectCatalogItem', $garment->id)
            ->set('directCatalogQuantity', '2')
            ->call('confirmDirectCatalogItem')
            ->assertSee('+ Add Measurements')
            ->assertSee('No measurements')
            ->assertDontSee('CHEST')
            ->call('openMeasurementModal', 1)
            ->assertSet('showMeasurementModal', true)
            ->assertSet('measurementModalLineIndex', 1)
            ->assertSeeHtml('data-compact-measurement-grid')
            ->assertSee('Chest')
            ->assertSee('Required')
            ->assertDontSee('CHEST');

        $rows = collect($component->get('measurementDraft.measurements'))->keyBy('measurement_field_id');
        $this->assertSame('in', $rows[$chest->id]['unit']);
        $this->assertSame('cm', $rows[$waist->id]['unit']);

        $chestIndex = collect($component->get('measurementDraft.measurements'))->search(
            fn (array $row): bool => (int) $row['measurement_field_id'] === $chest->id,
        );
        $component
            ->set("measurementDraft.measurements.$chestIndex.value", '42.00')
            ->call('applyMeasurementModal')
            ->assertSet('showMeasurementModal', false)
            ->assertSet('lines.1.measurements.'.$chestIndex.'.value', '42.00')
            ->assertSet('lines.0.measurements.'.$chestIndex.'.value', '');
    }

    public function test_saved_profile_unit_is_preserved_in_modal_and_unit_override_requires_confirmation(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $category = $this->category('Trouser');
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST', 'default_unit' => 'cm', 'unit' => 'cm']);
        $category->measurementFields()->attach($waist->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $profile = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $waist->id, 'value' => '36.00', 'unit' => 'in'],
        ], [], now(), $recorder);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->call('openMeasurementModal', 0)
            ->set('measurementDraft.measurement_profile_selection', (string) $profile->id)
            ->call('applySelectedDraftMeasurementProfile');

        $this->assertSame('in', $component->get('measurementDraft.measurements.0.unit'));
        $this->assertSame('36.00', $component->get('measurementDraft.measurements.0.value'));
        $component
            ->set('measurementDraft.measurements.0.unit', 'cm')
            ->call('applyMeasurementModal')
            ->assertHasErrors('measurementDraft.measurements.0.unit_change_confirmed')
            ->set('measurementDraft.measurements.0.unit_change_confirmed', true)
            ->call('applyMeasurementModal')
            ->assertHasNoErrors()
            ->assertSet('lines.0.measurements.0.unit', 'cm')
            ->assertSet('lines.0.measurements.0.value', '36.00');
    }

    public function test_saveback_proposals_merge_consistent_and_non_overlapping_values_but_never_choose_conflicts(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $neck = $this->field(['name' => 'Neck', 'code' => 'NECK']);
        $service = app(OrderMeasurementSavebackService::class);

        $proposal = $service->proposal($customer, [
            $this->candidateLine('Suit', $waist, '84.00', 'cm'),
            $this->candidateLine('Trouser', $waist, '84', 'cm'),
            $this->candidateLine('Shirt', $neck, '40.00', 'cm'),
        ], false);
        $this->assertCount(2, $proposal['changes']);
        $this->assertSame([], $proposal['conflicts']);

        $conflict = $service->proposal($customer, [
            $this->candidateLine('Suit #1', $waist, '84.00', 'cm'),
            $this->candidateLine('Suit #2', $waist, '86.00', 'cm'),
        ], false);
        $this->assertSame([], $conflict['changes']);
        $this->assertCount(1, $conflict['conflicts']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->resolvedChanges($conflict, []);
    }

    public function test_saveback_conflict_requires_an_explicit_modal_choice_before_order_persistence(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST']);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Suit')
            ->set('lines.0.qty', '1')
            ->set('lines.0.unit_price', '1000')
            ->set('lines.0.measurements', [$this->candidateLine('Suit', $waist, '84.00', 'cm')['measurements'][0]])
            ->call('addLine')
            ->set('lines.1.item_name', 'Trouser')
            ->set('lines.1.qty', '1')
            ->set('lines.1.unit_price', '1000')
            ->set('lines.1.measurements', [$this->candidateLine('Trouser', $waist, '86.00', 'cm')['measurements'][0]])
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true)
            ->assertSee('Choose reusable value')
            ->call('saveOrderWithCustomerMeasurements')
            ->assertHasErrors("customerMeasurementConflictChoices.$waist->id");

        $this->assertDatabaseCount('orders', 0);

        $component
            ->set("customerMeasurementConflictChoices.$waist->id", 'option:1')
            ->call('saveOrderWithCustomerMeasurements')
            ->assertHasNoErrors();

        $this->assertSame('86.00', $customer->fresh()->currentMeasurementProfile->values()->sole()->value);
    }

    public function test_explicit_saveback_creates_immutable_revision_preserves_source_and_unchanged_copy_does_not_prompt(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $category = $this->category('Suit');
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $category->measurementFields()->attach($waist->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $original = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $waist->id, 'value' => '84.00', 'unit' => 'cm'],
        ], [], now()->subMonth(), $recorder);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->call('openMeasurementModal', 0)
            ->set('measurementDraft.measurement_profile_selection', (string) $original->id)
            ->call('applySelectedDraftMeasurementProfile')
            ->set('measurementDraft.measurements.0.value', '86.00')
            ->call('applyMeasurementModal')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true)
            ->assertSee('Update Customer Measurements')
            ->call('saveOrderWithCustomerMeasurements')
            ->assertHasNoErrors();

        $profiles = MeasurementProfile::query()->where('customer_id', $customer->id)->orderBy('revision')->get();
        $this->assertCount(2, $profiles);
        $this->assertSame('84.00', $profiles[0]->values()->sole()->value);
        $this->assertFalse($profiles[0]->fresh()->is_current);
        $this->assertSame('86.00', $profiles[1]->values()->sole()->value);
        $this->assertTrue($profiles[1]->is_current);
        $measurement = Order::query()->latest('id')->firstOrFail()->lines()->sole()->measurement;
        $this->assertSame($original->id, $measurement->source_measurement_profile_id);

        $order = $measurement->orderLine->order;
        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('notes', 'Historical measurements were not edited.')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', false)
            ->assertHasNoErrors();

        Livewire::test(OrderForm::class, ['order' => $order->fresh()])
            ->set('lines.0.measurements.0.value', '87.00')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true);

        Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->call('openMeasurementModal', 0)
            ->set('measurementDraft.measurement_profile_selection', (string) $profiles[1]->id)
            ->call('applySelectedDraftMeasurementProfile')
            ->call('applyMeasurementModal')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', false)
            ->assertHasNoErrors();
    }

    public function test_save_order_only_and_transaction_failure_never_update_customer_profile(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $original = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $field->id, 'value' => '84.00', 'unit' => 'cm'],
        ], [], now(), $recorder);

        Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Custom garment')
            ->set('lines.0.qty', '1')
            ->set('lines.0.unit_price', '1000')
            ->set('lines.0.measurements', [[
                'measurement_field_id' => $field->id,
                'label' => 'Waist',
                'value' => '86.00',
                'unit' => 'cm',
                'is_custom' => false,
            ]])
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true)
            ->call('saveOrderOnly')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('measurement_profiles', 1);

        Event::listen(OrderCreated::class, fn () => throw new RuntimeException('Simulated downstream order failure.'));
        Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Rollback garment')
            ->set('lines.0.qty', '1')
            ->set('lines.0.unit_price', '1000')
            ->set('lines.0.measurements', [[
                'measurement_field_id' => $field->id,
                'label' => 'Waist',
                'value' => '88.00',
                'unit' => 'cm',
                'is_custom' => false,
            ]])
            ->call('save')
            ->call('saveOrderWithCustomerMeasurements')
            ->assertHasErrors('save');

        $this->assertDatabaseCount('measurement_profiles', 1);
        $this->assertSame('84.00', $original->fresh()->values()->sole()->value);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_orders_sidebar_quick_add_is_authorized_and_desktop_only(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeHtml('data-orders-quick-add')
            ->assertSee('aria-label="Create Order"', escape: false);

        $this->actingAsRole('storekeeper', $this->branch);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-orders-quick-add', escape: false);

        $sidebar = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));
        $this->assertSame(1, substr_count($sidebar, 'data-orders-quick-add'));
        $this->assertStringContainsString('.desktop-sidebar.is-collapsed .nav-group > h3', $sidebar);
    }

    /** @param array<string, mixed> $overrides */
    private function field(array $overrides = []): MeasurementField
    {
        $name = $overrides['name'] ?? 'Waist';

        return MeasurementField::query()->create(array_merge([
            'name' => $name,
            'slug' => str($name)->slug(),
            'code' => str($name)->snake()->upper(),
            'unit' => 'cm',
            'default_unit' => 'cm',
            'instructions' => 'Measure around the body.',
            'is_global' => false,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    private function category(string $name): GarmentCategory
    {
        return GarmentCategory::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function catalogItem(array $overrides = []): OrderCatalogItem
    {
        return OrderCatalogItem::query()->create(array_merge([
            'name' => 'Bespoke Garment',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '100000.00',
            'requires_measurements' => true,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Individual,
            'available_all_branches' => true,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function candidateLine(string $label, MeasurementField $field, string $value, string $unit): array
    {
        return [
            'id' => null,
            'item_name' => $label,
            'measurements' => [[
                'measurement_field_id' => $field->id,
                'label' => $field->name,
                'value' => $value,
                'unit' => $unit,
                'is_custom' => false,
            ]],
        ];
    }

    private function order(Customer $customer, int $creatorId): Order
    {
        return Order::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'order_date' => now()->toDateString(),
            'created_by' => $creatorId,
        ]);
    }
}
