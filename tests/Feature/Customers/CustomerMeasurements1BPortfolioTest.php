<?php

namespace Tests\Feature\Customers;

use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Customers\MeasurementForm;
use App\Models\Customer;
use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Models\User;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Support\BranchContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class CustomerMeasurements1BPortfolioTest extends TestCase
{
    public function test_customer_show_has_a_permission_aware_empty_portfolio_before_order_history(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Portfolio Customer']);
        $this->actingAsRole('branch_manager', $this->branch);

        $response = $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('No saved measurements yet.')
            ->assertSee('Record Measurements');

        $this->assertLessThan(
            strpos($response->getContent(), 'Order History'),
            strpos($response->getContent(), 'Saved body measurements and immutable revision history.'),
        );

        auth()->logout();
        $viewer = User::factory()->forBranch($this->branch)->create();
        $viewer->givePermissionTo('customers.view');
        BranchContext::setActiveBranch($this->branch->id);
        $this->actingAs($viewer);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Record Measurements');
        $this->get(route('customers.measurements.record', $customer))->assertForbidden();
    }

    public function test_staff_can_record_exact_decimal_values_with_snapshots_and_no_side_effects(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST', 'default_unit' => 'in', 'unit' => 'in']);
        $smsBefore = DB::table('sms_logs')->count();
        $whatsAppBefore = DB::table('whatsapp_messages')->count();

        Livewire::test(MeasurementForm::class, ['customer' => $customer])
            ->set('selectedMeasurementFieldId', (string) $waist->id)
            ->call('addMeasurement')
            ->set('rows.0.value', '36.25')
            ->set('rows.0.unit', 'in')
            ->set('measuredAt', '2026-08-20')
            ->set('notes', 'Measured over a light shirt.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('customers.show', $customer));

        $profile = $customer->currentMeasurementProfile()->with('values')->firstOrFail();
        $value = $profile->values->sole();
        $this->assertSame(1, $profile->revision);
        $this->assertTrue($profile->is_current);
        $this->assertSame($recorder->id, $profile->recorded_by_user_id);
        $this->assertSame('2026-08-20', $profile->measured_at->toDateString());
        $this->assertSame('36.25', $value->value);
        $this->assertSame('in', $value->unit);
        $this->assertSame('WAIST', $value->field_code_snapshot);
        $this->assertSame('Waist', $value->field_label_snapshot);
        $this->assertDatabaseCount('order_measurements', 0);
        $this->assertSame($smsBefore, DB::table('sms_logs')->count());
        $this->assertSame($whatsAppBefore, DB::table('whatsapp_messages')->count());
    }

    public function test_new_revision_copies_forward_changes_additions_and_removals_without_mutating_history(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $waist = $this->field(['name' => 'Waist', 'code' => 'WAIST']);
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $sleeve = $this->field(['name' => 'Sleeve', 'code' => 'SLEEVE']);
        $service = app(CustomerMeasurementProfileService::class);

        $first = $service->createRevision($customer, [
            ['measurement_field_id' => $waist->id, 'value' => '80.00', 'unit' => 'cm'],
            ['measurement_field_id' => $chest->id, 'value' => '101.50', 'unit' => 'cm'],
        ], [], '2026-08-18', $recorder, 'First fitting');

        $second = $service->createRevision($customer, [
            ['measurement_field_id' => $waist->id, 'value' => '81.25', 'unit' => 'cm'],
            ['measurement_field_id' => $sleeve->id, 'value' => '64.00', 'unit' => 'cm'],
        ], [$chest->id], '2026-08-21', $recorder, 'Adjusted waist');

        $this->assertSame($first->lineage_uuid, $second->lineage_uuid);
        $this->assertSame(2, $second->revision);
        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->is_current);
        $this->assertSame($second->id, $customer->currentMeasurementProfile()->firstOrFail()->id);
        $this->assertSame(1, MeasurementProfile::forCustomer($customer)->current()->count());
        $this->assertDatabaseHas('measurement_values', [
            'measurement_profile_id' => $first->id,
            'measurement_field_id' => $waist->id,
            'value' => '80.00',
        ]);
        $this->assertDatabaseHas('measurement_values', [
            'measurement_profile_id' => $first->id,
            'measurement_field_id' => $chest->id,
            'value' => '101.50',
        ]);
        $this->assertDatabaseHas('measurement_values', [
            'measurement_profile_id' => $second->id,
            'measurement_field_id' => $waist->id,
            'value' => '81.25',
        ]);
        $this->assertDatabaseHas('measurement_values', [
            'measurement_profile_id' => $second->id,
            'measurement_field_id' => $sleeve->id,
        ]);
        $this->assertDatabaseMissing('measurement_values', [
            'measurement_profile_id' => $second->id,
            'measurement_field_id' => $chest->id,
        ]);
    }

    public function test_definition_renames_archive_and_deletion_preserve_understandable_snapshots(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $chest = $this->field(['name' => 'Chest', 'code' => 'CHEST']);
        $archivedNew = $this->field(['name' => 'Old Rise', 'code' => 'OLD_RISE', 'is_active' => false]);
        $service = app(CustomerMeasurementProfileService::class);

        $first = $service->createRevision($customer, [
            ['measurement_field_id' => $chest->id, 'value' => '101.50', 'unit' => 'cm'],
        ], [], '2026-08-18', $recorder);

        $chest->update(['name' => 'Bust']);
        $second = $service->createRevision($customer, [
            ['measurement_field_id' => $chest->id, 'value' => '102.00', 'unit' => 'cm'],
        ], [], '2026-08-19', $recorder);

        $this->assertSame('Chest', $first->values()->sole()->field_label_snapshot);
        $this->assertSame('Bust', $second->values()->sole()->field_label_snapshot);

        $chest->update(['is_active' => false]);
        $third = $service->createRevision($customer, [], [], '2026-08-20', $recorder);
        $this->assertSame('Bust', $third->values()->sole()->field_label_snapshot);

        try {
            $service->createRevision($customer, [
                ['measurement_field_id' => $archivedNew->id, 'value' => '25.00', 'unit' => 'cm'],
            ], [], '2026-08-21', $recorder);
            $this->fail('Archived fields must not be added to a revision.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows', $exception->errors());
        }

        $chest->delete();
        $fourth = $service->createRevision($customer, [], [], '2026-08-22', $recorder);
        $this->assertNull($fourth->values()->sole()->field);
        $this->assertSame('Bust', $fourth->values()->sole()->field_label_snapshot);

        $this->get(route('customers.measurements.show', [$customer, $first]))
            ->assertOk()
            ->assertSee('Chest')
            ->assertSee('101.50');
    }

    public function test_form_rejects_empty_duplicate_invalid_values_units_and_future_dates(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field();

        Livewire::test(MeasurementForm::class, ['customer' => $customer])
            ->set('rows', [])
            ->set('measuredAt', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['rows', 'measuredAt']);

        Livewire::test(MeasurementForm::class, ['customer' => $customer])
            ->set('rows', [
                [
                    'measurement_field_id' => $field->id,
                    'label' => $field->name,
                    'code' => $field->code,
                    'value' => '-1',
                    'unit' => 'metres',
                    'archived' => false,
                ],
                [
                    'measurement_field_id' => $field->id,
                    'label' => $field->name,
                    'code' => $field->code,
                    'value' => '20.123',
                    'unit' => 'cm',
                    'archived' => false,
                ],
            ])
            ->call('save')
            ->assertHasErrors([
                'rows.0.value',
                'rows.0.unit',
                'rows.1.measurement_field_id',
                'rows.1.value',
            ]);
    }

    public function test_saved_profiles_and_values_are_immutable_and_database_constraints_reject_duplicate_revisions(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field();
        $profile = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $field->id, 'value' => '80.00', 'unit' => 'cm'],
        ], [], now(), $recorder);

        try {
            $profile->update(['notes' => 'Overwrite attempt']);
            $this->fail('A saved profile must not be updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $profile->values()->firstOrFail()->update(['value' => '90.00']);
            $this->fail('A saved value must not be updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        DB::table('measurement_profiles')->insert([
            'customer_id' => $customer->id,
            'lineage_uuid' => $profile->lineage_uuid,
            'revision' => 1,
            'is_current' => false,
            'profile_name' => 'Default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_current_marker_constraint_rejects_two_current_rows_for_one_lineage(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field();
        $profile = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $field->id, 'value' => '80.00', 'unit' => 'cm'],
        ], [], now(), $recorder);

        $this->expectException(QueryException::class);
        DB::table('measurement_profiles')->insert([
            'customer_id' => $customer->id,
            'lineage_uuid' => $profile->lineage_uuid,
            'revision' => 2,
            'is_current' => true,
            'current_lineage_key' => $profile->lineage_uuid,
            'current_customer_profile_key' => CustomerMeasurementProfileService::customerProfileKey($customer->id),
            'profile_name' => 'Default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ownership_branch_privacy_nullable_legacy_state_and_deleted_recorder_are_safe(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $otherCustomer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $field = $this->field();
        $profile = app(CustomerMeasurementProfileService::class)->createRevision($customer, [
            ['measurement_field_id' => $field->id, 'value' => '80.00', 'unit' => 'cm'],
        ], [], now(), $recorder);

        $legacyUnowned = MeasurementProfile::query()->create([
            'customer_id' => null,
            'profile_name' => 'Legacy Unowned',
            'notes' => 'Ownership was never established.',
        ]);
        $this->assertNull($legacyUnowned->customer_id);
        $this->assertNotNull($legacyUnowned->lineage_uuid);
        $this->assertSame($profile->id, $customer->currentMeasurementProfile()->firstOrFail()->id);

        $this->get(route('customers.measurements.show', [$otherCustomer, $profile]))->assertNotFound();

        $recorder->delete();
        $this->assertNull($profile->fresh()->recorded_by_user_id);

        $customer->delete();
        $this->assertNull($profile->fresh()->customer_id);
    }

    public function test_portfolio_history_is_bounded_ordered_and_legacy_order_measurements_remain_unchanged_and_private(): void
    {
        $recorder = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Bounded History']);
        $later = $this->field(['name' => 'Later', 'code' => 'LATER', 'sort_order' => 20]);
        $earlier = $this->field(['name' => 'Earlier', 'code' => 'EARLIER', 'sort_order' => 10]);
        $legacy = $this->legacyOrderMeasurement($customer, $recorder);
        $legacyBefore = $legacy->measurements;
        $service = app(CustomerMeasurementProfileService::class);

        for ($revision = 1; $revision <= 10; $revision++) {
            $service->createRevision($customer, [
                ['measurement_field_id' => $later->id, 'value' => (string) (80 + $revision), 'unit' => 'cm'],
                ['measurement_field_id' => $earlier->id, 'value' => (string) (40 + $revision), 'unit' => 'cm'],
            ], [], now()->subDays(10 - $revision), $recorder, $revision === 10 ? 'PRIVATE_FITTING_NOTE_9X' : null);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Revision 10')
            ->assertSee('Revision 3')
            ->assertDontSee('Revision 2')
            ->assertSee('Showing the 8 most recent revisions.');
        $measurementQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'measurement_profiles')
                || str_contains($query['query'], 'measurement_values')
                || str_contains($query['query'], 'measurement_fields'));
        $this->assertLessThanOrEqual(6, $measurementQueries->count());
        DB::disableQueryLog();
        $this->assertLessThan(
            strpos($response->getContent(), 'Later'),
            strpos($response->getContent(), 'Earlier'),
        );
        $this->assertEquals($legacyBefore, $legacy->fresh()->measurements);

        auth()->logout();
        $this->get(route('booking.public'))
            ->assertOk()
            ->assertDontSee('PRIVATE_FITTING_NOTE_9X');
    }

    public function test_migration_reconciles_legacy_owned_and_unowned_profiles_without_fabricating_ownership(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $field = $this->field(['name' => 'Legacy Waist', 'code' => 'LEGACY_WAIST']);
        $migration = require database_path('migrations/2026_08_24_000003_create_customer_measurement_revision_foundation.php');
        $migration->down();

        $firstId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => $customer->id,
            'profile_name' => 'Default',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $secondId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => $customer->id,
            'profile_name' => 'Default',
            'created_at' => '2026-02-01 00:00:00',
            'updated_at' => '2026-02-01 00:00:00',
        ]);
        $unownedId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => null,
            'profile_name' => 'Imported Unknown',
            'created_at' => '2026-03-01 00:00:00',
            'updated_at' => '2026-03-01 00:00:00',
        ]);
        DB::table('measurement_values')->insert([
            'measurement_profile_id' => $firstId,
            'measurement_field_id' => $field->id,
            'value' => '36.25',
            'unit' => 'in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $first = MeasurementProfile::query()->findOrFail($firstId);
        $second = MeasurementProfile::query()->findOrFail($secondId);
        $unowned = MeasurementProfile::query()->findOrFail($unownedId);
        $this->assertSame($first->lineage_uuid, $second->lineage_uuid);
        $this->assertSame(1, $first->revision);
        $this->assertSame(2, $second->revision);
        $this->assertFalse($first->is_current);
        $this->assertTrue($second->is_current);
        $this->assertNull($unowned->customer_id);
        $this->assertNotSame($first->lineage_uuid, $unowned->lineage_uuid);
        $this->assertSame('LEGACY_WAIST', $first->values()->sole()->field_code_snapshot);
        $this->assertSame('Legacy Waist', $first->values()->sole()->field_label_snapshot);
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

    private function legacyOrderMeasurement(Customer $customer, User $creator): OrderMeasurement
    {
        $order = Order::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $creator->id,
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
