<?php

namespace App\Livewire\Calendar;

use App\Services\Calendar\BookingEventService;
use App\Services\Calendar\CalendarEventService;
use App\Support\BranchContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Calendar')]
class Index extends Component
{
    #[Url(as: 'date')]
    public string $selectedDate = '';

    #[Url]
    public string $view = 'weekly';

    #[Url(as: 'month')]
    public string $month = '';

    public bool $showOrderDue = true;

    public bool $showBookings = true;

    public function mount(): void
    {
        if (! auth()->user()->can('dashboard.view')) {
            abort(403);
        }

        $this->selectedDate = $this->normalizeDate($this->selectedDate);
        $this->view = $this->normalizeView($this->view);
        $this->month = $this->normalizeMonth(
            $this->month !== ''
                ? $this->month
                : Carbon::parse($this->selectedDate)->format('Y-m')
        );
    }

    public function updatedSelectedDate(string $value): void
    {
        $this->selectedDate = $this->normalizeDate($value);
        $this->month = Carbon::parse($this->selectedDate)->format('Y-m');
    }

    public function updatedView(string $value): void
    {
        $this->view = $this->normalizeView($value);
    }

    public function updatedMonth(string $value): void
    {
        $this->month = $this->normalizeMonth($value);

        $selected = Carbon::parse($this->selectedDate);
        $monthDate = $this->monthDate();

        if (! $selected->isSameMonth($monthDate)) {
            $this->selectedDate = $monthDate->toDateString();
        }
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $this->normalizeDate($date);
        $this->month = Carbon::parse($this->selectedDate)->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthDate()->addMonth()->format('Y-m');
    }

    public function jumpToToday(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->month = now()->format('Y-m');
    }

    /**
     * Sync the calendar state coming from the FullCalendar frontend controls.
     */
    public function setCalendarState(string $date, string $view): void
    {
        $normalizedDate = $this->normalizeDate($date);
        $wireView = $this->toWireView($view);
        $normalizedView = $this->normalizeView($wireView);

        $this->selectedDate = $normalizedDate;
        $this->view = $normalizedView;
        $this->month = Carbon::parse($normalizedDate)->format('Y-m');
    }

    /**
     * Used by FullCalendar lazy loading to fetch events by visible range.
     */
    public function fetchEvents(?string $start = null, ?string $end = null): array
    {
        try {
            [$startDate, $endDate] = $this->normalizeRange($start, $end);
            $access = $this->accessState();

            if ($access['requires_branch_selection']) {
                return [];
            }

            $events = collect();

            if ($this->showOrderDue && $access['can_view_order_dates']) {
                $events = $events->merge($this->mapOrderDueEvents($startDate, $endDate));
            }

            if ($this->showBookings) {
                $events = $events->merge($this->mapBookingEvents($startDate, $endDate));
            }

            return $events
                ->sortBy([
                    ['start', 'asc'],
                    ['title', 'asc'],
                ])
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::error('Calendar events fetch failed.', [
                'start' => $start,
                'end' => $end,
                'show_order_due' => $this->showOrderDue,
                'show_bookings' => $this->showBookings,
                'user_id' => auth()->id(),
                'selected_date' => $this->selectedDate,
                'view' => $this->view,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    #[Computed]
    public function accessData(): array
    {
        return $this->accessState();
    }

    #[Computed]
    public function calendarConfig(): array
    {
        return [
            'initial_date' => $this->selectedDate,
            'initial_view' => $this->toFullCalendarView($this->view),
            'wire_view' => $this->view,
            'locale' => app()->getLocale(),
            'timezone' => config('app.timezone', 'UTC'),
        ];
    }

    #[Computed]
    public function selectedDateEvents(): array
    {
        $selectedDate = $this->normalizeDate($this->selectedDate);

        return collect($this->fetchEvents($selectedDate, Carbon::parse($selectedDate)->addDay()->toDateString()))
            ->filter(fn (array $event) => str_starts_with((string) ($event['start'] ?? ''), $selectedDate))
            ->values()
            ->all();
    }

    #[Computed]
    public function sidebarMonthData(): array
    {
        $monthDate = $this->monthDate();
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $gridStart = $monthDate->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $access = $this->accessState();

        $orderDueDates = [];
        if ($this->showOrderDue && $access['can_view_order_dates'] && ! $access['requires_branch_selection']) {
            $orderDueDates = app(CalendarEventService::class)->dueDateCounts($monthStart, $monthEnd);
        }

        $bookingDates = [];
        if ($this->showBookings && ! $access['requires_branch_selection']) {
            $bookingDates = $this->bookingDateCounts($monthStart, $monthEnd);
        }

        $today = now()->toDateString();
        $selectedDate = $this->normalizeDate($this->selectedDate);
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor <= $gridEnd) {
            $week = [];

            for ($index = 0; $index < 7; $index++) {
                $date = $cursor->toDateString();

                $week[] = [
                    'date' => $date,
                    'day' => $cursor->day,
                    'in_month' => $cursor->month === $monthDate->month,
                    'is_today' => $date === $today,
                    'is_selected' => $date === $selectedDate,
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
            'weekdays' => ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
            'weeks' => $weeks,
            'requires_branch_selection' => $access['requires_branch_selection'],
            'can_view_order_dates' => $access['can_view_order_dates'],
        ];
    }

    public function render()
    {
        return view('livewire.calendar.index');
    }

    protected function normalizeDate(string $value): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    protected function normalizeMonth(string $value): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth()->format('Y-m');
        } catch (\Throwable) {
            return now()->format('Y-m');
        }
    }

    protected function normalizeView(string $value): string
    {
        return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'weekly';
    }

    protected function monthDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->normalizeMonth($this->month))->startOfMonth();
    }

    protected function accessState(): array
    {
        $user = auth()->user();

        return [
            'can_view_order_dates' => (bool) $user?->can('orders.view'),
            'requires_branch_selection' => (bool) ($user && $user->isGlobalAdmin() && ! BranchContext::id()),
        ];
    }

    protected function normalizeRange(?string $start, ?string $end): array
    {
        try {
            $startDate = Carbon::parse($start)->startOfDay();
        } catch (\Throwable) {
            $startDate = now()->startOfDay();
        }

        try {
            // FullCalendar sends an exclusive end boundary.
            $endDate = Carbon::parse($end)->subSecond()->endOfDay();
        } catch (\Throwable) {
            $endDate = $startDate->copy()->endOfDay();
        }

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    protected function mapOrderDueEvents(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $orderEvents = app(CalendarEventService::class)->dueOrderEvents($startDate, $endDate);
        $canOpenOrder = auth()->user()?->can('orders.view') ?? false;
        $canResolveOrderRoute = Route::has('orders.show');

        return $orderEvents->map(function (array $event) use ($canOpenOrder, $canResolveOrderRoute): array {
            $palette = $this->priorityPalette((string) ($event['priority'] ?? 'normal'));
            $eventDate = (string) ($event['date'] ?? now()->toDateString());
            [$startAt, $endAt] = $this->orderAestheticTimeRange($eventDate, (string) ($event['id'] ?? '0'));
            $orderNumber = trim((string) ($event['order_no'] ?? ''));
            $customerName = trim((string) ($event['customer_name'] ?? ''));
            $title = $orderNumber !== '' ? $orderNumber : ($customerName !== '' ? $customerName : __('Due order'));
            $subtitleParts = array_filter([
                $customerName !== '' ? $customerName : null,
                ($event['status_label'] ?? null),
                ($event['priority_label'] ?? null),
            ]);

            return [
                'id' => 'order-'.$event['id'],
                'title' => $title,
                'start' => $eventDate.'T'.$startAt,
                'end' => $eventDate.'T'.$endAt,
                'allDay' => false,
                'backgroundColor' => $palette['bg'],
                'borderColor' => $palette['border'],
                'textColor' => $palette['text'],
                'extendedProps' => [
                    'type' => 'order_due',
                    'subtitle' => implode(' | ', $subtitleParts),
                    'url' => ($canOpenOrder && $canResolveOrderRoute) ? route('orders.show', ['order' => $event['id']]) : null,
                ],
            ];
        })->values();
    }

    /**
     * Place due-order events in deterministic pseudo-random time slots for a card-like timeline layout.
     */
    protected function orderAestheticTimeRange(string $eventDate, string $seedValue): array
    {
        $seed = abs((int) crc32($eventDate.'|'.$seedValue));
        $minuteSlots = [0, 15, 30, 45];
        $durationOptions = [30, 45, 60];

        $hour = 10 + ($seed % 8); // 10:00 - 17:45 starts
        $minute = $minuteSlots[(int) (($seed >> 3) % count($minuteSlots))];
        $duration = $durationOptions[(int) (($seed >> 6) % count($durationOptions))];

        $start = Carbon::parse($eventDate)->setTime($hour, $minute, 0);
        $end = $start->copy()->addMinutes($duration);
        $dayEnd = Carbon::parse($eventDate)->setTime(18, 0, 0);

        if ($end->greaterThan($dayEnd)) {
            $end = $dayEnd;
        }

        return [
            $start->format('H:i:s'),
            $end->format('H:i:s'),
        ];
    }

    protected function mapBookingEvents(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $bookingEvents = $this->bookingEvents($startDate, $endDate);

        return $bookingEvents->map(function (array $event): array {
            $date = (string) ($event['date'] ?? now()->toDateString());
            $startTime = (string) ($event['start'] ?? '10:00');
            $endTime = (string) ($event['end'] ?? '10:45');

            return [
                'id' => 'booking-'.$event['id'],
                'title' => $event['title'] ?? __('Fitting booking'),
                'start' => $date.'T'.$startTime.':00',
                'end' => $date.'T'.$endTime.':00',
                'allDay' => false,
                'backgroundColor' => '#decdf9',
                'borderColor' => '#c7b0f0',
                'textColor' => '#3d2a57',
                'extendedProps' => [
                    'type' => 'booking',
                    'subtitle' => $event['subtitle'] ?? __('Reserved time slot'),
                    'url' => null,
                ],
            ];
        })->values();
    }

    protected function toFullCalendarView(string $wireView): string
    {
        return match ($wireView) {
            'daily' => 'timeGridDay',
            'monthly' => 'dayGridMonth',
            default => 'timeGridWeek',
        };
    }

    protected function toWireView(string $view): string
    {
        return match ($view) {
            'timeGridDay' => 'daily',
            'dayGridMonth' => 'monthly',
            'timeGridWeek' => 'weekly',
            default => $view,
        };
    }

    protected function priorityPalette(string $priority): array
    {
        return match ($priority) {
            'low' => [
                'bg' => '#fadfc0',
                'text' => '#4a2f1b',
                'border' => '#f0c998',
            ],
            'high' => [
                'bg' => '#d4d3fd',
                'text' => '#2f2e5c',
                'border' => '#bbbaf6',
            ],
            'urgent' => [
                'bg' => '#f3c5c5',
                'text' => '#612b2b',
                'border' => '#e79f9f',
            ],
            default => [
                'bg' => '#bef0db',
                'text' => '#1f4c3c',
                'border' => '#9fe1c7',
            ],
        };
    }

    protected function bookingDateCounts(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return app(BookingEventService::class)->dateCounts($startDate, $endDate);
    }

    protected function bookingEvents(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return app(BookingEventService::class)->events($startDate, $endDate);
    }
}
