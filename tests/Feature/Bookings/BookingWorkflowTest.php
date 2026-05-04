<?php

namespace Tests\Feature\Bookings;

use App\Actions\Appointments\ApproveAppointmentAction;
use App\Actions\Appointments\DeclineAppointmentAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Actions\Bookings\CreateOnlineBookingAction;
use App\Actions\Bookings\UpdateOnlineBookingStatusAction;
use App\Livewire\Public\OnlineBookingWizard;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\GarmentCategory;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OnlineBooking;
use App\Services\Appointments\AppointmentAvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingSystemSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookingSystemSeeder::class);
    }

    public function test_customer_can_submit_measurement_appointment_without_garment_customization(): void
    {
        $type = AppointmentType::query()->where('code', 'measurement')->firstOrFail();
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Monday')->toDateString();
        $this->window($date, $type->id);
        $slot = app(AppointmentAvailabilityService::class)->slots($this->branch->id, $type->id, $date)->firstWhere('available', true);

        Livewire::test(OnlineBookingWizard::class)
            ->set('booking_type', 'measurement_appointment')
            ->set('branch_id', $this->branch->id)
            ->set('customer_name', 'Jane Customer')
            ->set('customer_phone', '255700000001')
            ->set('appointment_date', $date)
            ->call('refreshSlots')
            ->set('selectedSlot', $slot['start_at'])
            ->call('submit')
            ->assertSet('step', 6);

        $booking = OnlineBooking::query()->first();
        $this->assertSame('measurement_appointment', $booking->booking_type);
        $this->assertCount(0, $booking->items);
        $this->assertDatabaseHas('appointments', ['online_booking_id' => $booking->id, 'status' => 'pending_approval']);
    }

    public function test_booking_wizard_renders_with_system_color_variables(): void
    {
        $this->get(route('booking.public'))
            ->assertOk()
            ->assertSee('Book Tailoring Service')
            ->assertSee('var(--tailorpro-primary', false)
            ->assertSee('var(--tailorpro-secondary', false);
    }

    public function test_customer_cannot_continue_without_selecting_booking_type(): void
    {
        Livewire::test(OnlineBookingWizard::class)
            ->set('booking_type', '')
            ->call('next')
            ->assertHasErrors(['booking_type']);
    }

    public function test_customer_cannot_continue_without_required_contact_details(): void
    {
        Livewire::test(OnlineBookingWizard::class)
            ->set('step', 2)
            ->set('customer_name', '')
            ->set('customer_phone', '')
            ->call('next')
            ->assertHasErrors(['customer_name', 'customer_phone'])
            ->assertSet('step', 2);
    }

    public function test_new_custom_order_requires_garment_details_on_details_step(): void
    {
        Livewire::test(OnlineBookingWizard::class)
            ->set('step', 3)
            ->set('booking_type', 'new_custom_order')
            ->set('garment_category_id', null)
            ->call('next')
            ->assertHasErrors(['garment_category_id'])
            ->assertSet('step', 3);
    }

    public function test_non_appointment_schedule_step_can_continue_without_missing_rules_exception(): void
    {
        Livewire::test(OnlineBookingWizard::class)
            ->set('step', 4)
            ->set('booking_type', 'repeat_previous_order')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 5);
    }

    public function test_customer_can_submit_new_custom_order_with_garment_options(): void
    {
        $category = GarmentCategory::query()->where('slug', 'shirt')->firstOrFail();
        $group = $category->optionGroups()->with('options')->firstOrFail();
        $option = $group->options->first();

        app(CreateOnlineBookingAction::class)->execute([
            'booking_type' => 'new_custom_order',
            'branch_id' => $this->branch->id,
            'customer_name' => 'John Customer',
            'customer_phone' => '255700000002',
            'measurement_option' => 'measurements_not_sure',
            'items' => [[
                'garment_category_id' => $category->id,
                'quantity' => 1,
                'fabric_source' => 'customer_provided',
                'selected_options' => [[
                    'garment_option_group_id' => $group->id,
                    'garment_option_id' => $option->id,
                ]],
            ]],
        ]);

        $this->assertDatabaseHas('online_bookings', ['booking_type' => 'new_custom_order', 'booking_number' => 'TPB-000001']);
        $this->assertDatabaseHas('online_booking_item_options', ['garment_option_id' => $option->id]);
    }

    public function test_invalid_appointment_slot_is_rejected(): void
    {
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Sunday')->toDateString();

        Livewire::test(OnlineBookingWizard::class)
            ->set('booking_type', 'measurement_appointment')
            ->set('branch_id', $this->branch->id)
            ->set('customer_name', 'Jane Customer')
            ->set('customer_phone', '255700000003')
            ->set('appointment_date', $date)
            ->set('selectedSlot', CarbonImmutable::parse($date.' 09:00')->toIso8601String())
            ->call('submit')
            ->assertHasErrors(['selectedSlot']);

        $this->assertDatabaseCount('online_bookings', 0);
    }

    public function test_confirmed_appointment_slot_is_visible_but_rejected(): void
    {
        $type = AppointmentType::query()->where('code', 'measurement')->firstOrFail();
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Monday')->toDateString();
        $this->window($date, $type->id);

        Appointment::query()->create([
            'appointment_number' => 'TPA-BOOKED',
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_start_at' => $date.' 09:00:00',
            'scheduled_end_at' => $date.' 09:30:00',
            'status' => 'confirmed',
        ]);

        Livewire::test(OnlineBookingWizard::class)
            ->set('booking_type', 'measurement_appointment')
            ->set('step', 4)
            ->set('branch_id', $this->branch->id)
            ->set('customer_name', 'Jane Customer')
            ->set('customer_phone', '255700000005')
            ->set('appointment_date', $date)
            ->call('refreshSlots')
            ->assertSee('pointer-events-none', false)
            ->assertSee('Capacity Reached')
            ->assertSet('availableSlots', fn (array $slots): bool => collect($slots)->contains(
                fn (array $slot): bool => $slot['label'] === '09:00 - 09:30'
                    && $slot['available'] === false
                    && $slot['reason'] === 'capacity_reached'
            ))
            ->set('selectedSlot', CarbonImmutable::parse($date.' 09:00:00', AppointmentAvailabilityService::DEFAULT_TIMEZONE)->toIso8601String())
            ->call('submit')
            ->assertHasErrors(['selectedSlot']);

        $this->assertDatabaseCount('online_bookings', 0);
    }

    public function test_appointment_and_booking_status_workflows_record_history(): void
    {
        $admin = $this->actingAsRole('admin');
        $type = AppointmentType::query()->where('code', 'measurement')->firstOrFail();
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Thursday')->toDateString();
        $this->window($date, $type->id);

        $appointment = Appointment::query()->create([
            'appointment_number' => 'TPA-000001',
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_start_at' => $date.' 09:00:00',
            'scheduled_end_at' => $date.' 09:30:00',
            'status' => 'pending_approval',
        ]);

        app(ApproveAppointmentAction::class)->execute($appointment, $admin->id);
        $this->assertSame('confirmed', $appointment->refresh()->status);
        $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $appointment->id, 'new_status' => 'confirmed']);

        $newDate = CarbonImmutable::parse($date)->addWeek()->toDateString();
        $this->window($newDate, $type->id);
        app(RescheduleAppointmentAction::class)->execute($appointment, $newDate.' 09:30:00', $newDate.' 10:00:00', 'Customer asked.', $admin->id);
        $this->assertSame('rescheduled', $appointment->refresh()->status);

        app(DeclineAppointmentAction::class)->execute($appointment, 'Not needed.', $admin->id);
        $this->assertSame('declined', $appointment->refresh()->status);

        $booking = OnlineBooking::query()->create([
            'booking_number' => 'TPB-000001',
            'booking_type' => 'style_consultation',
            'status' => 'pending_review',
            'customer_name' => 'Jane',
            'customer_phone' => '255700000004',
        ]);

        app(UpdateOnlineBookingStatusAction::class)->execute($booking, 'declined', 'Outside service area.', $admin->id);
        $this->assertSame('declined', $booking->refresh()->status);
        $this->assertSame('Outside service area.', $booking->decline_reason);
    }

    protected function window(string $date, int $typeId): void
    {
        OfficeAvailabilityWindow::query()->create([
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $typeId,
            'day_of_week' => CarbonImmutable::parse($date)->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'slot_interval_minutes' => 30,
            'capacity' => 1,
            'is_active' => true,
        ]);
    }
}
