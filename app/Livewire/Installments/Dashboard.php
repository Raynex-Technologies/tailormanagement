<?php

namespace App\Livewire\Installments;

use App\Enums\InstallmentPlanStatus;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Package;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Installments Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        if (! auth()->user()->can('installments.view')) {
            abort(403);
        }
    }

    public function render()
    {
        $plans = InstallmentPlan::query()
            ->withSum('payments', 'amount')
            ->withCount([
                'schedules as overdue_schedules_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'partial'])
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->get();

        $upcomingSchedules = InstallmentSchedule::query()
            ->with(['installmentPlan.customer'])
            ->whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $overdueSchedules = InstallmentSchedule::query()
            ->with(['installmentPlan.customer'])
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $topPackages = Package::query()
            ->withCount('installmentPlans')
            ->orderByDesc('installment_plans_count')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $totalFinanced = (float) $plans->sum('package_price');
        $totalCollected = (float) $plans->sum(fn (InstallmentPlan $plan) => (float) ($plan->payments_sum_amount ?? 0));

        return view('livewire.installments.dashboard', [
            'stats' => [
                'active_plans' => $plans->filter(fn (InstallmentPlan $plan) => $plan->status === InstallmentPlanStatus::Active)->count(),
                'completed_plans' => $plans->filter(fn (InstallmentPlan $plan) => $plan->status === InstallmentPlanStatus::Completed)->count(),
                'total_financed' => $totalFinanced,
                'outstanding_balance' => max(0, $totalFinanced - $totalCollected),
                'overdue_plans' => $plans->filter(fn (InstallmentPlan $plan) => $plan->overdue_schedules_count > 0)->count(),
                'total_collected' => $totalCollected,
            ],
            'frequencyBreakdown' => $plans
                ->groupBy(fn (InstallmentPlan $plan) => $plan->payment_frequency?->label() ?? 'Unknown')
                ->map(fn ($group) => $group->count())
                ->all(),
            'upcomingSchedules' => $upcomingSchedules,
            'overdueSchedules' => $overdueSchedules,
            'topPackages' => $topPackages,
        ]);
    }
}
