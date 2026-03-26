<?php

namespace App\Livewire\Dashboard;

use App\Services\Calendar\BookingEventService;
use App\Services\Calendar\CalendarEventService;
use App\Support\BranchContext;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CalendarCard extends Component
{
    protected const PREVIEW_WEEKS = 4;

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(string $value): void
    {
        $this->month = $this->normalizeMonth($value);
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthDate()->addMonth()->format('Y-m');
    }

    #[Computed]
    public function monthData(): array
    {
        $monthDate = $this->monthDate();
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $gridStart = $monthDate->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $user = auth()->user();
        $requiresBranchSelection = $user && $user->isGlobalAdmin() && ! BranchContext::id();
        $canViewOrderDates = (bool) $user?->can('orders.view');

        $orderDueDates = [];
        if ($canViewOrderDates && ! $requiresBranchSelection) {
            $orderDueDates = app(CalendarEventService::class)->dueDateCounts($monthStart, $monthEnd);
        }

        $bookingDates = $this->bookingDateCounts($monthStart, $monthEnd);
        $today = now()->toDateString();
        $cursor = $gridStart->copy();
        $weeks = [];

        while ($cursor <= $gridEnd) {
            $week = [];

            for ($index = 0; $index < 7; $index++) {
                $date = $cursor->toDateString();

                $week[] = [
                    'date' => $date,
                    'day' => $cursor->day,
                    'in_month' => $cursor->month === $monthDate->month,
                    'is_today' => $date === $today,
                    'has_orders' => array_key_exists($date, $orderDueDates),
                    'has_bookings' => array_key_exists($date, $bookingDates),
                    'orders_count' => (int) ($orderDueDates[$date] ?? 0),
                    'bookings_count' => (int) ($bookingDates[$date] ?? 0),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'month_label' => $monthDate->format('F Y'),
            'weeks' => $this->compactPreviewWeeks($weeks, $monthDate, $today),
            'weekdays' => ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
            'requires_branch_selection' => $requiresBranchSelection,
            'can_view_order_dates' => $canViewOrderDates,
            'has_booking_dates' => count($bookingDates) > 0,
        ];
    }

    #[Computed]
    public function upcomingDueOrders(): array
    {
        $user = auth()->user();
        $requiresBranchSelection = $user && $user->isGlobalAdmin() && ! BranchContext::id();
        $canViewOrderDates = (bool) $user?->can('orders.view');

        if (! $canViewOrderDates || $requiresBranchSelection) {
            return [
                'items' => [],
                'can_view_order_dates' => $canViewOrderDates,
                'requires_branch_selection' => $requiresBranchSelection,
            ];
        }

        $today = now()->startOfDay();
        $dueRangeStart = now()->subDays(14)->startOfDay();
        $dueRangeEnd = now()->addMonths(3)->endOfDay();

        $items = app(CalendarEventService::class)
            ->dueOrderEvents($dueRangeStart, $dueRangeEnd)
            ->filter(function (array $event): bool {
                $status = (string) ($event['status'] ?? '');

                return filled($event['date'] ?? null)
                    && in_array($status, ['new', 'in_progress'], true);
            })
            ->map(function (array $event) use ($today): array {
                $dueDate = Carbon::parse((string) $event['date'])->startOfDay();
                $daysUntilDue = (int) $today->diffInDays($dueDate, false);

                return [
                    'id' => (int) $event['id'],
                    'order_no' => (string) ($event['order_no'] ?? ''),
                    'customer_name' => (string) ($event['customer_name'] ?? __('Walk-in customer')),
                    'title' => (string) ($event['title'] ?? __('Order')),
                    'due_date' => $dueDate->toDateString(),
                    'days_to_due' => $daysUntilDue,
                    'priority' => (string) ($event['priority'] ?? 'normal'),
                    'priority_label' => (string) ($event['priority_label'] ?? __('Normal')),
                    'status_label' => (string) ($event['status_label'] ?? __('New')),
                    'url' => route('orders.show', ['order' => $event['id']]),
                ];
            })
            ->take(5)
            ->values()
            ->all();

        return [
            'items' => $items,
            'can_view_order_dates' => $canViewOrderDates,
            'requires_branch_selection' => $requiresBranchSelection,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.calendar-card');
    }

    protected function normalizeMonth(string $value): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth()->format('Y-m');
        } catch (\Throwable) {
            return now()->format('Y-m');
        }
    }

    protected function monthDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->normalizeMonth($this->month))->startOfMonth();
    }

    protected function bookingDateCounts(Carbon $startDate, Carbon $endDate): array
    {
        return app(BookingEventService::class)->dateCounts($startDate, $endDate);
    }

    protected function compactPreviewWeeks(array $weeks, Carbon $monthDate, string $todayDate): array
    {
        if (count($weeks) <= self::PREVIEW_WEEKS) {
            return $weeks;
        }

        // For the current month, keep today visible in the 4-week preview.
        if ($monthDate->isSameMonth(now())) {
            $todayWeekIndex = 0;

            foreach ($weeks as $index => $week) {
                foreach ($week as $day) {
                    if (($day['date'] ?? null) === $todayDate) {
                        $todayWeekIndex = $index;
                        break 2;
                    }
                }
            }

            $maxStartIndex = count($weeks) - self::PREVIEW_WEEKS;
            $startIndex = max(0, min($todayWeekIndex - 1, $maxStartIndex));

            return array_slice($weeks, $startIndex, self::PREVIEW_WEEKS);
        }

        return array_slice($weeks, 0, self::PREVIEW_WEEKS);
    }
}
