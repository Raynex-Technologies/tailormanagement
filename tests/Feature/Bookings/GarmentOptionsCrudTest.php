<?php

namespace Tests\Feature\Bookings;

use App\Livewire\GarmentOptions\OptionsIndex;
use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use Database\Seeders\BookingSystemSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class GarmentOptionsCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookingSystemSeeder::class);
        $this->actingAsRole('admin');
    }

    public function test_options_route_lists_options_with_view_and_edit_actions(): void
    {
        $option = GarmentOption::query()->firstOrFail();

        $this->get(route('admin.garment-options.index'))
            ->assertOk()
            ->assertSee($option->label)
            ->assertSee('View')
            ->assertSee('Edit')
            ->assertSee('Customization Sections');
    }

    public function test_authorized_staff_can_view_edit_create_and_archive_an_option(): void
    {
        $option = GarmentOption::query()->firstOrFail();

        Livewire::test(OptionsIndex::class)
            ->call('view', $option->id)
            ->assertSet('readOnly', true)
            ->assertSet('label', $option->label)
            ->call('edit', $option->id)
            ->assertSet('readOnly', false)
            ->set('label', 'Premium '.$option->label)
            ->set('priceAdjustment', '15000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('garment_options', [
            'id' => $option->id,
            'label' => 'Premium '.$option->label,
            'price_adjustment' => '15000.00',
        ]);

        $group = GarmentOptionGroup::query()->firstOrFail();
        Livewire::test(OptionsIndex::class)
            ->call('create')
            ->set('groupId', $group->id)
            ->set('label', 'Hand Finished')
            ->set('description', 'Finished by hand for a premium look.')
            ->call('save')
            ->assertHasNoErrors();

        $created = GarmentOption::query()->where('label', 'Hand Finished')->firstOrFail();
        Livewire::test(OptionsIndex::class)->call('toggleActive', $created->id);
        $this->assertFalse($created->refresh()->is_active);
    }
}
