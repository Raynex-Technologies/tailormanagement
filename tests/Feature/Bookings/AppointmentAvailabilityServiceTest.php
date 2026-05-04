<?php

namespace Tests\Feature\Bookings;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OfficeUnavailabilityPeriod;
use App\Services\Appointments\AppointmentAvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingSystemSeeder;
use Tests\TestCase;

class AppointmentAvailabilityServiceTest extends TestCase
{
    protected AppointmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookingSystemSeeder::class);
        $this->type = AppointmentType::query()->where('code', 'measurement')->firstOrFail();
        OfficeAvailabilityWindow::query()->delete();
    }

    public function test_it_generates_slots_inside_weekly_window(): void
    {
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Monday')->toDateString();
        $this->window($date, '09:00', '10:00');

        $slots = app(AppointmentAvailabilityService::class)->slots($this->branch->id, $this->type->id, $date);

        $this->assertCount(2, $slots->where('available', true));
        $this->assertSame('09:00 - 09:30', $slots->first()['label']);
    }

    public function test_it_excludes_blocked_and_capacity_reached_slots(): void
    {
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Tuesday')->toDateString();
        $this->window($date, '09:00', '11:00');

        OfficeUnavailabilityPeriod::query()->create([
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $this->type->id,
            'title' => 'Staff meeting',
            'starts_at' => $date.' 09:00:00',
            'ends_at' => $date.' 09:30:00',
        ]);

        Appointment::query()->create([
            'appointment_number' => 'TPA-TEST01',
            'branch_id' => $this->branch->id,
            'appointment_type_id' => $this->type->id,
            'scheduled_start_at' => $date.' 09:30:00',
            'scheduled_end_at' => $date.' 10:00:00',
            'status' => 'confirmed',
        ]);

        $slots = app(AppointmentAvailabilityService::class)->slots($this->branch->id, $this->type->id, $date);

        $this->assertSame('unavailable', $slots[0]['reason']);
        $this->assertSame('capacity_reached', $slots[1]['reason']);
        $this->assertTrue($slots[2]['available']);
    }

    public function test_it_respects_branch_and_type_specific_windows(): void
    {
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Wednesday')->toDateString();
        $this->window($date, '09:00', '10:00', $this->branch->id, $this->type->id);

        $otherType = AppointmentType::query()->where('code', 'fitting')->firstOrFail();

        $this->assertNotEmpty(app(AppointmentAvailabilityService::class)->slots($this->branch->id, $this->type->id, $date));
        $this->assertEmpty(app(AppointmentAvailabilityService::class)->slots($this->otherBranch->id, $this->type->id, $date));
        $this->assertEmpty(app(AppointmentAvailabilityService::class)->slots($this->branch->id, $otherType->id, $date));
    }

    public function test_it_returns_no_slots_when_closed(): void
    {
        $date = CarbonImmutable::now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->next('Sunday')->toDateString();

        $this->assertEmpty(app(AppointmentAvailabilityService::class)->slots($this->branch->id, $this->type->id, $date));
    }

    protected function window(string $date, string $start, string $end, ?int $branchId = null, ?int $typeId = null): void
    {
        $day = CarbonImmutable::parse($date)->dayOfWeekIso;

        OfficeAvailabilityWindow::query()->create([
            'branch_id' => $branchId ?? $this->branch->id,
            'appointment_type_id' => $typeId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'slot_interval_minutes' => 30,
            'capacity' => 1,
            'is_active' => true,
        ]);
    }
}
