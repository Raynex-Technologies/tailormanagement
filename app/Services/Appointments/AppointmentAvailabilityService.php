<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OfficeUnavailabilityPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AppointmentAvailabilityService
{
    public const DEFAULT_TIMEZONE = 'Africa/Dar_es_Salaam';

    /**
     * @return \Illuminate\Support\Collection<int, array{start_at: string, end_at: string, label: string, available: bool, reason: ?string}>
     */
    public function slots(?int $branchId, int $appointmentTypeId, string $date, ?string $timezone = null, ?int $excludeAppointmentId = null): Collection
    {
        $timezone ??= self::DEFAULT_TIMEZONE;
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $appointmentType = AppointmentType::query()->findOrFail($appointmentTypeId);
        $dayOfWeek = (int) $day->dayOfWeekIso;

        $windows = OfficeAvailabilityWindow::query()
            ->where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id');
                if ($branchId) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->where(function ($query) use ($appointmentTypeId) {
                $query->whereNull('appointment_type_id')
                    ->orWhere('appointment_type_id', $appointmentTypeId);
            })
            ->where(function ($query) use ($day) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day->toDateString());
            })
            ->where(function ($query) use ($day) {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $day->toDateString());
            })
            ->orderByRaw('branch_id is not null desc')
            ->orderByRaw('appointment_type_id is not null desc')
            ->orderBy('start_time')
            ->get();

        if ($windows->isEmpty()) {
            return collect();
        }

        $unavailability = OfficeUnavailabilityPeriod::query()
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id');
                if ($branchId) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->where(function ($query) use ($appointmentTypeId) {
                $query->whereNull('appointment_type_id')
                    ->orWhere('appointment_type_id', $appointmentTypeId);
            })
            ->where(function ($query) use ($day) {
                $query->where(function ($range) use ($day) {
                    $range->where('starts_at', '<', $day->addDay())
                        ->where('ends_at', '>', $day);
                })->orWhere(function ($yearly) use ($day) {
                    $yearly->where('repeats_yearly', true)
                        ->whereMonth('starts_at', $day->month)
                        ->whereDay('starts_at', $day->day);
                });
            })
            ->get();

        $appointments = Appointment::query()
            ->whereIn('status', Appointment::BLOCKING_STATUSES)
            ->where('appointment_type_id', $appointmentTypeId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereNull('branch_id'))
            ->when($excludeAppointmentId, fn ($query) => $query->whereKeyNot($excludeAppointmentId))
            ->where('scheduled_start_at', '<', $day->addDay())
            ->where('scheduled_end_at', '>', $day)
            ->get();

        $now = CarbonImmutable::now($timezone);
        $slots = collect();

        foreach ($windows as $window) {
            $duration = (int) $appointmentType->default_duration_minutes;
            $bufferBefore = (int) $appointmentType->buffer_before_minutes;
            $bufferAfter = (int) $appointmentType->buffer_after_minutes;
            $interval = max(5, (int) $window->slot_interval_minutes);
            $capacity = max(1, (int) $window->capacity);
            $windowStart = CarbonImmutable::parse($day->toDateString().' '.$window->start_time, $timezone);
            $windowEnd = CarbonImmutable::parse($day->toDateString().' '.$window->end_time, $timezone);

            for ($start = $windowStart; $start->addMinutes($duration)->lessThanOrEqualTo($windowEnd); $start = $start->addMinutes($interval)) {
                $end = $start->addMinutes($duration);
                $blockedStart = $start->subMinutes($bufferBefore);
                $blockedEnd = $end->addMinutes($bufferAfter);
                $available = true;
                $reason = null;

                if ($start->lessThanOrEqualTo($now)) {
                    $available = false;
                    $reason = 'past';
                }

                if ($available && $this->overlapsUnavailable($blockedStart, $blockedEnd, $unavailability, $day, $timezone)) {
                    $available = false;
                    $reason = 'unavailable';
                }

                $overlapCount = $appointments->filter(
                    fn (Appointment $appointment): bool => $blockedStart->lessThan($appointment->scheduled_end_at)
                        && $blockedEnd->greaterThan($appointment->scheduled_start_at)
                )->count();

                if ($available && $overlapCount >= $capacity) {
                    $available = false;
                    $reason = 'capacity_reached';
                }

                $slots->push([
                    'start_at' => $start->toIso8601String(),
                    'end_at' => $end->toIso8601String(),
                    'label' => $start->format('H:i').' - '.$end->format('H:i'),
                    'available' => $available,
                    'reason' => $reason,
                ]);
            }
        }

        return $slots
            ->unique(fn (array $slot): string => $slot['start_at'].'|'.$slot['end_at'])
            ->sortBy('start_at')
            ->values();
    }

    public function isSlotAvailable(?int $branchId, int $appointmentTypeId, string $startAt, string $endAt, ?string $timezone = null, ?int $excludeAppointmentId = null): bool
    {
        $timezone ??= self::DEFAULT_TIMEZONE;
        $start = CarbonImmutable::parse($startAt, $timezone);
        $end = CarbonImmutable::parse($endAt, $timezone);

        return $this->slots($branchId, $appointmentTypeId, $start->toDateString(), $timezone, $excludeAppointmentId)
            ->contains(fn (array $slot): bool => $slot['available']
                && CarbonImmutable::parse($slot['start_at'])->equalTo($start)
                && CarbonImmutable::parse($slot['end_at'])->equalTo($end));
    }

    protected function overlapsUnavailable(CarbonImmutable $start, CarbonImmutable $end, Collection $periods, CarbonImmutable $day, string $timezone): bool
    {
        return $periods->contains(function (OfficeUnavailabilityPeriod $period) use ($start, $end, $day, $timezone): bool {
            if ($period->repeats_yearly) {
                $periodStart = CarbonImmutable::parse($day->toDateString().' '.$period->starts_at->timezone($timezone)->format('H:i:s'), $timezone);
                $periodEnd = $period->is_full_day
                    ? $day->addDay()
                    : CarbonImmutable::parse($day->toDateString().' '.$period->ends_at->timezone($timezone)->format('H:i:s'), $timezone);
            } else {
                $periodStart = CarbonImmutable::parse($period->starts_at, $timezone);
                $periodEnd = CarbonImmutable::parse($period->ends_at, $timezone);
            }

            return $start->lessThan($periodEnd) && $end->greaterThan($periodStart);
        });
    }
}
