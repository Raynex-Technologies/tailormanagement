<?php

namespace App\Livewire\Dashboard;

use App\Models\OrderPayment;
use App\Support\BranchContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaymentMethodDistributionCard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dashboard.payment-methods.view'), 403);
    }

    public string $range = 'this_month';

    protected const RANGE_MONTHS = [
        'this_month' => 1,
        'past_3_months' => 3,
        'past_6_months' => 6,
        'past_12_months' => 12,
    ];

    protected const CHART_COLORS = [
        '#84CC16',
        '#3B82F6',
        '#F59E0B',
        '#EF4444',
        '#8B5CF6',
        '#14B8A6',
        '#EC4899',
        '#6B7280',
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
    public function distribution(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $user = auth()->user();
        if ($user && $user->isGlobalAdmin() && ! BranchContext::id()) {
            return $this->emptyDistribution($startDate, $endDate);
        }

        $methodExpr = "COALESCE(payment_methods.name, 'Default')";

        $rows = OrderPayment::query()
            ->leftJoin('payment_methods', 'order_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('order_payments.paid_at', [$startDate, $endDate])
            ->selectRaw($methodExpr.' as method_name')
            ->selectRaw('SUM(order_payments.amount) as total_amount')
            ->selectRaw('COUNT(order_payments.id) as payments_count')
            ->groupBy(DB::raw($methodExpr))
            ->orderByDesc('total_amount')
            ->get();

        $totalAmount = (float) $rows->sum(fn ($row) => (float) $row->total_amount);
        $totalPayments = (int) $rows->sum(fn ($row) => (int) $row->payments_count);

        $cursor = 0.0;
        $segments = [];

        $items = $rows->values()->map(function ($row, int $index) use ($totalAmount, &$cursor, &$segments) {
            $amount = (float) $row->total_amount;
            $percentage = $totalAmount > 0 ? (($amount / $totalAmount) * 100) : 0;
            $end = min(100, $cursor + $percentage);
            $color = self::CHART_COLORS[$index % count(self::CHART_COLORS)];

            if ($percentage > 0) {
                $segments[] = sprintf(
                    '%s %s%% %s%%',
                    $color,
                    $this->formatPercent($cursor),
                    $this->formatPercent($end)
                );
            }

            $cursor = $end;

            return [
                'name' => $row->method_name ?: 'Default',
                'amount' => $amount,
                'count' => (int) $row->payments_count,
                'percentage' => round($percentage, 1),
                'color' => $color,
            ];
        })->all();

        if ($totalAmount > 0 && ! empty($items) && $cursor < 100) {
            $lastColor = $items[array_key_last($items)]['color'];
            $segments[] = sprintf('%s %s%% 100%%', $lastColor, $this->formatPercent($cursor));
        }

        return [
            'chart_gradient' => $totalAmount > 0
                ? 'conic-gradient('.implode(', ', $segments).')'
                : 'conic-gradient(#E5E7EB 0% 100%)',
            'items' => $items,
            'total_amount' => $totalAmount,
            'total_payments' => $totalPayments,
            'has_data' => $totalAmount > 0 && ! empty($items),
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.payment-method-distribution-card');
    }

    protected function resolveDateRange(): array
    {
        $months = self::RANGE_MONTHS[$this->range] ?? 1;
        $endDate = now()->endOfMonth();
        $startDate = now()->startOfMonth()->subMonths($months - 1);

        return [$startDate, $endDate];
    }

    protected function formatPeriodLabel(CarbonInterface $startDate, CarbonInterface $endDate): string
    {
        if ($startDate->isSameMonth($endDate)) {
            return $startDate->format('M Y');
        }

        return $startDate->format('M Y').' - '.$endDate->format('M Y');
    }

    protected function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    protected function emptyDistribution(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return [
            'chart_gradient' => 'conic-gradient(#E5E7EB 0% 100%)',
            'items' => [],
            'total_amount' => 0.0,
            'total_payments' => 0,
            'has_data' => false,
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }
}
