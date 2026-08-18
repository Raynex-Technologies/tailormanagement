<?php

namespace App\Livewire\Dashboard;

use App\Models\Expense;
use App\Models\OrderExpense;
use App\Models\OrderPayment;
use App\Models\PosSale;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Component;

class IncomeExpensesChartCard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dashboard.chart.income-expenses.view'), 403);
    }

    public string $range = 'this_month';

    protected const RANGE_MONTHS = [
        'this_month' => 1,
        'past_3_months' => 3,
        'past_6_months' => 6,
        'past_12_months' => 12,
    ];

    public function updatedRange(string $value): void
    {
        if (! array_key_exists($value, self::RANGE_MONTHS)) {
            $this->range = 'this_month';
        }
    }

    #[Computed]
    public function rangeOptions(): array
    {
        return [
            'this_month' => __('This month'),
            'past_3_months' => __('Past 3 months'),
            'past_6_months' => __('Past 6 months'),
            'past_12_months' => __('Past 12 months'),
        ];
    }

    #[Computed]
    public function chartData(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();
        $groupByMonth = $this->range !== 'this_month';

        $user = auth()->user();
        $requiresBranchSelection = $user && $user->isGlobalAdmin() && ! BranchContext::id();
        if ($requiresBranchSelection) {
            return $this->emptyChartData($startDate, $endDate, true, $groupByMonth);
        }

        $periods = $this->buildPeriods($startDate, $endDate, $groupByMonth);
        $incomeTotals = $this->collectIncomeTotals($startDate, $endDate, $groupByMonth);
        $expenseTotals = $this->collectExpenseTotals($startDate, $endDate, $groupByMonth);

        $categories = [];
        $incomeSeries = [];
        $expenseSeries = [];

        foreach ($periods as $key => $label) {
            $categories[] = $label;
            $incomeSeries[] = round((float) ($incomeTotals[$key] ?? 0), 2);
            $expenseSeries[] = round((float) ($expenseTotals[$key] ?? 0), 2);
        }

        $incomeTotal = round(array_sum($incomeSeries), 2);
        $expenseTotal = round(array_sum($expenseSeries), 2);

        return [
            'categories' => $categories,
            'series' => [
                ['name' => __('Income'), 'data' => $incomeSeries],
                ['name' => __('Expenses'), 'data' => $expenseSeries],
            ],
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'net_total' => round($incomeTotal - $expenseTotal, 2),
            'has_data' => $incomeTotal > 0 || $expenseTotal > 0,
            'requires_branch_selection' => false,
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }

    #[Computed]
    public function chartKey(): string
    {
        return 'income-expenses-'.md5(json_encode($this->chartData) ?: '');
    }

    public function render()
    {
        return view('livewire.dashboard.income-expenses-chart-card');
    }

    protected function resolveDateRange(): array
    {
        if ($this->range === 'this_month') {
            return [now()->startOfMonth(), now()->endOfDay()];
        }

        $months = self::RANGE_MONTHS[$this->range] ?? 3;

        return [
            now()->startOfMonth()->subMonths($months - 1),
            now()->endOfMonth(),
        ];
    }

    protected function buildPeriods(CarbonInterface $startDate, CarbonInterface $endDate, bool $groupByMonth): array
    {
        $periods = [];
        $cursor = CarbonImmutable::instance($startDate);
        $end = CarbonImmutable::instance($endDate);

        if ($groupByMonth) {
            $cursor = $cursor->startOfMonth();
            $end = $end->endOfMonth();

            while ($cursor <= $end) {
                $periods[$cursor->format('Y-m')] = $cursor->format('M Y');
                $cursor = $cursor->addMonth();
            }

            return $periods;
        }

        $cursor = $cursor->startOfDay();
        $end = $end->endOfDay();

        while ($cursor <= $end) {
            $periods[$cursor->format('Y-m-d')] = $cursor->format('j M');
            $cursor = $cursor->addDay();
        }

        return $periods;
    }

    protected function collectIncomeTotals(CarbonInterface $startDate, CarbonInterface $endDate, bool $groupByMonth): array
    {
        $incomeTotals = OrderPayment::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (OrderPayment $payment) => $groupByMonth
                ? $payment->paid_at?->format('Y-m')
                : $payment->paid_at?->format('Y-m-d'))
            ->map(fn ($payments) => (float) $payments->sum(fn ($payment) => (float) $payment->amount))
            ->filter(fn ($value, $key) => filled($key))
            ->all();

        $posTotals = PosSale::query()
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$startDate, $endDate])
            ->get(['sold_at', 'total_amount'])
            ->groupBy(fn (PosSale $sale) => $groupByMonth
                ? $sale->sold_at?->format('Y-m')
                : $sale->sold_at?->format('Y-m-d'))
            ->map(fn ($sales) => (float) $sales->sum(fn ($sale) => (float) $sale->total_amount))
            ->filter(fn ($value, $key) => filled($key))
            ->all();

        foreach ($posTotals as $period => $amount) {
            $incomeTotals[$period] = ($incomeTotals[$period] ?? 0) + (float) $amount;
        }

        return $incomeTotals;
    }

    protected function collectExpenseTotals(CarbonInterface $startDate, CarbonInterface $endDate, bool $groupByMonth): array
    {
        $expenseTotals = Expense::query()
            ->whereNotNull('expense_date')
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['expense_date', 'amount'])
            ->groupBy(fn (Expense $expense) => $groupByMonth
                ? $expense->expense_date?->format('Y-m')
                : $expense->expense_date?->format('Y-m-d'))
            ->map(fn ($expenses) => (float) $expenses->sum(fn ($expense) => (float) $expense->amount))
            ->filter(fn ($value, $key) => filled($key))
            ->all();

        $orderExpenseTotals = OrderExpense::query()
            ->whereHas('order')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['created_at', 'amount'])
            ->groupBy(fn (OrderExpense $expense) => $groupByMonth
                ? $expense->created_at?->format('Y-m')
                : $expense->created_at?->format('Y-m-d'))
            ->map(fn ($expenses) => (float) $expenses->sum(fn ($expense) => (float) $expense->amount))
            ->filter(fn ($value, $key) => filled($key))
            ->all();

        $combinedTotals = $expenseTotals;
        foreach ($orderExpenseTotals as $period => $amount) {
            $combinedTotals[$period] = ($combinedTotals[$period] ?? 0) + (float) $amount;
        }

        return $combinedTotals;
    }

    protected function formatPeriodLabel(CarbonInterface $startDate, CarbonInterface $endDate): string
    {
        if ($startDate->isSameMonth($endDate)) {
            return $startDate->format('M j, Y').' - '.$endDate->format('M j, Y');
        }

        return $startDate->format('M Y').' - '.$endDate->format('M Y');
    }

    protected function emptyChartData(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        bool $requiresBranchSelection,
        bool $groupByMonth
    ): array {
        return [
            'categories' => array_values($this->buildPeriods($startDate, $endDate, $groupByMonth)),
            'series' => [
                ['name' => __('Income'), 'data' => []],
                ['name' => __('Expenses'), 'data' => []],
            ],
            'income_total' => 0.0,
            'expense_total' => 0.0,
            'net_total' => 0.0,
            'has_data' => false,
            'requires_branch_selection' => $requiresBranchSelection,
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }
}
