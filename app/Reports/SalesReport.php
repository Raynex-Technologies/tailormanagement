<?php

namespace App\Reports;

use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SalesReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?int $paymentMethodId;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->paymentMethodId = isset($filters['method']) ? (int) $filters['method'] : null;
        $this->search = $filters['search'] ?? null;
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $query = $this->baseQuery();

        $stats = $query->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(order_payments.amount), 0) as total_received,
            COALESCE(AVG(order_payments.amount), 0) as avg_payment
        ')->first();

        // Get top payment method
        $topMethod = $this->baseQuery()
            ->leftJoin('payment_methods', 'order_payments.payment_method_id', '=', 'payment_methods.id')
            ->select('payment_methods.name', DB::raw('SUM(order_payments.amount) as total'))
            ->groupBy('payment_methods.name')
            ->orderByDesc('total')
            ->first();

        return [
            'total_received' => (float) ($stats->total_received ?? 0),
            'total_count' => (int) ($stats->total_count ?? 0),
            'avg_payment' => (float) ($stats->avg_payment ?? 0),
            'top_method' => $topMethod?->name ?? 'N/A',
            'top_method_amount' => (float) ($topMethod?->total ?? 0),
        ];
    }

    /**
     * Get paginated rows for the report table.
     */
    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->select([
                'order_payments.*',
                'orders.order_no',
                'orders.order_date',
                'orders.created_at as order_created_at',
                'customers.name as customer_name',
                'users.name as received_by_name',
                'payment_methods.name as payment_method_name',
            ])
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'order_payments.received_by', '=', 'users.id')
            ->leftJoin('payment_methods', 'order_payments.payment_method_id', '=', 'payment_methods.id')
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.created_at')
            ->orderByDesc('order_payments.paid_at')
            ->paginate($perPage);
    }

    /**
     * Export data for CSV.
     */
    public function export(): array
    {
        $rows = $this->baseQuery()
            ->select([
                'orders.order_no',
                'orders.order_date',
                'orders.created_at as order_created_at',
                'customers.name as customer_name',
                'order_payments.amount',
                'payment_methods.name as payment_method_name',
                'order_payments.reference',
                'users.name as received_by_name',
            ])
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'order_payments.received_by', '=', 'users.id')
            ->leftJoin('payment_methods', 'order_payments.payment_method_id', '=', 'payment_methods.id')
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.created_at')
            ->orderByDesc('order_payments.paid_at')
            ->get();

        $data = [];
        $data[] = ['Order Date', 'Order No', 'Customer', 'Amount', 'Method', 'Reference', 'Received By'];

        foreach ($rows as $row) {
            $orderDate = $row->order_date
                ? Carbon::parse($row->order_date)
                : Carbon::parse($row->order_created_at);

            $data[] = [
                $orderDate->format('Y-m-d'),
                $row->order_no,
                $row->customer_name,
                number_format($row->amount, 2),
                $row->payment_method_name ?? 'Default',
                $row->reference ?? '',
                $row->received_by_name ?? '',
            ];
        }

        return $data;
    }

    /**
     * Build the base query with filters.
     */
    protected function baseQuery()
    {
        $query = OrderPayment::query();

        // Date range filter on order date (fallback to order created_at for legacy rows).
        if ($this->dateFrom) {
            $query->whereHas('order', function ($orderQuery) {
                $orderQuery->whereDate('order_date', '>=', $this->dateFrom)
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->whereNull('order_date')
                            ->whereDate('created_at', '>=', $this->dateFrom);
                    });
            });
        }

        if ($this->dateTo) {
            $query->whereHas('order', function ($orderQuery) {
                $orderQuery->whereDate('order_date', '<=', $this->dateTo)
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->whereNull('order_date')
                            ->whereDate('created_at', '<=', $this->dateTo);
                    });
            });
        }

        // Method filter
        if ($this->paymentMethodId) {
            $query->where('order_payments.payment_method_id', $this->paymentMethodId);
        }

        // Search filter (order_no or customer name)
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('order', function ($oq) {
                    $oq->where('order_no', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', "%{$this->search}%");
                        });
                });
            });
        }

        return $query;
    }
}
