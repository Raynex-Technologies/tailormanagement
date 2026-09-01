<?php

namespace Tests\Feature\Bookings;

use App\Livewire\GarmentOptions\CategoriesIndex;
use App\Models\GarmentCategory;
use Database\Seeders\BookingSystemSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class GarmentCategoriesCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookingSystemSeeder::class);
        $this->actingAsRole('admin');
    }

    public function test_category_workspace_uses_clear_customization_language(): void
    {
        $this->get(route('admin.garment-categories.index'))
            ->assertOk()
            ->assertSee('Garment Categories')
            ->assertSee('Customization Choices')
            ->assertSee('Customization Sections');

        $this->get(route('admin.garment-options.index'))
            ->assertOk()
            ->assertSee('Garment Customizations')
            ->assertSee('New Choice')
            ->assertSee('Customization Sections');
    }

    public function test_authorized_staff_can_create_view_edit_and_archive_a_category(): void
    {
        Livewire::test(CategoriesIndex::class)
            ->call('create')
            ->set('name', 'Dinner Jacket')
            ->set('description', 'Formal evening tailoring.')
            ->set('genderScope', 'unisex')
            ->set('sortOrder', 20)
            ->call('save')
            ->assertHasNoErrors();

        $category = GarmentCategory::query()->where('slug', 'dinner-jacket')->firstOrFail();

        Livewire::test(CategoriesIndex::class)
            ->call('view', $category->id)
            ->assertSet('readOnly', true)
            ->assertSet('name', 'Dinner Jacket')
            ->call('edit', $category->id)
            ->set('name', 'Premium Dinner Jacket')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('garment_categories', [
            'id' => $category->id,
            'name' => 'Premium Dinner Jacket',
            'slug' => 'premium-dinner-jacket',
            'is_active' => true,
        ]);

        Livewire::test(CategoriesIndex::class)->call('toggleActive', $category->id);
        $this->assertFalse($category->refresh()->is_active);
    }
}
