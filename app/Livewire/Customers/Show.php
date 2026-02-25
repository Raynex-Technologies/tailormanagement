<?php

namespace App\Livewire\Customers;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests, WithPagination;

    public Customer $customer;
    public int $ordersPerPage = 10;

    protected string $paginationTheme = 'tailwind';

    public function mount(Customer $customer): void
    {
        $this->authorize('users.view');

        $actor = auth()->user();
        $activeBranchId = BranchContext::id();

        if ($actor && ! $actor->isGlobalAdmin() && $customer->branch_id !== $actor->branch_id) {
            abort(404);
        }

        if ($actor && $actor->isGlobalAdmin() && $activeBranchId && $customer->branch_id !== $activeBranchId) {
            abort(404);
        }

        $this->customer = $customer->load('branch');
    }

    public function updatingOrdersPerPage(): void
    {
        $this->resetPage('ordersPage');
    }

    public function render()
    {
        $ordersQuery = $this->customer->orders()
            ->with(['assignedTailor', 'creator'])
            ->orderByDesc('order_date')
            ->orderByDesc('created_at');

        $stats = [
            'total_orders' => (clone $ordersQuery)->count(),
            'completed_orders' => (clone $ordersQuery)->where('status', 'completed')->count(),
            'open_order_value' => (clone $ordersQuery)
                ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value])
                ->sum('total'),
            'lifetime_value' => (clone $ordersQuery)->sum('total'),
        ];

        $orders = $ordersQuery->paginate($this->ordersPerPage, ['*'], 'ordersPage');

        return view('livewire.customers.show', [
            'orders' => $orders,
            'stats' => $stats,
            'canManage' => auth()->user()?->can('users.manage') ?? false,
        ])->title("Customer: {$this->customer->name}");
    }
}
