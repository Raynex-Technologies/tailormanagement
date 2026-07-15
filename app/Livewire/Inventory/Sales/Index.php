<?php

namespace App\Livewire\Inventory\Sales;

use App\Models\PosSale;
use App\Models\User;
use App\Support\SalesPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Sales')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $paymentMethod = '';

    #[Url]
    public string $cashier = '';

    public int $perPage = 15;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('viewAny', PosSale::class);

        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }

        if ($this->dateTo === '') {
            $this->dateTo = now()->endOfMonth()->toDateString();
        }
    }

    public function updatedSearch(): void
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

    public function updatedPaymentMethod(): void
    {
        $this->resetPage();
    }

    public function updatedCashier(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->paymentMethod = '';
        $this->cashier = '';
        $this->resetPage();
    }

    protected function salesQuery(): Builder
    {
        $query = SalesPermissions::applyVisibility(PosSale::query(), auth()->user())
            ->with(['customer:id,name,phone', 'user:id,name', 'branch:id,name'])
            ->withCount('items');

        if ($this->dateFrom !== '') {
            $query->whereDate('sold_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('sold_at', '<=', $this->dateTo);
        }

        if ($this->paymentMethod !== '') {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->cashier !== '') {
            $query->where('user_id', (int) $this->cashier);
        }

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    public function render()
    {
        $salesQuery = $this->salesQuery();
        $summaryQuery = clone $salesQuery;

        $cashiers = SalesPermissions::applyVisibility(PosSale::query(), auth()->user())
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return view('livewire.inventory.sales.index', [
            'sales' => $salesQuery
                ->latest('sold_at')
                ->latest('id')
                ->paginate($this->perPage),
            'summary' => [
                'total_amount' => (float) (clone $summaryQuery)->sum('total_amount'),
                'total_paid' => (float) (clone $summaryQuery)->sum('amount_paid'),
                'sales_count' => (int) (clone $summaryQuery)->count(),
            ],
            'cashiers' => User::query()
                ->whereIn('id', $cashiers)
                ->orderBy('name')
                ->get(['id', 'name']),
            'paymentMethods' => [
                'cash' => __('Cash'),
                'mobile_money' => __('Mobile Money'),
                'card' => __('Card'),
                'bank_transfer' => __('Bank Transfer'),
                'other' => __('Other'),
            ],
        ]);
    }
}
