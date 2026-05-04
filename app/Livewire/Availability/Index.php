<?php

namespace App\Livewire\Availability;

use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OfficeUnavailabilityPeriod;
use App\Services\Appointments\AppointmentAvailabilityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests;

    public string $tab = 'windows';

    public ?int $windowId = null;
    public ?int $windowBranchId = null;
    public ?int $windowAppointmentTypeId = null;
    public int $dayOfWeek = 1;
    public string $startTime = '08:00';
    public string $endTime = '17:00';
    public int $slotIntervalMinutes = 30;
    public int $capacity = 1;
    public string $effectiveFrom = '';
    public string $effectiveUntil = '';
    public bool $windowActive = true;

    public ?int $blockId = null;
    public ?int $blockBranchId = null;
    public ?int $blockAppointmentTypeId = null;
    public string $blockTitle = '';
    public string $blockReason = '';
    public string $startsAt = '';
    public string $endsAt = '';
    public bool $isFullDay = false;
    public bool $repeatsYearly = false;

    public ?int $previewBranchId = null;
    public ?int $previewAppointmentTypeId = null;
    public string $previewDate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OfficeAvailabilityWindow::class);
        $this->previewDate = now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->addDay()->toDateString();
        $this->previewAppointmentTypeId = AppointmentType::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function saveWindow(): void
    {
        $this->authorize('manage', OfficeAvailabilityWindow::class);
        $this->validate([
            'windowBranchId' => ['nullable', 'exists:branches,id'],
            'windowAppointmentTypeId' => ['nullable', 'exists:appointment_types,id'],
            'dayOfWeek' => ['required', 'integer', 'between:1,7'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'slotIntervalMinutes' => ['required', 'integer', 'min:5', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
        ]);

        OfficeAvailabilityWindow::query()->updateOrCreate(
            ['id' => $this->windowId],
            [
                'branch_id' => $this->windowBranchId,
                'appointment_type_id' => $this->windowAppointmentTypeId,
                'day_of_week' => $this->dayOfWeek,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'slot_interval_minutes' => $this->slotIntervalMinutes,
                'capacity' => $this->capacity,
                'effective_from' => $this->effectiveFrom ?: null,
                'effective_until' => $this->effectiveUntil ?: null,
                'is_active' => $this->windowActive,
                'created_by' => $this->windowId ? null : auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        session()->flash('success', 'Availability window saved.');
        $this->resetWindow();
    }

    public function editWindow(int $id): void
    {
        $window = OfficeAvailabilityWindow::query()->findOrFail($id);
        $this->authorize('manage', OfficeAvailabilityWindow::class);

        $this->windowId = $window->id;
        $this->windowBranchId = $window->branch_id;
        $this->windowAppointmentTypeId = $window->appointment_type_id;
        $this->dayOfWeek = $window->day_of_week;
        $this->startTime = substr((string) $window->start_time, 0, 5);
        $this->endTime = substr((string) $window->end_time, 0, 5);
        $this->slotIntervalMinutes = $window->slot_interval_minutes;
        $this->capacity = $window->capacity;
        $this->effectiveFrom = $window->effective_from?->format('Y-m-d') ?? '';
        $this->effectiveUntil = $window->effective_until?->format('Y-m-d') ?? '';
        $this->windowActive = $window->is_active;
    }

    public function saveBlock(): void
    {
        $this->authorize('manage', OfficeUnavailabilityPeriod::class);
        $this->validate([
            'blockBranchId' => ['nullable', 'exists:branches,id'],
            'blockAppointmentTypeId' => ['nullable', 'exists:appointment_types,id'],
            'blockTitle' => ['required', 'string', 'max:191'],
            'blockReason' => ['nullable', 'string', 'max:1000'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ]);

        OfficeUnavailabilityPeriod::query()->updateOrCreate(
            ['id' => $this->blockId],
            [
                'branch_id' => $this->blockBranchId,
                'appointment_type_id' => $this->blockAppointmentTypeId,
                'title' => $this->blockTitle,
                'reason' => $this->blockReason ?: null,
                'starts_at' => $this->startsAt,
                'ends_at' => $this->endsAt,
                'is_full_day' => $this->isFullDay,
                'repeats_yearly' => $this->repeatsYearly,
                'created_by' => auth()->id(),
            ]
        );

        session()->flash('success', 'Unavailable period saved.');
        $this->resetBlock();
    }

    public function editBlock(int $id): void
    {
        $block = OfficeUnavailabilityPeriod::query()->findOrFail($id);
        $this->authorize('manage', OfficeUnavailabilityPeriod::class);

        $this->blockId = $block->id;
        $this->blockBranchId = $block->branch_id;
        $this->blockAppointmentTypeId = $block->appointment_type_id;
        $this->blockTitle = $block->title;
        $this->blockReason = $block->reason ?? '';
        $this->startsAt = $block->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $block->ends_at->format('Y-m-d\TH:i');
        $this->isFullDay = $block->is_full_day;
        $this->repeatsYearly = $block->repeats_yearly;
    }

    public function saveAppointmentType(int $typeId, string $field, mixed $value): void
    {
        $this->authorize('manage', OfficeAvailabilityWindow::class);
        abort_unless(in_array($field, ['default_duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'requires_approval', 'is_public', 'is_active'], true), 403);

        $type = AppointmentType::query()->findOrFail($typeId);
        $type->forceFill([$field => $value])->save();
    }

    public function resetWindow(): void
    {
        $this->reset(['windowId', 'windowBranchId', 'windowAppointmentTypeId', 'effectiveFrom', 'effectiveUntil']);
        $this->dayOfWeek = 1;
        $this->startTime = '08:00';
        $this->endTime = '17:00';
        $this->slotIntervalMinutes = 30;
        $this->capacity = 1;
        $this->windowActive = true;
    }

    public function resetBlock(): void
    {
        $this->reset(['blockId', 'blockBranchId', 'blockAppointmentTypeId', 'blockTitle', 'blockReason', 'startsAt', 'endsAt', 'isFullDay', 'repeatsYearly']);
    }

    public function render()
    {
        $previewSlots = $this->previewAppointmentTypeId
            ? app(AppointmentAvailabilityService::class)->slots($this->previewBranchId, $this->previewAppointmentTypeId, $this->previewDate)
            : collect();

        return view('livewire.availability.index', [
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'types' => AppointmentType::query()->orderBy('sort_order')->get(),
            'windows' => OfficeAvailabilityWindow::query()->with(['branch', 'appointmentType'])->orderBy('day_of_week')->orderBy('start_time')->get(),
            'blocks' => OfficeUnavailabilityPeriod::query()->with(['branch', 'appointmentType'])->latest('starts_at')->limit(50)->get(),
            'previewSlots' => $previewSlots,
            'days' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        ])->title(__('Availability Settings'));
    }
}
