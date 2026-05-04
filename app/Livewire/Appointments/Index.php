<?php

namespace App\Livewire\Appointments;

use App\Actions\Appointments\ApproveAppointmentAction;
use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\DeclineAppointmentAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Services\Appointments\AppointmentAvailabilityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $date = '';
    public string $status = '';
    public ?int $branchId = null;
    public ?int $appointmentTypeId = null;
    public int $perPage = 15;

    public ?int $selectedAppointmentId = null;
    public string $actionNote = '';
    public string $rescheduleDate = '';
    public string $selectedSlot = '';
    public array $availableSlots = [];

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
        $this->date = now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->toDateString();
        $this->rescheduleDate = now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->addDay()->toDateString();
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'date', 'status', 'branchId', 'appointmentTypeId', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function selectAppointment(int $appointmentId): void
    {
        $appointment = $this->baseAppointmentQuery()->findOrFail($appointmentId);
        $this->authorize('view', $appointment);

        $this->selectedAppointmentId = $appointment->id;
        $this->actionNote = '';
        $this->rescheduleDate = $appointment->scheduled_start_at->copy()->addDay()->toDateString();
        $this->selectedSlot = '';
        $this->refreshSlots();
    }

    public function approve(ApproveAppointmentAction $action): void
    {
        $appointment = $this->selectedAppointmentOrFail();
        $this->authorize('approve', Appointment::class);
        $action->execute($appointment, auth()->id());
        session()->flash('success', 'Appointment approved.');
        $this->closeDetails();
    }

    public function decline(DeclineAppointmentAction $action): void
    {
        $this->validate(['actionNote' => ['required', 'string', 'max:1000']]);
        $appointment = $this->selectedAppointmentOrFail();
        $this->authorize('decline', Appointment::class);
        $action->execute($appointment, $this->actionNote, auth()->id());
        session()->flash('success', 'Appointment declined.');
        $this->closeDetails();
    }

    public function cancel(CancelAppointmentAction $action): void
    {
        $this->validate(['actionNote' => ['nullable', 'string', 'max:1000']]);
        $appointment = $this->selectedAppointmentOrFail();
        $this->authorize('cancel', Appointment::class);
        $action->execute($appointment, $this->actionNote ?: 'Appointment cancelled.', auth()->id());
        session()->flash('success', 'Appointment cancelled.');
        $this->closeDetails();
    }

    public function complete(): void
    {
        $appointment = $this->selectedAppointmentOrFail();
        $this->authorize('complete', Appointment::class);
        $old = $appointment->status;
        $appointment->forceFill(['status' => 'completed'])->save();
        $appointment->statusHistories()->create([
            'old_status' => $old,
            'new_status' => 'completed',
            'changed_by' => auth()->id(),
            'note' => 'Appointment completed.',
        ]);
        session()->flash('success', 'Appointment marked completed.');
        $this->closeDetails();
    }

    public function updatedRescheduleDate(): void
    {
        $this->selectedSlot = '';
        $this->refreshSlots();
    }

    public function refreshSlots(): void
    {
        $appointment = $this->selectedAppointment();
        if (! $appointment || ! $this->rescheduleDate) {
            $this->availableSlots = [];
            return;
        }

        $this->availableSlots = app(AppointmentAvailabilityService::class)
            ->slots($appointment->branch_id, $appointment->appointment_type_id, $this->rescheduleDate, null, $appointment->id)
            ->where('available', true)
            ->values()
            ->all();
    }

    public function reschedule(RescheduleAppointmentAction $action): void
    {
        $appointment = $this->selectedAppointmentOrFail();
        $this->authorize('reschedule', Appointment::class);
        $this->validate([
            'rescheduleDate' => ['required', 'date', 'after_or_equal:today'],
            'selectedSlot' => ['required', 'string'],
            'actionNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = collect($this->availableSlots)->firstWhere('start_at', $this->selectedSlot);
        abort_if(! $slot, 422, 'Selected slot is unavailable.');

        $action->execute($appointment, $slot['start_at'], $slot['end_at'], $this->actionNote ?: null, auth()->id());
        session()->flash('success', 'Appointment rescheduled.');
        $this->closeDetails();
    }

    public function closeDetails(): void
    {
        $this->reset(['selectedAppointmentId', 'actionNote', 'selectedSlot', 'availableSlots']);
    }

    public function render()
    {
        $appointments = $this->appointmentQuery()
            ->with(['type', 'branch', 'booking'])
            ->orderBy('scheduled_start_at')
            ->paginate($this->perPage);

        return view('livewire.appointments.index', [
            'appointments' => $appointments,
            'selectedAppointment' => $this->selectedAppointment(),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'types' => AppointmentType::query()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => ['requested', 'pending_approval', 'confirmed', 'declined', 'reschedule_requested', 'rescheduled', 'cancelled', 'completed', 'no_show'],
        ])->title(__('Appointments'));
    }

    protected function selectedAppointmentOrFail(): Appointment
    {
        return $this->selectedAppointment() ?? abort(404);
    }

    protected function selectedAppointment(): ?Appointment
    {
        if (! $this->selectedAppointmentId) {
            return null;
        }

        return $this->baseAppointmentQuery()
            ->with(['type', 'branch', 'booking', 'statusHistories'])
            ->find($this->selectedAppointmentId);
    }

    protected function appointmentQuery()
    {
        return $this->baseAppointmentQuery()
            ->when($this->search !== '', fn ($query) => $query->where(function ($search) {
                $search->where('appointment_number', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_phone', 'like', "%{$this->search}%");
            }))
            ->when($this->date !== '', fn ($query) => $query->whereDate('scheduled_start_at', $this->date))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->when($this->appointmentTypeId, fn ($query) => $query->where('appointment_type_id', $this->appointmentTypeId));
    }

    protected function baseAppointmentQuery()
    {
        $user = auth()->user();

        return Appointment::query()
            ->when(! $user?->isGlobalAdmin(), fn ($query) => $query->where(function ($branch) use ($user) {
                $branch->whereNull('branch_id')->orWhere('branch_id', $user?->branch_id);
            }));
    }
}
