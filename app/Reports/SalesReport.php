<?php

namespace App\Reports;

use App\Models\OrderPayment;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
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

    public function summary(): array
    {
        $rows = $this->combinedRowsQuery();

        $stats = (clone $rows)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_received, COALESCE(AVG(amount), 0) as avg_payment')
            ->first();

        $topMethod = (clone $rows)
            ->select('method_name', DB::raw('SUM(amount) as total'))
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->first();

        return [
            'total_received' => (float) ($stats->total_received ?? 0),
            'total_count' => (int) ($stats->total_count ?? 0),
            'avg_payment' => (float) ($stats->avg_payment ?? 0),
            'top_method' => $topMethod?->method_name ?? 'N/A',
            'top_method_amount' => (float) ($topMethod?->total ?? 0),
        ];
    }

    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->combinedRowsQuery()
            ->orderByDesc('sale_date')
            ->orderByDesc('occurred_at')
            ->orderByDesc('source_id')
            ->paginate($perPage);
    }

    public function export(): array
    {
        $rows = $this->combinedRowsQuery()
            ->orderByDesc('sale_date')
            ->orderByDesc('occurred_at')
            ->orderByDesc('source_id')
            ->get();

        $data = [];
        $data[] = ['Type', 'Date', 'Document No', 'Customer', 'Amount', 'Method', 'Reference', 'Received By'];

        foreach ($rows as $row) {
            $data[] = [
                $row->source_label,
                Carbon::parse($row->sale_date)->format('Y-m-d'),
                $row->document_no,
                $row->customer_name,
                number_format((float) $row->amount, 2),
                $row->method_name ?? 'Default',
                $row->reference ?? '',
                $row->cashier_name ?? '',
            ];
        }

        return $data;
    }

    protected function combinedRowsQuery(): Builder
    {
        $paymentRows = $this->paymentRowsQuery();
        $posRows = $this->posRowsQuery();

        return DB::query()->fromSub($paymentRows->unionAll($posRows), 'sales_rows');
    }

    protected function paymentRowsQuery()
    {
        $query = OrderPayment::query()
            ->selectRaw("'order_payment' as source_type")
            ->selectRaw("'Order Payment' as source_label")
            ->selectRaw('order_payments.id as source_id')
            ->selectRaw('order_payments.order_id as order_id')
            ->selectRaw('NULL as pos_sale_id')
            ->selectRaw('COALESCE(orders.order_date, DATE(orders.created_at)) as sale_date')
            ->selectRaw('order_payments.paid_at as occurred_at')
            ->selectRaw('orders.order_no as document_no')
            ->selectRaw('customers.name as customer_name')
            ->selectRaw('order_payments.amount as amount')
            ->selectRaw("COALESCE(payment_methods.name, 'Default') as method_name")
            ->selectRaw('order_payments.reference as reference')
            ->selectRaw('users.name as cashier_name')
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'order_payments.received_by', '=', 'users.id')
            ->leftJoin('payment_methods', 'order_payments.payment_method_id', '=', 'payment_methods.id');

        if ($this->dateFrom) {
            $query->whereDate(DB::raw('COALESCE(orders.order_date, orders.created_at)'), '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate(DB::raw('COALESCE(orders.order_date, orders.created_at)'), '<=', $this->dateTo);
        }

        if ($this->paymentMethodId) {
            $query->where('order_payments.payment_method_id', $this->paymentMethodId);
        }

        if ($this->search) {
            $search = trim($this->search);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('orders.order_no', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('order_payments.reference', 'like', "%{$search}%");
            });
        }

        return $query->toBase();
    }

    protected function posRowsQuery()
    {
        $query = PosSale::query()
            ->selectRaw("'pos_sale' as source_type")
            ->selectRaw("'POS Sale' as source_label")
            ->selectRaw('pos_sales.id as source_id')
            ->selectRaw('NULL as order_id')
            ->selectRaw('pos_sales.id as pos_sale_id')
            ->selectRaw('DATE(pos_sales.sold_at) as sale_date')
            ->selectRaw('pos_sales.sold_at as occurred_at')
            ->selectRaw('pos_sales.sale_number as document_no')
            ->selectRaw("COALESCE(customers.name, 'Walk-in Customer') as customer_name")
            ->selectRaw('pos_sales.total_amount as amount')
            ->selectRaw("REPLACE(pos_sales.payment_method, '_', ' ') as method_name")
            ->selectRaw('pos_sales.payment_reference as reference')
            ->selectRaw('users.name as cashier_name')
            ->leftJoin('customers', 'pos_sales.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'pos_sales.user_id', '=', 'users.id');

        if ($this->dateFrom) {
            $query->whereDate('pos_sales.sold_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('pos_sales.sold_at', '<=', $this->dateTo);
        }

        if ($this->paymentMethodId) {
            $query->whereRaw('1 = 0');
        }

        if ($this->search) {
            $search = trim($this->search);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('pos_sales.sale_number', 'like', "%{$search}%")
                    ->orWhere('pos_sales.payment_reference', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%");
            });
        }

        return $query->toBase();
    }
}
