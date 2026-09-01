<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\Priority;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Support\PaymentPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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

    public function mount(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = now()->endOfMonth()->toDateString();
        }
    }

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
        $this->reset(['search', 'statusFilter', 'tailorFilter', 'priorityFilter']);
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $isTailor = $user->hasRole('tailor');
        $query = $this->filteredOrdersQuery($user->id, $isTailor)
            ->with(['customer', 'assignedTailor', 'lines.assignedTailor']);

        [$dateFrom, $dateTo] = $this->normalizedDateRange();
        $query->dateRange($dateFrom, $dateTo);

        $orders = $query
            ->orderByDesc('order_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $canViewKpis = $user->can('orders.view_kpis');
        $kpis = [];

        if ($canViewKpis) {
            $currentStats = $this->orderStats($dateFrom, $dateTo, $user->id, $isTailor);
            [$previousFrom, $previousTo] = $this->previousMonthRange($dateFrom, $dateTo);
            $previousStats = $this->orderStats($previousFrom, $previousTo, $user->id, $isTailor);
            $kpis = collect($currentStats)->mapWithKeys(fn ($value, $key) => [
                $key => [
                    'value' => $value,
                    'growth' => $this->percentageGrowth($value, $previousStats[$key]),
                ],
            ])->all();
        }

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
            'canViewPayments' => PaymentPermissions::canView($user),
            'canViewKpis' => $canViewKpis,
            'kpis' => $kpis,
            'kpiPeriodLabel' => $canViewKpis ? $this->periodLabel($dateFrom, $dateTo) : null,
        ]);
    }

    protected function filteredOrdersQuery(int $userId, bool $isTailor): Builder
    {
        return Order::query()
            ->search($this->search)
            ->status($this->statusFilter)
            ->assignedTo($this->tailorFilter ?: null)
            ->when($this->priorityFilter !== '', fn ($query) => $query->where('priority', $this->priorityFilter))
            ->when($isTailor, fn (Builder $query) => $query->forTailor($userId));
    }

    protected function orderStats(?string $from, ?string $to, int $userId, bool $isTailor): array
    {
        $paymentTotals = OrderPayment::query()
            ->selectRaw('order_id, SUM(amount) as paid_amount')
            ->groupBy('order_id');

        $stats = $this->filteredOrdersQuery($userId, $isTailor)
            ->dateRange($from, $to)
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.order_id', '=', 'orders.id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(payment_totals.paid_amount), 0) as payments_total')
            ->first();

        $total = (float) ($stats->total_amount ?? 0);
        $paid = (float) ($stats->payments_total ?? 0);

        return [
            'orders' => (int) ($stats->orders_count ?? 0),
            'amount' => $total,
            'paid' => $paid,
            'balance' => max(0, $total - $paid),
        ];
    }

    protected function previousMonthRange(?string $from, ?string $to): array
    {
        $fromDate = $from ? CarbonImmutable::parse($from) : null;
        $toDate = $to ? CarbonImmutable::parse($to) : null;
        $previousTo = $toDate?->subMonthNoOverflow();

        if ($toDate?->isLastOfMonth()) {
            $previousTo = $previousTo?->endOfMonth();
        }

        return [
            $fromDate?->subMonthNoOverflow()->toDateString(),
            $previousTo?->toDateString(),
        ];
    }

    protected function percentageGrowth(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    protected function periodLabel(?string $from, ?string $to): string
    {
        if ($from && $to) {
            return CarbonImmutable::parse($from)->format('M j').' – '.CarbonImmutable::parse($to)->format('M j, Y');
        }

        return __('All time');
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
