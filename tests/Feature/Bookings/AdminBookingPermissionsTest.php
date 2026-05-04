<?php

namespace Tests\Feature\Bookings;

use App\Livewire\Availability\Index as AvailabilityIndex;
use App\Livewire\Appointments\Index as AppointmentsIndex;
use App\Livewire\OnlineBookings\Index as OnlineBookingsIndex;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OnlineBooking;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingSystemSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBookingPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookingSystemSeeder::class);
    }

    public function test_unauthorized_users_cannot_manage_availability(): void
    {
        $this->actingAsRole('sales');

        Livewire::test(AvailabilityIndex::class)
            ->call('saveWindow')
            ->assertForbidden();
    }

    public function test_authorized_users_can_manage_availability(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(AvailabilityIndex::class)
            ->set('windowBranchId', $this->branch->id)
            ->set('dayOfWeek', 1)
            ->set('startTime', '09:00')
            ->set('endTime', '10:00')
            ->set('slotIntervalMinutes', 30)
            ->set('capacity', 1)
            ->call('saveWindow')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('office_availability_windows', ['branch_id' => $this->branch->id, 'day_of_week' => 1]);
    }

    public function test_availability_form_defaults_to_eight_to_five(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(AvailabilityIndex::class)
            ->assertSet('startTime', '08:00')
            ->assertSet('endTime', '17:00')
            ->set('startTime', '10:00')
            ->set('endTime', '12:00')
            ->call('resetWindow')
            ->assertSet('startTime', '08:00')
            ->assertSet('endTime', '17:00');
    }

    public function test_authorized_user_can_approve_appointment_and_unauthorized_user_cannot(): void
    {
        $type = AppointmentType::query()->where('code', 'measurement')->firstOrFail();
        $date = CarbonImmutable::now()->next('Monday')->toDateString();

        OfficeAvailabilityWindow::query()->create([
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $type->id,
            'day_of_week' => CarbonImmutable::parse($date)->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_interval_minutes' => 30,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $appointment = Appointment::query()->create([
            'appointment_number' => 'TPA-000001',
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_start_at' => $date.' 09:00:00',
            'scheduled_end_at' => $date.' 09:30:00',
            'status' => 'pending_approval',
        ]);

        $this->actingAsRole('sales');
        Livewire::test(AppointmentsIndex::class)
            ->call('selectAppointment', $appointment->id)
            ->call('approve')
            ->assertForbidden();

        $this->actingAsRole('admin');
        Livewire::test(AppointmentsIndex::class)
            ->call('selectAppointment', $appointment->id)
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertSame('confirmed', $appointment->refresh()->status);
    }

    public function test_online_booking_review_updates_the_selected_booking_not_the_first_booking(): void
    {
        $this->actingAsRole('admin');

        $first = OnlineBooking::query()->create([
            'booking_number' => 'TPB-FIRST',
            'booking_type' => 'measurement_appointment',
            'status' => 'pending_review',
            'customer_name' => 'First Customer',
            'customer_phone' => '255700000101',
            'branch_id' => $this->branch->id,
        ]);

        $second = OnlineBooking::query()->create([
            'booking_number' => 'TPB-SECOND',
            'booking_type' => 'measurement_appointment',
            'status' => 'pending_review',
            'customer_name' => 'Second Customer',
            'customer_phone' => '255700000102',
            'branch_id' => $this->branch->id,
        ]);

        Livewire::test(OnlineBookingsIndex::class)
            ->call('selectBooking', $second->id)
            ->assertSet('selectedBookingId', $second->id)
            ->assertSet('showDetailsModal', true)
            ->set('targetStatus', 'confirmed')
            ->set('reviewNote', 'Confirmed selected booking.')
            ->call('updateStatus')
            ->assertHasNoErrors();

        $this->assertSame('pending_review', $first->refresh()->status);
        $this->assertSame('confirmed', $second->refresh()->status);
        $this->assertSame('Confirmed selected booking.', $second->internal_note);
    }
}
