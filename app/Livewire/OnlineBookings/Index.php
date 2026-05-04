<?php

namespace App\Livewire\OnlineBookings;

use App\Actions\Bookings\UpdateOnlineBookingStatusAction;
use App\Models\Branch;
use App\Models\OnlineBooking;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $status = '';
    public string $bookingType = '';
    public ?int $branchId = null;
    public bool $urgentOnly = false;
    public int $perPage = 15;

    public ?int $selectedBookingId = null;
    public bool $showDetailsModal = false;
    public string $targetStatus = 'confirmed';
    public string $reviewNote = '';

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'bookingType' => ['except' => ''],
        'branchId' => ['except' => null],
        'urgentOnly' => ['except' => false],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', OnlineBooking::class);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'status', 'bookingType', 'branchId', 'urgentOnly', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'bookingType', 'branchId', 'urgentOnly']);
        $this->resetPage();
    }

    public function selectBooking(int $bookingId): void
    {
        $booking = $this->bookingQuery()->findOrFail($bookingId);
        $this->authorize('view', $booking);

        $this->selectedBookingId = $booking->id;
        $this->showDetailsModal = true;
        $this->targetStatus = $booking->status === 'declined' ? 'pending_review' : $booking->status;
        $this->reviewNote = '';
    }

    public function closeDetails(): void
    {
        $this->reset(['selectedBookingId', 'showDetailsModal', 'reviewNote']);
        $this->targetStatus = 'confirmed';
    }

    public function updatedShowDetailsModal(bool $open): void
    {
        if (! $open) {
            $this->closeDetails();
        }
    }

    public function updateStatus(UpdateOnlineBookingStatusAction $action): void
    {
        $booking = $this->selectedBooking();
        abort_unless($booking, 404);
        $this->authorize('review', OnlineBooking::class);

        $this->validate([
            'targetStatus' => ['required', Rule::in(OnlineBooking::STATUSES)],
            'reviewNote' => [$this->targetStatus === 'declined' ? 'required' : 'nullable', 'string', 'max:2000'],
        ]);

        $action->execute($booking, $this->targetStatus, $this->reviewNote ?: null, auth()->id());

        session()->flash('success', 'Booking status updated.');
        $this->closeDetails();
    }

    public function render()
    {
        $bookings = $this->bookingQuery()
            ->with(['branch', 'appointment.type'])
            ->withCount('items')
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.online-bookings.index', [
            'bookings' => $bookings,
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'selectedBooking' => $this->selectedBooking(),
            'types' => OnlineBooking::TYPES,
            'statuses' => OnlineBooking::STATUSES,
        ])->title(__('Online Bookings'));
    }

    protected function selectedBooking(): ?OnlineBooking
    {
        if (! $this->selectedBookingId) {
            return null;
        }

        return $this->bookingQuery()
            ->with(['branch', 'customer', 'items.category', 'items.selectedOptions.group', 'items.selectedOptions.option', 'images', 'appointment.type', 'appointment.statusHistories'])
            ->find($this->selectedBookingId);
    }

    protected function bookingQuery()
    {
        $user = auth()->user();

        return OnlineBooking::query()
            ->when(! $user?->isGlobalAdmin(), fn ($query) => $query->where(function ($branch) use ($user) {
                $branch->whereNull('branch_id')->orWhere('branch_id', $user?->branch_id);
            }))
            ->when($this->search !== '', fn ($query) => $query->where(function ($search) {
                $search->where('booking_number', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_phone', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->bookingType !== '', fn ($query) => $query->where('booking_type', $this->bookingType))
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->when($this->urgentOnly, fn ($query) => $query->where('is_urgent', true));
    }
}
