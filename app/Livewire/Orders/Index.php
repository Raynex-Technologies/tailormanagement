<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\Priority;
use App\Models\Order;
use App\Models\User;
use App\Support\PaymentPermissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Orders Management')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $tailorFilter = '';

    #[Url]
    public string $priorityFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTailorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'tailorFilter', 'priorityFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $query = Order::query()
            ->with(['customer', 'assignedTailor', 'lines.assignedTailor'])
            ->search($this->search)
            ->status($this->statusFilter)
            ->assignedTo($this->tailorFilter ?: null)
            ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter));

        [$dateFrom, $dateTo] = $this->normalizedDateRange();
        $query->dateRange($dateFrom, $dateTo);

        // For tailors, show only their assigned orders
        if ($user->hasRole('tailor')) {
            $query->forTailor($user->id);
        }

        $orders = $query
            ->orderByDesc('order_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        // Get statuses for filter
        $statuses = collect(OrderStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()]);

        $priorities = collect(Priority::cases())
            ->mapWithKeys(fn ($priority) => [$priority->value => $priority->label()]);

        // Get tailors for filter (only from current branch context)
        $tailors = User::whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
            ->orderBy('name')
            ->pluck('name', 'id');

        // Determine if user can see financial columns (Total, Payment Status)
        // Storekeepers cannot see these columns
        $canViewFinancials = $user->can('orders.view_financials');

        return view('livewire.orders.index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'tailors' => $tailors,
            'canViewFinancials' => $canViewFinancials,
            'canCreatePayments' => PaymentPermissions::canCreate($user),
            'canViewPayments' => PaymentPermissions::canView($user),
        ]);
    }

    protected function normalizedDateRange(): array
    {
        $from = $this->dateFrom !== '' ? $this->dateFrom : null;
        $to = $this->dateTo !== '' ? $this->dateTo : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
