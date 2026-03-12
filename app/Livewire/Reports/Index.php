<?php

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Models\OrderPayment;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Reports')]
class Index extends Component
{
    protected const PERIOD_ALL_TIME = 'all_time';

    protected const PERIOD_PAST_12_MONTHS = 'past_12_months';

    protected const PERIOD_MONTH = 'month';

    #[Url]
    public string $period = self::PERIOD_ALL_TIME;

    #[Url]
    public string $selectedMonth = '';

    public function mount(): void
    {
        if (! auth()->user()->can('reports.view')) {
            abort(403);
        }

        $this->period = $this->normalizePeriod($this->period);
        $this->selectedMonth = $this->normalizeSelectedMonth($this->selectedMonth);
    }

    public function updatedPeriod(string $value): void
    {
        $this->period = $this->normalizePeriod($value);

        if ($this->period === self::PERIOD_MONTH) {
            $this->selectedMonth = $this->normalizeSelectedMonth($this->selectedMonth);
        }
    }

    public function updatedSelectedMonth(string $value): void
    {
        $this->selectedMonth = $this->normalizeSelectedMonth($value);
    }

    #[Computed]
    public function periodOptions(): array
    {
        return [
            self::PERIOD_ALL_TIME => __('All time'),
            self::PERIOD_PAST_12_MONTHS => __('Past 12 months'),
            self::PERIOD_MONTH => __('Specific month'),
        ];
    }

    #[Computed]
    public function monthOptions(): array
    {
        return $this->availableMonthOptions();
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === self::PERIOD_PAST_12_MONTHS) {
            [$startDate, $endDate] = $this->resolveDateRange();

            return __('Past 12 months') . ' (' . $startDate->format('M Y') . ' - ' . $endDate->format('M Y') . ')';
        }

        if ($this->period === self::PERIOD_MONTH) {
            return $this->monthToCarbon($this->selectedMonth)->format('F Y');
        }

        return __('All time');
    }

    #[Computed]
    public function summary(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $incomeQuery = OrderPayment::query()
            ->whereNotNull('paid_at');

        if ($startDate && $endDate) {
            $incomeQuery->whereBetween('paid_at', [$startDate, $endDate]);
        }

        $regularExpenseQuery = Expense::query()
            ->whereNotNull('expense_date');

        if ($startDate && $endDate) {
            $regularExpenseQuery->whereBetween('expense_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);
        }

        $orderExpenseQuery = OrderExpense::query()
            ->whereHas('order');

        if ($startDate && $endDate) {
            $orderExpenseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $ordersQuery = Order::query();

        if ($startDate && $endDate) {
            $ordersQuery->dateRange($startDate->toDateString(), $endDate->toDateString());
        }

        $incomeTotal = (float) (clone $incomeQuery)->sum('amount');
        $regularExpensesTotal = (float) (clone $regularExpenseQuery)->sum('amount');
        $orderExpensesTotal = (float) (clone $orderExpenseQuery)->sum('amount');
        $totalExpenses = $regularExpensesTotal + $orderExpensesTotal;
        $netResult = $incomeTotal - $totalExpenses;

        $resultType = 'break_even';
        if ($netResult > 0.00001) {
            $resultType = 'profit';
        } elseif ($netResult < -0.00001) {
            $resultType = 'loss';
        }

        $profitMargin = $incomeTotal > 0
            ? round(($netResult / $incomeTotal) * 100, 2)
            : null;

        return [
            'income_total' => $incomeTotal,
            'regular_expenses_total' => $regularExpensesTotal,
            'order_expenses_total' => $orderExpensesTotal,
            'total_expenses' => $totalExpenses,
            'net_result' => $netResult,
            'result_type' => $resultType,
            'profit_margin' => $profitMargin,
            'payments_count' => (int) (clone $incomeQuery)->count(),
            'expenses_count' => (int) ((clone $regularExpenseQuery)->count() + (clone $orderExpenseQuery)->count()),
            'orders_count' => (int) (clone $ordersQuery)->count(),
            'orders_value' => (float) (clone $ordersQuery)->sum('total'),
        ];
    }

    #[Computed]
    public function reportLinks(): array
    {
        return [
            [
                'name' => 'Sales Report',
                'description' => 'Payment transactions, totals, and methods analysis.',
                'route' => 'reports.sales',
                'icon' => 'payments',
                'color' => 'emerald',
            ],
            [
                'name' => 'Orders Report',
                'description' => 'Order status, turnaround time, and value analysis.',
                'route' => 'reports.orders',
                'icon' => 'description',
                'color' => 'blue',
            ],
            [
                'name' => 'Expenses Report',
                'description' => 'Expense tracking by category and capital allocation.',
                'route' => 'reports.expenses',
                'icon' => 'receipt',
                'color' => 'red',
            ],
            [
                'name' => 'Inventory Report',
                'description' => 'Stock levels, movements, and low stock alerts.',
                'route' => 'reports.inventory',
                'icon' => 'archive',
                'color' => 'amber',
            ],
            [
                'name' => 'Capital Audit Report',
                'description' => 'Capital allocations, spending, and transactions.',
                'route' => 'reports.capital',
                'icon' => 'account_balance',
                'color' => 'purple',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.reports.index');
    }

    /**
     * Resolve date range from selected period.
     */
    protected function resolveDateRange(): array
    {
        if ($this->period === self::PERIOD_PAST_12_MONTHS) {
            return [
                now()->startOfMonth()->subMonths(11),
                now()->endOfMonth(),
            ];
        }

        if ($this->period === self::PERIOD_MONTH) {
            $month = $this->monthToCarbon($this->selectedMonth);

            return [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ];
        }

        return [null, null];
    }

    /**
     * Ensure period value is valid.
     */
    protected function normalizePeriod(string $value): string
    {
        return in_array($value, [
            self::PERIOD_ALL_TIME,
            self::PERIOD_PAST_12_MONTHS,
            self::PERIOD_MONTH,
        ], true) ? $value : self::PERIOD_ALL_TIME;
    }

    /**
     * Ensure selected month belongs to available options.
     */
    protected function normalizeSelectedMonth(string $value): string
    {
        $options = $this->availableMonthOptions();

        if (array_key_exists($value, $options)) {
            return $value;
        }

        return now()->format('Y-m');
    }

    /**
     * Build list of month options (current + previous 11 months).
     */
    protected function availableMonthOptions(): array
    {
        $options = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $month = now()->startOfMonth()->subMonths($offset);
            $options[$month->format('Y-m')] = $month->format('M Y');
        }

        return $options;
    }

    /**
     * Convert month key (Y-m) into Carbon date.
     */
    protected function monthToCarbon(string $month): Carbon
    {
        $normalized = $this->normalizeSelectedMonth($month);

        return Carbon::createFromFormat('Y-m', $normalized)->startOfMonth();
    }
}
