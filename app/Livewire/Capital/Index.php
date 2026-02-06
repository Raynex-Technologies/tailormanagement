<?php

namespace App\Livewire\Capital;

use App\Enums\CapitalAllocationStatus;
use App\Models\CapitalAllocation;
use App\Services\Capital\CapitalAllocationService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('capital.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(?string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function render()
    {
        $branchId = BranchContext::id();

        $query = CapitalAllocation::with(['accountant', 'creator'])
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('allocation_no', 'like', "%{$this->search}%")
                    ->orWhereHas('accountant', fn ($aq) => $aq->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $allocations = $query->paginate(15);

        // Stats
        $stats = [
            'total' => CapitalAllocation::count(),
            'open' => CapitalAllocation::where('status', CapitalAllocationStatus::Open)->count(),
            'total_allocated' => CapitalAllocation::sum('initial_amount'),
            'total_spent' => CapitalAllocation::sum('spent_amount'),
        ];

        return view('livewire.capital.index', [
            'allocations' => $allocations,
            'stats' => $stats,
            'statuses' => CapitalAllocationStatus::cases(),
        ])->title(__('Capital Allocations'));
    }
}
