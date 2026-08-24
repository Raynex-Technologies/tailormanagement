<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderCatalogItemType;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\OrderCatalog\Index as OrderCatalogIndex;
use App\Livewire\OrderCatalog\ItemForm;
use App\Livewire\OrderCatalog\MeasurementForm;
use App\Livewire\Public\OnlineBookingWizard;
use App\Models\Customer;
use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Services\Measurements\MeasurementFieldReconciliationService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerMeasurements1AFoundationTest extends TestCase
{
    public function test_admin_can_create_canonical_definition_with_multi_category_applicability(): void
    {
        $suit = $this->category('Suit', 20);
        $shirt = $this->category('Shirt', 10);
        $this->actingAsRole('admin');

        Livewire::test(MeasurementForm::class)
            ->set('name', 'Chest')
            ->set('code', 'CHEST')
            ->set('defaultUnit', 'in')
            ->set('instructions', 'Measure around the fullest part of the chest.')
            ->set("categoryApplicability.{$suit->id}.selected", true)
            ->set("categoryApplicability.{$suit->id}.required", true)
            ->set("categoryApplicability.{$suit->id}.sort_order", 20)
            ->set("categoryApplicability.{$shirt->id}.selected", true)
            ->set("categoryApplicability.{$shirt->id}.required", false)
            ->set("categoryApplicability.{$shirt->id}.sort_order", 10)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('order-catalog.index', ['tab' => 'measurements']));

        $field = MeasurementField::query()->where('code', 'CHEST')->firstOrFail();
        $this->assertSame('in', $field->default_unit);
        $this->assertSame('in', $field->unit);
        $this->assertSame('Measure around the fullest part of the chest.', $field->instructions);
        $this->assertFalse($field->is_global);
        $this->assertSame([$shirt->id, $suit->id], $field->garmentCategories()->pluck('garment_categories.id')->all());
        $this->assertDatabaseHas('garment_category_measurement_field', [
            'garment_category_id' => $suit->id,
            'measurement_field_id' => $field->id,
            'is_required' => true,
            'sort_order' => 20,
        ]);
        $this->assertDatabaseHas('garment_category_measurement_field', [
            'garment_category_id' => $shirt->id,
            'measurement_field_id' => $field->id,
            'is_required' => false,
            'sort_order' => 10,
        ]);

        $field->garmentCategories()->syncWithoutDetaching([
            $suit->id => ['is_required' => false, 'sort_order' => 30],
        ]);

        $this->assertSame(1, DB::table('garment_category_measurement_field')
            ->where('garment_category_id', $suit->id)
            ->where('measurement_field_id', $field->id)
            ->count());
    }

    public function test_definition_form_validates_global_code_and_supported_units(): void
    {
        $this->actingAsRole('admin');
        $this->field(['name' => 'Waist', 'code' => 'WAIST']);

        Livewire::test(MeasurementForm::class)
            ->set('name', 'Another Waist')
            ->set('code', 'WAIST')
            ->set('defaultUnit', 'metres')
            ->call('save')
            ->assertHasErrors(['code', 'defaultUnit']);

        Livewire::test(MeasurementForm::class)
            ->set('name', 'Shoulder')
            ->set('code', 'shoulder length')
            ->set('defaultUnit', 'cm')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('measurement_fields', [
            'code' => 'SHOULDER_LENGTH',
            'instructions' => null,
        ]);
    }

    public function test_measurement_index_supports_archive_reactivate_filters_and_authorization(): void
    {
        $field = $this->field(['name' => 'Neck', 'code' => 'NECK']);
        $this->actingAsRole('admin');

        Livewire::test(OrderCatalogIndex::class)
            ->call('setTab', 'measurements')
            ->assertSee('Measurements')
            ->assertSee('Add Measurement')
            ->assertSee('NECK')
            ->call('archiveMeasurement', $field->id)
            ->assertDontSee('NECK')
            ->set('statusFilter', 'archived')
            ->assertSee('NECK')
            ->assertSee('Reactivate')
            ->call('reactivateMeasurement', $field->id);

        $this->assertTrue($field->fresh()->is_active);

        auth()->logout();
        $this->actingAsRole('branch_manager');
        Livewire::test(OrderCatalogIndex::class)
            ->call('setTab', 'measurements')
            ->assertSee('Measurements')
            ->assertDontSee('Add Measurement');

        $this->get(route('order-catalog.measurements.create'))->assertForbidden();
    }

    public function test_catalog_garments_can_select_category_while_services_clear_it(): void
    {
        $category = $this->category('Suit');
        $this->actingAsRole('admin');

        Livewire::test(ItemForm::class)
            ->set('name', 'Two-Piece Suit')
            ->set('type', OrderCatalogItemType::Garment->value)
            ->set('garmentCategoryId', $category->id)
            ->set('defaultSellingPrice', '250000')
            ->set('quantityBehavior', 'individual')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_catalog_items', [
            'name' => 'Two-Piece Suit',
            'garment_category_id' => $category->id,
        ]);

        Livewire::test(ItemForm::class)
            ->set('name', 'Alteration Service')
            ->set('type', OrderCatalogItemType::Service->value)
            ->set('garmentCategoryId', $category->id)
            ->set('defaultSellingPrice', '30000')
            ->set('quantityBehavior', 'bulk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_catalog_items', [
            'name' => 'Alteration Service',
            'garment_category_id' => null,
        ]);

        $legacy = OrderCatalogItem::query()->create([
            'name' => 'Legacy uncategorized garment',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => 1,
            'requires_measurements' => true,
            'available_all_branches' => true,
        ]);
        $this->assertNull($legacy->garment_category_id);
    }

    public function test_online_booking_loads_only_active_global_and_applicable_fields(): void
    {
        $suit = $this->category('Suit');
        $shirt = $this->category('Shirt');
        $global = $this->field(['name' => 'Height', 'code' => 'HEIGHT', 'is_global' => true]);
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $neck = $this->field(['name' => 'Neck', 'code' => 'NECK']);
        $archived = $this->field(['name' => 'Old Rise', 'code' => 'OLD_RISE', 'is_active' => false]);
        $unassigned = $this->field(['name' => 'Unassigned Dimension', 'code' => 'UNASSIGNED']);
        $chest->garmentCategories()->attach($suit->id, ['is_required' => true, 'sort_order' => 10]);
        $neck->garmentCategories()->attach($shirt->id, ['is_required' => true, 'sort_order' => 10]);
        $archived->garmentCategories()->attach($suit->id, ['sort_order' => 20]);

        Livewire::test(OnlineBookingWizard::class)
            ->set('step', 3)
            ->set('booking_type', 'new_custom_order')
            ->set('garment_category_id', $suit->id)
            ->set('measurement_option', 'enter_measurements_now')
            ->assertSee($global->name)
            ->assertSee($chest->name)
            ->assertDontSee($neck->name)
            ->assertDontSee($archived->name)
            ->assertDontSee($unassigned->name);
    }

    public function test_reconciliation_consolidates_safe_duplicates_but_preserves_ambiguous_rows_and_legacy_orders(): void
    {
        $suit = $this->category('Suit');
        $shirt = $this->category('Shirt');
        $canonical = $this->field([
            'name' => 'Shoulder',
            'slug' => 'shoulder',
            'code' => 'SHOULDER',
            'garment_category_id' => $suit->id,
            'sort_order' => 20,
        ]);
        $legacyDuplicate = $this->field([
            'name' => 'Shoulder',
            'slug' => 'shoulder',
            'code' => 'SHOULDER_SHIRT_LEGACY',
            'garment_category_id' => $shirt->id,
            'is_required' => true,
            'sort_order' => 10,
        ]);
        $ambiguousBody = $this->field(['name' => 'Length', 'slug' => 'body-length', 'code' => 'BODY_LENGTH']);
        $ambiguousGarment = $this->field(['name' => 'Length', 'slug' => 'garment-length', 'code' => 'GARMENT_LENGTH']);

        $orderMeasurement = $this->legacyOrderMeasurement();
        $before = $orderMeasurement->measurements;

        $report = app(MeasurementFieldReconciliationService::class)->reconcile();

        $this->assertTrue(collect($report['canonicalized'])->contains('canonical_id', $canonical->id));
        $this->assertFalse($legacyDuplicate->fresh()->is_active);
        $this->assertDatabaseHas('garment_category_measurement_field', [
            'garment_category_id' => $shirt->id,
            'measurement_field_id' => $canonical->id,
            'is_required' => true,
            'sort_order' => 10,
        ]);
        $this->assertTrue(collect($report['ambiguous'])->contains(fn (array $item): bool => in_array($ambiguousBody->id, $item['field_ids'], true) && in_array($ambiguousGarment->id, $item['field_ids'], true)));
        $this->assertTrue($ambiguousBody->fresh()->is_active);
        $this->assertTrue($ambiguousGarment->fresh()->is_active);
        $this->assertDatabaseHas('measurement_fields', ['id' => $legacyDuplicate->id]);
        $this->assertEquals($before, $orderMeasurement->fresh()->measurements);
    }

    public function test_existing_superadmin_receives_management_permission_through_migration_sync(): void
    {
        $superadmin = $this->actingAsRole('superadmin');
        $role = Role::findByName('superadmin');
        $permission = Permission::findByName('measurement_fields.manage');
        $role->revokePermissionTo($permission);
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertDatabaseMissing('permissions', ['name' => 'measurement_fields.manage']);

        $migration = require database_path('migrations/2026_08_24_000002_add_measurement_field_management_permission.php');
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
        $this->assertTrue($role->fresh()->hasPermissionTo('measurement_fields.manage'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('measurement_fields.manage'));
        $this->assertFalse(Role::findByName('branch_manager')->hasPermissionTo('measurement_fields.manage'));
    }

    private function category(string $name, int $sortOrder = 0): GarmentCategory
    {
        return GarmentCategory::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
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
            'instructions' => null,
            'is_global' => false,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    private function legacyOrderMeasurement(): OrderMeasurement
    {
        $this->actingAsRole('admin');
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);
        $line = OrderLine::query()->create([
            'order_id' => $order->id,
            'item_name' => 'Legacy Suit',
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
        ]);

        return OrderMeasurement::query()->create([
            'order_line_id' => $line->id,
            'measurements' => ['Waist' => '36 in', 'Chest' => '42 in'],
        ]);
    }
}
