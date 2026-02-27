<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrdersReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?string $status;

    protected ?int $tailorId;

    protected ?string $paymentStatus;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->status = $filters['status'] ?? null;
        $this->tailorId = $filters['tailor_id'] ?? null;
        $this->paymentStatus = $filters['payment_status'] ?? null;
        $this->search = $filters['search'] ?? null;
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $baseQuery = $this->baseQuery();

        $stats = (clone $baseQuery)->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(orders.total), 0) as total_value
        ')->first();

        $completedCount = (clone $baseQuery)
            ->whereIn('orders.status', [OrderStatus::Completed->value, OrderStatus::Delivered->value])
            ->count();

        // Calculate average turnaround (only for completed orders with completed_at)
        $avgTurnaround = (clone $baseQuery)
            ->whereNotNull('orders.completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, COALESCE(orders.order_date, DATE(orders.created_at)), orders.completed_at)) as avg_hours')
            ->first();

        $avgHours = $avgTurnaround?->avg_hours ?? 0;
        $avgDays = $avgHours > 0 ? round($avgHours / 24, 1) : 0;

        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'completed_count' => $completedCount,
            'total_value' => (float) ($stats->total_value ?? 0),
            'avg_turnaround_days' => $avgDays,
        ];
    }

    /**
     * Get paginated rows for the report table.
     */
    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->select([
                'orders.*',
                'customers.name as customer_name',
            ])
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->with(['payments', 'assignedTailor', 'lines.assignedTailor'])
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.created_at')
            ->paginate($perPage);
    }

    /**
     * Export data for CSV.
     */
    public function export(): array
    {
        $rows = $this->baseQuery()
            ->select([
                'orders.*',
                'customers.name as customer_name',
            ])
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->with(['payments', 'assignedTailor', 'lines.assignedTailor'])
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.created_at')
            ->get();

        $data = [];
        $data[] = ['Order No', 'Customer', 'Status', 'Order Date', 'Due Date', 'Tailor(s)', 'Total', 'Paid', 'Balance'];

        foreach ($rows as $row) {
            $paidAmount = $row->payments->sum('amount');
            $tailorNames = collect();
            if ($row->assignedTailor?->name) {
                $tailorNames->push($row->assignedTailor->name);
            }
            $lineTailorNames = $row->lines->pluck('assignedTailor.name')->filter()->unique()->values();
            foreach ($lineTailorNames as $lineTailorName) {
                if (! $tailorNames->contains($lineTailorName)) {
                    $tailorNames->push($lineTailorName);
                }
            }

            $data[] = [
                $row->order_no,
                $row->customer_name,
                $row->status->label(),
                $row->order_date?->format('Y-m-d') ?? Carbon::parse($row->created_at)->format('Y-m-d'),
                $row->due_date?->format('Y-m-d') ?? '',
                $tailorNames->isNotEmpty() ? $tailorNames->implode(', ') : 'Unassigned',
                number_format($row->total, 2),
                number_format($paidAmount, 2),
                number_format(max(0, $row->total - $paidAmount), 2),
            ];
        }

        return $data;
    }

    /**
     * Build the base query with filters.
     */
    protected function baseQuery()
    {
        $query = Order::query();

        // Date range filter
        if ($this->dateFrom) {
            $query->where(function ($q) {
                $q->whereDate('orders.order_date', '>=', $this->dateFrom)
                    ->orWhere(function ($legacyQuery) {
                        // Keep legacy rows (without order_date) filterable.
                        $legacyQuery->whereNull('orders.order_date')
                            ->whereDate('orders.created_at', '>=', $this->dateFrom);
                    });
            });
        }

        if ($this->dateTo) {
            $query->where(function ($q) {
                $q->whereDate('orders.order_date', '<=', $this->dateTo)
                    ->orWhere(function ($legacyQuery) {
                        // Keep legacy rows (without order_date) filterable.
                        $legacyQuery->whereNull('orders.order_date')
                            ->whereDate('orders.created_at', '<=', $this->dateTo);
                    });
            });
        }

        // Status filter
        if ($this->status) {
            $query->where('orders.status', $this->status);
        }

        // Tailor filter
        if ($this->tailorId) {
            $query->assignedTo($this->tailorId);
        }

        // Payment status filter
        if ($this->paymentStatus) {
            $query->where('orders.payment_status', $this->paymentStatus);
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('orders.order_no', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', function ($cq) {
                        $cq->where('name', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%");
                    });
            });
        }

        return $query;
    }
}
