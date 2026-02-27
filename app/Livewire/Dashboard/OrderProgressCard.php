<?php

namespace App\Livewire\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\BranchContext;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrderProgressCard extends Component
{
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
    public function progressData(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $user = auth()->user();
        $requiresBranchSelection = $user && $user->isGlobalAdmin() && ! BranchContext::id();
        if ($requiresBranchSelection) {
            return $this->emptyData($startDate, $endDate, true);
        }

        $baseQuery = Order::query()->dateRange(
            $startDate->toDateString(),
            $endDate->toDateString()
        );

        $inProgressCount = (int) (clone $baseQuery)
            ->whereIn('status', [OrderStatus::InProgress, OrderStatus::Ready])
            ->count();

        $completedCount = (int) (clone $baseQuery)
            ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Completed])
            ->count();

        $upcomingCount = (int) (clone $baseQuery)
            ->where('status', OrderStatus::New)
            ->count();

        $totalTracked = $inProgressCount + $completedCount + $upcomingCount;

        return [
            'in_progress_count' => $inProgressCount,
            'completed_count' => $completedCount,
            'upcoming_count' => $upcomingCount,
            'total_tracked' => $totalTracked,
            'progress_percent' => $totalTracked > 0
                ? (int) round(($completedCount / $totalTracked) * 100)
                : 0,
            'in_progress_width' => $totalTracked > 0 ? ($inProgressCount / $totalTracked) * 100 : 0,
            'completed_width' => $totalTracked > 0 ? ($completedCount / $totalTracked) * 100 : 0,
            'upcoming_width' => $totalTracked > 0 ? ($upcomingCount / $totalTracked) * 100 : 0,
            'requires_branch_selection' => false,
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.order-progress-card');
    }

    protected function resolveDateRange(): array
    {
        if ($this->range === 'this_month') {
            return [now()->startOfMonth(), now()->endOfDay()];
        }

        $months = self::RANGE_MONTHS[$this->range] ?? 3;

        return [
            now()->startOfMonth()->subMonths($months - 1),
            now()->endOfDay(),
        ];
    }

    protected function formatPeriodLabel(CarbonInterface $startDate, CarbonInterface $endDate): string
    {
        if ($startDate->isSameMonth($endDate)) {
            return $startDate->format('M j, Y') . ' - ' . $endDate->format('M j, Y');
        }

        return $startDate->format('M Y') . ' - ' . $endDate->format('M j, Y');
    }

    protected function emptyData(CarbonInterface $startDate, CarbonInterface $endDate, bool $requiresBranchSelection): array
    {
        return [
            'in_progress_count' => 0,
            'completed_count' => 0,
            'upcoming_count' => 0,
            'total_tracked' => 0,
            'progress_percent' => 0,
            'in_progress_width' => 0,
            'completed_width' => 0,
            'upcoming_width' => 0,
            'requires_branch_selection' => $requiresBranchSelection,
            'period_label' => $this->formatPeriodLabel($startDate, $endDate),
        ];
    }
}
