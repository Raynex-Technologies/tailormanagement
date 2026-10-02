<?php

namespace Tests\Feature\OrderCatalog;

use App\Livewire\GarmentOptions\CategoriesIndex;
use App\Livewire\GarmentOptions\Index as Groups;
use App\Livewire\GarmentOptions\OptionsIndex;
use App\Livewire\GarmentOptions\Show;
use App\Livewire\OrderCatalog\ItemForm;
use App\Livewire\OrderCatalog\MeasurementForm;
use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use App\Models\OrderCatalogItem;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class GarmentMasterWorkspaceTest extends TestCase
{
    public function test_type_creation_redirects_to_workspace_and_archive_reactivate_preserves_links(): void
    {
        $this->actingAsRole('admin');
        $component = Livewire::test(CategoriesIndex::class)->call('create')
            ->set('name', 'Waistcoat')->call('save')->assertHasNoErrors();
        $type = GarmentCategory::query()->where('slug', 'waistcoat')->firstOrFail();
        $component->assertRedirect(route('order-catalog.garment-types.show', $type));
        $field = $this->field();
        $type->measurementFields()->attach($field, ['is_required' => true, 'sort_order' => 7]);
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('edit', $type->id)
            ->set('description', 'A tailored layer')->call('save')->assertHasNoErrors();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('toggleActive', $type->id);
        $this->assertFalse($type->refresh()->is_active);
        $this->assertSame(1, $type->measurementFields()->count());
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('toggleActive', $type->id);
        $this->assertTrue($type->refresh()->is_active);
    }

    public function test_new_measurements_exclude_archived_types_and_edit_preserves_existing_archived_links(): void
    {
        $this->actingAsRole('admin');
        $active = $this->type('Active Suit');
        $archived = $this->type('Archived Suit', false);
        $unlinked = $this->type('Hidden Suit', false);
        $field = $this->field();
        $field->garmentCategories()->attach($archived, ['is_required' => true, 'sort_order' => 8]);
        Livewire::test(MeasurementForm::class)->assertViewHas('categories', fn ($rows) => $rows->modelKeys() === [$active->id]);
        Livewire::test(MeasurementForm::class, ['measurementField' => $field])
            ->assertSee('Archived Suit')->assertDontSee('Hidden Suit')
            ->set('instructions', 'Preserve the existing link')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('garment_category_measurement_field', ['garment_category_id' => $archived->id, 'measurement_field_id' => $field->id, 'is_required' => true, 'sort_order' => 8]);
        Livewire::test(MeasurementForm::class, ['measurementField' => $field])
            ->set("categoryApplicability.{$archived->id}.selected", false)->call('save')->assertHasNoErrors();
        $this->assertSame(0, $field->garmentCategories()->count());
        Livewire::test(MeasurementForm::class)->set('name', 'Waist')->set('code', 'WAIST')
            ->set("categoryApplicability.{$unlinked->id}", ['selected' => true, 'required' => false, 'sort_order' => 0])
            ->call('save')->assertHasErrors('categoryApplicability');
    }

    public function test_workspace_saves_canonical_pivot_and_other_measurement_view_reads_it(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type();
        $field = $this->field();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')
            ->call('selectMeasurement', $field->id)
            ->set("measurementSettings.{$field->id}.required", true)
            ->set("measurementSettings.{$field->id}.sort_order", 23)
            ->call('saveMeasurements')->assertHasNoErrors();
        Livewire::test(MeasurementForm::class, ['measurementField' => $field])
            ->assertSet("categoryApplicability.{$type->id}.selected", true)
            ->assertSet("categoryApplicability.{$type->id}.required", true)
            ->assertSet("categoryApplicability.{$type->id}.sort_order", 23);
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')
            ->set("measurementSettings.{$field->id}.selected", false)->call('saveMeasurements')->assertHasNoErrors();
        $this->assertSame(0, $type->measurementFields()->count());
    }

    public function test_workspace_retains_existing_archived_fields_but_rejects_new_archived_or_invalid_links(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type();
        $field = $this->field();
        $type->measurementFields()->attach($field);
        $field->update(['is_active' => false]);
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')
            ->call('saveMeasurements')->assertHasNoErrors();
        $other = $this->field('HIP');
        $other->update(['is_active' => false]);
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')
            ->set("measurementSettings.{$other->id}", ['selected' => true, 'required' => false, 'sort_order' => 1])
            ->call('saveMeasurements')->assertHasErrors('measurementSettings');
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')
            ->set('measurementSettings.999999', ['selected' => true, 'required' => false, 'sort_order' => 1])
            ->call('saveMeasurements')->assertHasErrors('measurementSettings');
        $this->assertSame([$field->id], $type->measurementFields()->pluck('measurement_fields.id')->all());
    }

    public function test_archived_catalogue_association_survives_edit_but_cannot_be_newly_assigned(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type('Archived Type', false);
        $item = $this->item($type);
        Livewire::test(ItemForm::class, ['catalogItem' => $item])->assertSee('Archived Type (Archived)')
            ->set('name', 'Updated offering')->call('save')->assertHasNoErrors();
        $this->assertSame($type->id, $item->refresh()->garment_category_id);
        Livewire::test(ItemForm::class)->set('name', 'New offering')->set('defaultSellingPrice', '100')
            ->set('garmentCategoryId', $type->id)->call('save')->assertHasErrors('garmentCategoryId');
        Livewire::test(ItemForm::class, ['catalogItem' => $item])->set('type', 'service')
            ->call('save')->assertHasNoErrors();
        $this->assertNull($item->refresh()->garment_category_id);
    }

    public function test_create_item_preselects_type_and_uncategorized_garments_keep_warning(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type();
        Livewire::withQueryParams(['garment_type' => $type->id])->test(ItemForm::class)
            ->assertSet('garmentCategoryId', $type->id)->set('name', 'Classic Suit')->set('defaultSellingPrice', '200')
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('order_catalog_items', ['name' => 'Classic Suit', 'garment_category_id' => $type->id]);
        Livewire::withQueryParams([])->test(ItemForm::class)
            ->assertSee('Select a Garment Type so the system knows which measurement template should be used.');
    }

    public function test_workspace_renders_linked_data_counts_and_contextual_customization_routes(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type();
        $field = $this->field();
        $type->measurementFields()->attach($field, ['sort_order' => 10, 'is_required' => true]);
        $group = $type->optionGroups()->create(['name' => 'Lapel Style', 'slug' => 'lapel', 'input_type' => 'select', 'is_active' => true]);
        $group->options()->create(['label' => 'Notch', 'value' => 'notch', 'is_active' => true]);
        $item = $this->item($type);
        Livewire::test(CategoriesIndex::class)->assertViewHas('categories', fn ($rows) => $rows->first()->measurement_fields_count === 1 && $rows->first()->catalog_items_count === 1);
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('setTab', 'measurements')->assertSee('CHEST')
            ->call('setTab', 'customization')->assertSee('Lapel Style')->assertSee('Notch')
            ->call('setTab', 'items')->assertSee($item->name)->assertSee($item->code);
        Livewire::withQueryParams(['garment_type' => $type->id, 'edit_group' => $group->id])->test(Groups::class)
            ->assertSet('categoryId', $type->id)->assertSet('groupId', $group->id);
        Livewire::withQueryParams(['garment_type' => $type->id, 'group' => $group->id])->test(OptionsIndex::class)
            ->assertSet('categoryId', $type->id)->assertSet('groupFilter', $group->id)->assertSee('Notch');
    }

    public function test_permissions_remain_separate_for_type_measurements_and_catalogue(): void
    {
        $type = $this->type();
        $user = User::factory()->forBranch($this->branch)->create();
        $this->actingAs($user);
        $this->get(route('order-catalog.garment-types.index'))->assertForbidden();
        $user->givePermissionTo('garment-options.view');
        $this->get(route('order-catalog.garment-types.index'))->assertOk();
        $this->get(route('order-catalog.garment-types.show', $type))->assertOk();
        Livewire::test(Show::class, ['garmentCategory' => $type])->assertDontSee('Edit Garment Type')
            ->call('manageMeasurements')->assertForbidden();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('saveMeasurements')->assertForbidden();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('edit', $type->id)->assertForbidden();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('toggleActive', $type->id)->assertForbidden();
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('setTab', 'items')->assertForbidden();
        $user->givePermissionTo('measurement_fields.manage');
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('manageMeasurements')->assertSet('managingMeasurements', true);
    }

    public function test_workspace_hides_other_branch_offerings_and_branch_manager_cannot_edit_them(): void
    {
        $type = $this->type();
        $own = $this->item($type, 'Own Offering');
        $own->update(['available_all_branches' => false]);
        $own->branches()->attach($this->branch);
        $other = $this->item($type, 'Other Branch Offering');
        $other->update(['available_all_branches' => false]);
        $other->branches()->attach($this->otherBranch);
        $this->actingAsRole('branch_manager');
        Livewire::test(Show::class, ['garmentCategory' => $type])->call('setTab', 'items')
            ->assertSee('Own Offering')->assertDontSee('Other Branch Offering');
        Livewire::test(ItemForm::class, ['catalogItem' => $other])->assertForbidden();
    }

    public function test_duplicate_slug_is_a_validation_error(): void
    {
        $this->actingAsRole('admin');
        $this->type('Dinner Jacket');
        Livewire::test(CategoriesIndex::class)->call('create')->set('name', 'Dinner Jacket')
            ->call('save')->assertHasErrors('slug');
    }

    public function test_master_routes_use_orders_module_without_changing_booking_routes(): void
    {
        $this->actingAsRole('admin');
        $type = $this->type();
        config(['modules.orders.enabled' => true, 'modules.bookings.enabled' => false]);
        $this->get(route('order-catalog.garment-types.index'))->assertOk();
        $this->get(route('order-catalog.garment-types.show', $type))->assertOk();
        $this->get(route('admin.garment-categories.index'))->assertOk();
        $this->get(route('admin.garment-options.index'))->assertOk();
        $this->get(route('admin.garment-option-groups.index'))->assertOk();
        $this->get(route('admin.online-bookings.index'))->assertNotFound();
        config(['modules.orders.enabled' => false, 'modules.bookings.enabled' => true]);
        $this->get(route('order-catalog.garment-types.index'))->assertNotFound();
        $this->get(route('admin.garment-options.index'))->assertNotFound();
    }

    private function type(string $name = 'Suit', bool $active = true): GarmentCategory
    {
        return GarmentCategory::query()->create(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => $active, 'sort_order' => 1]);
    }

    private function field(string $code = 'CHEST'): MeasurementField
    {
        return MeasurementField::query()->create(['name' => ucfirst(strtolower($code)), 'slug' => strtolower($code), 'code' => $code, 'default_unit' => 'cm', 'is_active' => true, 'is_global' => false, 'is_required' => false, 'sort_order' => 0]);
    }

    private function item(GarmentCategory $type, string $name = 'Classic Suit'): OrderCatalogItem
    {
        return OrderCatalogItem::query()->create(['name' => $name, 'type' => 'garment', 'garment_category_id' => $type->id, 'default_selling_price' => '200', 'requires_measurements' => true, 'quantity_behavior' => 'individual', 'available_all_branches' => true]);
    }
}
