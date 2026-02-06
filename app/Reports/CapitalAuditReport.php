<?php

namespace App\Reports;

use App\Enums\CapitalAllocationStatus;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CapitalAuditReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?int $accountantId;

    protected ?string $status;

    protected ?int $allocationId;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->accountantId = $filters['accountant_id'] ?? null;
        $this->status = $filters['status'] ?? null;
        $this->allocationId = $filters['allocation_id'] ?? null;
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $allocQuery = $this->baseAllocationQuery();

        $stats = (clone $allocQuery)->selectRaw('
            COALESCE(SUM(capital_allocations.initial_amount), 0) as total_allocated,
            COALESCE(SUM(capital_allocations.spent_amount), 0) as total_spent
        ')->first();

        $openCount = (clone $allocQuery)
            ->where('capital_allocations.status', CapitalAllocationStatus::Open->value)
            ->count();

        // Transaction stats for the date range
        $transactionStats = $this->baseTransactionQuery()
            ->selectRaw('
                COALESCE(SUM(CASE WHEN capital_transactions.type = "debit" THEN capital_transactions.amount ELSE 0 END), 0) as total_debits,
                COUNT(*) as transaction_count
            ')->first();

        $totalAllocated = (float) ($stats->total_allocated ?? 0);
        $totalSpent = (float) ($stats->total_spent ?? 0);

        return [
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'total_remaining' => $totalAllocated - $totalSpent,
            'open_allocations_count' => $openCount,
            'transaction_count' => (int) ($transactionStats->transaction_count ?? 0),
            'period_debits' => (float) ($transactionStats->total_debits ?? 0),
        ];
    }

    /**
     * Get allocations table (paginated).
     */
    public function allocations(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseAllocationQuery()
            ->select([
                'capital_allocations.*',
                'users.name as accountant_name',
            ])
            ->leftJoin('users', 'capital_allocations.accountant_id', '=', 'users.id')
            ->orderByDesc('capital_allocations.starts_on')
            ->paginate($perPage);
    }

    /**
     * Get transactions table (paginated).
     */
    public function transactions(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseTransactionQuery()
            ->select([
                'capital_transactions.*',
                'capital_allocations.allocation_no',
                'users.name as created_by_name',
            ])
            ->join('capital_allocations', 'capital_transactions.capital_allocation_id', '=', 'capital_allocations.id')
            ->leftJoin('users', 'capital_transactions.created_by', '=', 'users.id')
            ->orderByDesc('capital_transactions.created_at')
            ->paginate($perPage);
    }

    /**
     * Export allocations for CSV.
     */
    public function exportAllocations(): array
    {
        $rows = $this->baseAllocationQuery()
            ->select([
                'capital_allocations.*',
                'users.name as accountant_name',
            ])
            ->leftJoin('users', 'capital_allocations.accountant_id', '=', 'users.id')
            ->orderByDesc('capital_allocations.starts_on')
            ->get();

        $data = [];
        $data[] = ['Allocation No', 'Accountant', 'Period Start', 'Period End', 'Initial Amount', 'Spent', 'Remaining', 'Status'];

        foreach ($rows as $row) {
            $remaining = $row->initial_amount - $row->spent_amount;
            $data[] = [
                $row->allocation_no,
                $row->accountant_name ?? '',
                Carbon::parse($row->starts_on)->format('Y-m-d'),
                Carbon::parse($row->ends_on)->format('Y-m-d'),
                number_format($row->initial_amount, 2),
                number_format($row->spent_amount, 2),
                number_format($remaining, 2),
                ucfirst($row->status),
            ];
        }

        return $data;
    }

    /**
     * Export transactions for CSV.
     */
    public function exportTransactions(): array
    {
        $rows = $this->baseTransactionQuery()
            ->select([
                'capital_transactions.*',
                'capital_allocations.allocation_no',
                'users.name as created_by_name',
            ])
            ->join('capital_allocations', 'capital_transactions.capital_allocation_id', '=', 'capital_allocations.id')
            ->leftJoin('users', 'capital_transactions.created_by', '=', 'users.id')
            ->orderByDesc('capital_transactions.created_at')
            ->get();

        $data = [];
        $data[] = ['Date', 'Allocation No', 'Type', 'Amount', 'Reference Type', 'Description', 'Created By'];

        foreach ($rows as $row) {
            $refType = '';
            if ($row->reference_type) {
                $refType = class_basename($row->reference_type);
            }

            $data[] = [
                Carbon::parse($row->created_at)->format('Y-m-d H:i'),
                $row->allocation_no,
                ucfirst($row->type),
                number_format($row->amount, 2),
                $refType,
                $row->description ?? '',
                $row->created_by_name ?? '',
            ];
        }

        return $data;
    }

    /**
     * Build base allocation query with filters.
     */
    protected function baseAllocationQuery()
    {
        $query = CapitalAllocation::query();

        // Filter by status
        if ($this->status) {
            $query->where('capital_allocations.status', $this->status);
        }

        // Filter by accountant
        if ($this->accountantId) {
            $query->where('capital_allocations.accountant_id', $this->accountantId);
        }

        // Filter by specific allocation
        if ($this->allocationId) {
            $query->where('capital_allocations.id', $this->allocationId);
        }

        return $query;
    }

    /**
     * Build base transaction query with filters.
     */
    protected function baseTransactionQuery()
    {
        $query = CapitalTransaction::query();

        // Date range filter (on transaction created_at)
        if ($this->dateFrom) {
            $query->whereDate('capital_transactions.created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('capital_transactions.created_at', '<=', $this->dateTo);
        }

        // Filter by allocation
        if ($this->allocationId) {
            $query->where('capital_transactions.capital_allocation_id', $this->allocationId);
        }

        return $query;
    }
}
