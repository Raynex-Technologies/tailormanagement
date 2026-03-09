<?php

namespace App\Reports;

use App\Enums\InstallmentPlanStatus;
use App\Models\InstallmentPlan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InstallmentsReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?string $status;

    protected ?string $frequency;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->status = $filters['status'] ?? null;
        $this->frequency = $filters['frequency'] ?? null;
        $this->search = $filters['search'] ?? null;
    }

    public function summary(): array
    {
        $plans = $this->baseQuery()
            ->withSum('payments', 'amount')
            ->withCount([
                'schedules as overdue_schedules_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'partial'])
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->get();

        $totalFinanced = (float) $plans->sum('package_price');
        $totalCollected = (float) $plans->sum(fn (InstallmentPlan $plan) => (float) ($plan->payments_sum_amount ?? 0));
        $overdueCount = (int) $plans->filter(fn (InstallmentPlan $plan) => $plan->overdue_schedules_count > 0)->count();

        return [
            'total_financed' => $totalFinanced,
            'total_collected' => $totalCollected,
            'outstanding_balance' => max(0, $totalFinanced - $totalCollected),
            'active_count' => (int) $plans->filter(fn (InstallmentPlan $plan) => $plan->status === InstallmentPlanStatus::Active)->count(),
            'overdue_count' => $overdueCount,
            'average_ticket' => $plans->count() > 0 ? round($totalFinanced / $plans->count(), 2) : 0,
        ];
    }

    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with(['customer', 'creator'])
            ->withSum('payments', 'amount')
            ->withCount([
                'schedules as overdue_schedules_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'partial'])
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function export(): array
    {
        $rows = $this->baseQuery()
            ->with(['customer'])
            ->withSum('payments', 'amount')
            ->withCount([
                'schedules as overdue_schedules_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'partial'])
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->get();

        $data = [];
        $data[] = ['Start Date', 'Plan No', 'Customer', 'Package', 'Frequency', 'Status', 'Package Price', 'Collected', 'Remaining', 'Overdue'];

        foreach ($rows as $row) {
            $collected = (float) ($row->payments_sum_amount ?? 0);

            $data[] = [
                optional($row->start_date)->format('Y-m-d'),
                $row->plan_no,
                $row->customer?->name,
                $row->package_name,
                $row->payment_frequency?->label() ?? $row->payment_frequency,
                $row->status?->label() ?? $row->status,
                number_format((float) $row->package_price, 2, '.', ''),
                number_format($collected, 2, '.', ''),
                number_format(max(0, (float) $row->package_price - $collected), 2, '.', ''),
                $row->overdue_schedules_count > 0 ? 'Yes' : 'No',
            ];
        }

        return $data;
    }

    protected function baseQuery()
    {
        return InstallmentPlan::query()
            ->when($this->dateFrom, fn ($query) => $query->whereDate('start_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('start_date', '<=', $this->dateTo))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->frequency, fn ($query) => $query->where('payment_frequency', $this->frequency))
            ->when($this->search, function ($query) {
                $term = '%' . $this->search . '%';

                $query->where(function ($innerQuery) use ($term) {
                    $innerQuery->where('plan_no', 'like', $term)
                        ->orWhere('package_name', 'like', $term)
                        ->orWhereHas('customer', function ($customerQuery) use ($term) {
                            $customerQuery->where('name', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            });
    }
}
