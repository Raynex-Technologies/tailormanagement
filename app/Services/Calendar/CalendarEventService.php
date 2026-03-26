<?php

namespace App\Services\Calendar;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CalendarEventService
{
    /**
     * Return due-order counts keyed by due date (Y-m-d).
     */
    public function dueDateCounts(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return Order::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereIn('status', $this->openStatusValues())
            ->selectRaw('DATE(due_date) as due_on, COUNT(*) as total')
            ->groupBy('due_on')
            ->pluck('total', 'due_on')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Return due-order events for the selected date range.
     */
    public function dueOrderEvents(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return Order::query()
            ->with('customer:id,name')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereIn('status', $this->openStatusValues())
            ->orderBy('due_date')
            ->orderByRaw($this->prioritySortSql())
            ->orderBy('created_at')
            ->get([
                'id',
                'order_no',
                'customer_id',
                'due_date',
                'priority',
                'status',
                'created_at',
            ])
            ->map(function (Order $order): array {
                $priority = $order->priority?->value ?? 'normal';
                $status = $order->status?->value ?? OrderStatus::New->value;

                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'customer_name' => $order->customer?->name ?? 'Walk-in customer',
                    'title' => trim(($order->order_no ?? '') . ' - ' . ($order->customer?->name ?? 'Walk-in customer')),
                    'date' => $order->due_date?->toDateString(),
                    'priority' => $priority,
                    'priority_label' => $order->priority?->label() ?? __('Normal'),
                    'status' => $status,
                    'status_label' => $order->status?->label() ?? __('New'),
                ];
            })
            ->values();
    }

    protected function openStatusValues(): array
    {
        return [
            OrderStatus::New->value,
            OrderStatus::InProgress->value,
            OrderStatus::Ready->value,
        ];
    }

    protected function prioritySortSql(): string
    {
        return "CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            ELSE 4
        END";
    }
}
