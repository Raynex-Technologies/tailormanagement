<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Models\OrderPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);

        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = now()->endOfMonth()->toDateString();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $allowed = [10, 15, 25, 50];
        if (! in_array($this->perPage, $allowed, true)) {
            $this->perPage = 15;
        }
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

    public function clearFilters(): void
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $isTailor = $user->hasRole('tailor');
        [$dateFrom, $dateTo] = $this->normalizedDateRange();

        $query = $this->filteredInvoicesQuery($user->id, $isTailor)
            ->issueDateRange($dateFrom, $dateTo)
            ->with([
                'order' => fn ($orderQuery) => $orderQuery
                    ->with('customer')
                    ->withSum('payments', 'amount'),
                'branch',
            ]);

        $invoices = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $canViewKpis = $user->can('invoices.view_kpis');
        $kpis = [];

        if ($canViewKpis) {
            $currentStats = $this->invoiceStats($dateFrom, $dateTo, $user->id, $isTailor);
            [$previousFrom, $previousTo] = $this->previousMonthRange($dateFrom, $dateTo);
            $previousStats = $this->invoiceStats($previousFrom, $previousTo, $user->id, $isTailor);
            $kpis = collect($currentStats)->mapWithKeys(fn ($value, $key) => [
                $key => [
                    'value' => $value,
                    'growth' => $this->percentageGrowth($value, $previousStats[$key]),
                ],
            ])->all();
        }

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'canViewKpis' => $canViewKpis,
            'kpis' => $kpis,
            'kpiPeriodLabel' => $canViewKpis ? $this->periodLabel($dateFrom, $dateTo) : null,
        ]);
    }

    protected function filteredInvoicesQuery(int $userId, bool $isTailor): Builder
    {
        return Invoice::query()
            ->search($this->search)
            ->when($isTailor, fn (Builder $query) => $query->whereHas(
                'order',
                fn (Builder $orderQuery) => $orderQuery->forTailor($userId),
            ));
    }

    protected function invoiceStats(?string $from, ?string $to, int $userId, bool $isTailor): array
    {
        $paymentTotals = OrderPayment::query()
            ->selectRaw('order_id, SUM(amount) as paid_amount')
            ->groupBy('order_id');

        $stats = $this->filteredInvoicesQuery($userId, $isTailor)
            ->issueDateRange($from, $to)
            ->leftJoinSub($paymentTotals, 'invoice_payment_totals', 'invoice_payment_totals.order_id', '=', 'invoices.order_id')
            ->selectRaw('COUNT(invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total), 0) as invoiced_total')
            ->selectRaw('COALESCE(SUM(invoice_payment_totals.paid_amount), 0) as invoice_payments_total')
            ->first();

        $total = (float) ($stats->invoiced_total ?? 0);
        $paid = (float) ($stats->invoice_payments_total ?? 0);

        return [
            'invoices' => (int) ($stats->invoice_count ?? 0),
            'amount' => $total,
            'paid' => $paid,
            'outstanding' => max(0, $total - $paid),
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
