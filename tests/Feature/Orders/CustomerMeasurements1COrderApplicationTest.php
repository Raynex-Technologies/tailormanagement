<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Orders\Form as OrderForm;
use App\Models\Customer;
use App\Models\GarmentCategory;
use App\Models\InventoryItem;
use App\Models\MeasurementField;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderMeasurement;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Services\Orders\OrderCatalogCompositionService;
use App\Services\Orders\OrderMeasurementService;
use App\Support\Orders\OrderMeasurementSnapshot;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerMeasurements1COrderApplicationTest extends TestCase
{
    public function test_categorized_garment_prepopulates_required_fields_in_deterministic_order_and_default_units(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $category = $this->category('Suit');
        $optionalEarly = $this->field(['name' => 'Neck', 'code' => 'NECK', 'default_unit' => 'in', 'unit' => 'in']);
        $requiredLater = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $requiredEarly = $this->field(['name' => 'Chest', 'code' => 'CHEST', 'default_unit' => 'in', 'unit' => 'in']);
        $category->measurementFields()->attach($optionalEarly->id, ['is_required' => false, 'sort_order' => 1]);
        $category->measurementFields()->attach($requiredLater->id, ['is_required' => true, 'sort_order' => 20]);
        $category->measurementFields()->attach($requiredEarly->id, ['is_required' => true, 'sort_order' => 10]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);

        $component = Livewire::test(OrderForm::class)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem');

        $rows = $component->get('lines.0.measurements');
        $this->assertSame([$requiredEarly->id, $requiredLater->id, $optionalEarly->id], collect($rows)->pluck('measurement_field_id')->all());
        $this->assertSame([true, true, false], collect($rows)->pluck('required')->all());
        $this->assertSame(['in', 'cm', 'in'], collect($rows)->pluck('unit')->all());
        $component
            ->assertSee('No measurements')
            ->call('openMeasurementModal', 0)
            ->assertSee('Required measurement missing')
            ->assertSee('Change unit')
            ->call('cancelMeasurementModal');

        $serviceItem = $this->catalogItem([
            'name' => 'Pressing',
            'type' => OrderCatalogItemType::Service,
            'requires_measurements' => false,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Bulk,
            'garment_category_id' => null,
        ]);
        $inventory = InventoryItem::factory()->create(['branch_id' => $this->branch->id]);
        $initialized = app(OrderMeasurementService::class)->initializeLineTemplates([
            ['order_catalog_item_id' => $serviceItem->id, 'inventory_item_id' => null, 'requires_measurements' => false],
            ['order_catalog_item_id' => null, 'inventory_item_id' => $inventory->id, 'requires_measurements' => false],
        ]);
        $this->assertFalse($initialized[0]['measurement_enabled']);
        $this->assertFalse($initialized[1]['measurement_enabled']);
    }

    public function test_package_and_multiple_physical_garments_receive_independent_templates(): void
    {
        $category = $this->category('Jacket');
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $category->measurementFields()->attach($chest->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $snapshot = [
            'components' => [[
                'source_type' => 'catalog_item',
                'source_id' => $garment->id,
                'source_code' => $garment->code,
                'template_item_id' => 100,
                'name' => 'Package Jacket',
                'quantity_behavior' => 'individual',
                'configured_quantity' => '2',
                'package_unit_price' => '100000.00',
                'package_line_total' => '200000.00',
                'requires_measurements' => true,
                'catalog_item_type' => 'garment',
                'garment_category_id' => $category->id,
            ]],
        ];

        $lines = app(OrderCatalogCompositionService::class)->packageLines($snapshot, 'package-key');
        $lines = app(OrderMeasurementService::class)->initializeLineTemplates($lines);

        $this->assertCount(2, $lines);
        $this->assertSame('1', $lines[0]['qty']);
        $this->assertSame('1', $lines[1]['qty']);
        $this->assertSame($chest->id, $lines[0]['measurements'][0]['measurement_field_id']);
        $lines[0]['measurements'][0]['value'] = '40.00';
        $this->assertSame('', $lines[1]['measurements'][0]['value']);
        $this->assertSame([1, 2], collect($lines)->pluck('package_unit_index')->all());
    }

    public function test_current_and_older_profiles_apply_by_field_id_without_irrelevant_values_or_conversion(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $category = $this->category('Shirt');
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $sleeve = $this->field(['name' => 'Sleeve Length', 'code' => 'SLEEVE_LENGTH']);
        $weight = $this->field(['name' => 'Weight', 'code' => 'WEIGHT', 'default_unit' => 'kg', 'unit' => 'kg']);
        $category->measurementFields()->attach($chest->id, ['is_required' => true, 'sort_order' => 1]);
        $category->measurementFields()->attach($sleeve->id, ['is_required' => true, 'sort_order' => 2]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $profiles = app(CustomerMeasurementProfileService::class);
        $older = $profiles->createRevision($customer, [
            ['measurement_field_id' => $chest->id, 'value' => '42.25', 'unit' => 'in'],
            ['measurement_field_id' => $weight->id, 'value' => '74.00', 'unit' => 'kg'],
        ], [], now()->subMonth(), $recorder);
        $current = $profiles->createRevision($customer, [
            ['measurement_field_id' => $chest->id, 'value' => '108.00', 'unit' => 'cm'],
        ], [$weight->id], now(), $recorder);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->set('lines.0.measurement_profile_selection', (string) $older->id)
            ->call('applySelectedMeasurementProfile', 0);

        $rows = collect($component->get('lines.0.measurements'))->keyBy('measurement_field_id');
        $this->assertSame('42.25', $rows[$chest->id]['value']);
        $this->assertSame('in', $rows[$chest->id]['unit']);
        $this->assertSame('', $rows[$sleeve->id]['value']);
        $this->assertFalse($rows->has($weight->id));
        $this->assertSame($older->id, $component->get('lines.0.measurement_source_profile_id'));

        $component->set('lines.0.measurements.0.value', '43.00');
        $this->assertSame('42.25', $older->values()->where('measurement_field_id', $chest->id)->firstOrFail()->value);

        $component->set('lines.0.measurement_profile_selection', (string) $current->id)
            ->call('applySelectedMeasurementProfile', 0)
            ->assertSet('lines.0.measurement_pending_profile_id', $current->id)
            ->call('confirmApplyMeasurementProfile', 0)
            ->assertSet('lines.0.measurement_source_profile_id', $current->id);
        $this->assertSame('108.00', collect($component->get('lines.0.measurements'))->keyBy('measurement_field_id')[$chest->id]['value']);
    }

    public function test_structured_snapshot_provenance_and_order_show_survive_source_unavailability_without_document_exposure(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $category = $this->category('Suit');
        $waist = $this->field(['name' => 'Natural Waist', 'code' => 'WAIST', 'default_unit' => 'in', 'unit' => 'in']);
        $category->measurementFields()->attach($waist->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $profile = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $waist->id, 'value' => '36.00', 'unit' => 'in'],
        ], [], now()->subDay(), $recorder);
        $smsBefore = DB::table('sms_logs')->count();
        $whatsAppBefore = DB::table('whatsapp_messages')->count();

        Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->set('lines.0.measurement_profile_selection', (string) $profile->id)
            ->call('applySelectedMeasurementProfile', 0)
            ->set('lines.0.measurements.0.value', '36.50')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true)
            ->call('saveOrderOnly')
            ->assertHasNoErrors();

        $order = Order::query()->with('lines.measurement')->latest('id')->firstOrFail();
        $measurement = $order->lines->sole()->measurement;
        $entry = $measurement->measurements['entries'][0];
        $this->assertSame(2, $measurement->measurements['version']);
        $this->assertSame($waist->id, $entry['measurement_field_id']);
        $this->assertSame('WAIST', $entry['code']);
        $this->assertSame('Natural Waist', $entry['label']);
        $this->assertSame('36.50', $entry['value']);
        $this->assertSame('in', $entry['unit']);
        $this->assertSame($profile->id, $measurement->source_measurement_profile_id);
        $this->assertSame($profile->lineage_uuid, $measurement->source_profile_lineage);
        $this->assertSame($profile->revision, $measurement->source_profile_revision);
        $this->assertSame('36.00', $profile->values()->sole()->value);
        $this->assertDatabaseMissing('invoice_lines', ['notes' => 'Natural Waist']);
        $this->assertDatabaseMissing('sms_logs', ['message' => 'Natural Waist']);
        $this->assertDatabaseMissing('whatsapp_messages', ['body' => 'Natural Waist']);
        $this->assertGreaterThanOrEqual($smsBefore, DB::table('sms_logs')->count());
        $this->assertGreaterThanOrEqual($whatsAppBefore, DB::table('whatsapp_messages')->count());

        DB::table('measurement_profiles')->where('id', $profile->id)->delete();
        $this->assertNull($measurement->fresh()->source_measurement_profile_id);
        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Natural Waist')
            ->assertSee('36.50')
            ->assertSee('in')
            ->assertSee('Based on customer measurements');
        $this->get(route('invoices.show', $order->invoice))->assertDontSee('Natural Waist');
    }

    public function test_manual_canonical_and_custom_measurements_persist_and_invalid_or_duplicate_rows_are_rejected(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $hip = $this->field(['name' => 'Hip', 'code' => 'HIP']);
        $archived = $this->field(['name' => 'Archived Rise', 'code' => 'ARCHIVED_RISE', 'is_active' => false]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Custom ceremonial garment')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 100000)
            ->call('openMeasurementModal', 0)
            ->assertSee('Hip')
            ->assertDontSee('HIP')
            ->assertDontSee('Archived Rise')
            ->set('measurementDraft.measurement_field_selection', (string) $hip->id)
            ->call('addDraftMeasurement')
            ->set('measurementDraft.measurements.0.value', '101.50')
            ->set('measurementDraft.measurement_field_selection', 'custom')
            ->call('addDraftMeasurement')
            ->set('measurementDraft.measurements.1.label', 'Front Rise Special')
            ->set('measurementDraft.measurements.1.value', '11.25')
            ->set('measurementDraft.measurements.1.unit', 'in')
            ->set('measurementDraft.measurements.1.unit_change_confirmed', true)
            ->call('applyMeasurementModal')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', true)
            ->call('saveOrderOnly')
            ->assertHasNoErrors();

        $entries = Order::query()->latest('id')->firstOrFail()->lines()->sole()->measurement->measurements['entries'];
        $this->assertSame([$hip->id, null], collect($entries)->pluck('measurement_field_id')->all());
        $this->assertSame([false, true], collect($entries)->pluck('is_custom')->all());

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Invalid measurement garment')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 1)
            ->set('lines.0.measurements', [
                ['measurement_field_id' => $hip->id, 'label' => 'Hip', 'value' => '100.00', 'unit' => 'cm', 'is_custom' => false],
                ['measurement_field_id' => $hip->id, 'label' => 'Hip', 'value' => '101.00', 'unit' => 'cm', 'is_custom' => false],
            ])
            ->call('save')
            ->assertHasErrors(['lines.0.measurements.1.measurement_field_id']);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Invalid custom measurement garment')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 1)
            ->set('lines.0.measurements', [
                ['measurement_field_id' => null, 'label' => '', 'value' => 'Slim Fit', 'unit' => 'metres', 'is_custom' => true],
            ])
            ->call('save')
            ->assertHasErrors(['lines.0.measurements.0.label']);
    }

    public function test_customer_change_clears_unsaved_profile_values_and_cross_customer_or_branch_profiles_cannot_be_applied(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customerA = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Customer A']);
        $customerB = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Customer B']);
        $otherBranchCustomer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $category = $this->category('Suit');
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $category->measurementFields()->attach($waist->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);
        $profileA = app(CustomerMeasurementProfileService::class)->createRevision($customerA, [
            ['measurement_field_id' => $waist->id, 'value' => '90.00', 'unit' => 'cm'],
        ], [], now(), $recorder);
        $otherProfile = app(CustomerMeasurementProfileService::class)->createRevision($otherBranchCustomer, [
            ['measurement_field_id' => $waist->id, 'value' => '99.00', 'unit' => 'cm'],
        ], [], now(), $recorder);

        $component = Livewire::test(OrderForm::class)
            ->call('selectCustomer', $customerA->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->set('lines.0.measurement_profile_selection', (string) $profileA->id)
            ->call('applySelectedMeasurementProfile', 0)
            ->call('selectCustomer', $customerB->id)
            ->assertSet('lines.0.measurement_source_profile_id', null)
            ->assertSet('lines.0.measurements.0.value', '');

        $component->set('lines.0.measurement_profile_selection', (string) $profileA->id)
            ->call('applySelectedMeasurementProfile', 0)
            ->assertHasErrors('lines.0.measurement_profile_selection');
        $component->set('lines.0.measurement_profile_selection', (string) $otherProfile->id)
            ->call('applySelectedMeasurementProfile', 0)
            ->assertHasErrors('lines.0.measurement_profile_selection');
    }

    public function test_order_edit_preserves_untouched_legacy_and_structured_snapshots_but_converts_edited_legacy_safely(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $order = $this->order($customer, $user->id);
        $structuredLine = $order->lines()->create([
            'item_name' => 'Structured jacket', 'qty' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);
        $legacyLine = $order->lines()->create([
            'item_name' => 'Legacy trouser', 'qty' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);
        $structuredState = array_merge(app(OrderMeasurementSnapshot::class)->emptyEditorState(), [
            'id' => $structuredLine->id,
            'measurements' => [[
                'measurement_field_id' => $field->id, 'code' => 'CHEST', 'label' => 'Chest',
                'value' => '42.00', 'unit' => 'in', 'is_custom' => false,
            ]],
        ]);
        $prepared = app(OrderMeasurementService::class)->prepareSnapshots([$structuredState], $customer)[0];
        app(OrderMeasurementService::class)->persistPrepared($structuredLine, $prepared);
        $legacy = OrderMeasurement::query()->create([
            'order_line_id' => $legacyLine->id,
            'measurements' => ['Waist' => '36 in', 'Trouser Length' => '40 in'],
        ]);
        $legacyBefore = $legacy->measurements;
        $structuredBefore = $structuredLine->measurement()->firstOrFail()->measurements;

        Livewire::test(OrderForm::class, ['order' => $order])
            ->assertSee('Edit Measurements')
            ->set('notes', 'No measurement edit')
            ->call('save')
            ->assertSet('showCustomerMeasurementSavebackModal', false)
            ->assertHasNoErrors();
        $this->assertEquals($legacyBefore, $legacy->fresh()->measurements);
        $this->assertEquals($structuredBefore, $structuredLine->measurement()->firstOrFail()->measurements);

        $edit = Livewire::test(OrderForm::class, ['order' => $order->fresh()]);
        $legacyIndex = collect($edit->get('lines'))->search(fn (array $line): bool => $line['id'] === $legacyLine->id);
        $edit->set("lines.$legacyIndex.measurements.0.value", '37.00')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(2, $legacy->fresh()->measurements['version']);
        $this->assertTrue($legacy->fresh()->measurements['entries'][0]['is_custom']);
        $this->assertSame('37.00', $legacy->fresh()->measurements['entries'][0]['value']);
    }

    public function test_soft_deleted_measurement_is_restored_and_reused_while_clearing_leaves_no_active_record(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field();
        $order = $this->order($customer, $user->id);
        $line = $order->lines()->create(['item_name' => 'Suit', 'qty' => 1, 'unit_price' => 1, 'line_total' => 1]);
        $service = app(OrderMeasurementService::class);
        $state = array_merge(app(OrderMeasurementSnapshot::class)->emptyEditorState(), [
            'id' => $line->id,
            'measurements' => [[
                'measurement_field_id' => $field->id, 'label' => $field->name, 'code' => $field->code,
                'value' => '80.00', 'unit' => 'cm', 'is_custom' => false,
            ]],
        ]);

        $service->persistPrepared($line, $service->prepareSnapshots([$state], $customer)[0]);
        $measurement = $line->measurement()->firstOrFail();
        $this->assertSame($line->id, $measurement->active_order_line_id);

        $state['measurements'][0]['value'] = '';
        $service->persistPrepared($line, $service->prepareSnapshots([$state], $customer)[0]);
        $this->assertSoftDeleted('order_measurements', ['id' => $measurement->id]);
        $this->assertDatabaseMissing('order_measurements', ['order_line_id' => $line->id, 'deleted_at' => null]);

        $state['measurements'][0]['value'] = '81.25';
        $service->persistPrepared($line, $service->prepareSnapshots([$state], $customer)[0]);
        $restored = $line->measurement()->firstOrFail();
        $this->assertSame($measurement->id, $restored->id);
        $this->assertSame('81.25', $restored->measurements['entries'][0]['value']);
        $this->assertSame(1, OrderMeasurement::query()->where('order_line_id', $line->id)->count());
    }

    public function test_ambiguous_legacy_duplicates_are_preserved_and_fail_closed_when_measurements_are_edited(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order = $this->order($customer, $user->id);
        $line = $order->lines()->create(['item_name' => 'Legacy duplicate', 'qty' => 1, 'unit_price' => 1, 'line_total' => 1]);
        DB::table('order_measurements')->insert([
            [
                'order_line_id' => $line->id, 'active_order_line_id' => null,
                'measurements' => json_encode(['Chest' => '40 in']),
                'snapshot_format_version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'order_line_id' => $line->id, 'active_order_line_id' => null,
                'measurements' => json_encode(['Chest' => '42 in']),
                'snapshot_format_version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $component = Livewire::test(OrderForm::class, ['order' => $order]);
        $component->set('lines.0.measurements.0.value', '43.00')
            ->call('save')
            ->assertHasErrors('lines.0.measurements');
        $this->assertSame(2, DB::table('order_measurements')->where('order_line_id', $line->id)->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('order_measurements')->where('order_line_id', $line->id)->whereNotNull('deleted_at')->count());
    }

    public function test_blank_required_measurements_do_not_block_initial_order_or_new_customer_flow(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $category = $this->category('Suit');
        $required = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $category->measurementFields()->attach($required->id, ['is_required' => true, 'sort_order' => 1]);
        $garment = $this->catalogItem(['garment_category_id' => $category->id]);

        Livewire::test(OrderForm::class)
            ->call('openNewCustomerModal')
            ->set('newCustomerName', 'New Measurement Customer')
            ->set('newCustomerPhone', '+255700654321')
            ->call('createNewCustomer')
            ->call('configureDirectCatalogItem', $garment->id)
            ->call('confirmDirectCatalogItem')
            ->assertSee('No measurements')
            ->call('openMeasurementModal', 0)
            ->assertSee('Required')
            ->call('cancelMeasurementModal')
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNotNull($order->customer_id);
        $this->assertNull($order->lines()->sole()->measurement);
        $this->assertDatabaseCount('measurement_profiles', 0);
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
            'instructions' => 'Measure carefully around the body.',
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
            'garment_category_id' => null,
            'default_selling_price' => '100000.00',
            'requires_measurements' => true,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Individual,
            'available_all_branches' => true,
        ], $overrides));
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
