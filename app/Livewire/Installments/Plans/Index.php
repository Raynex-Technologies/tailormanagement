<?php

namespace App\Livewire\Installments\Plans;

use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Models\InstallmentPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Installment Plans')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $frequencyFilter = '';

    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', InstallmentPlan::class);
    }

    public function render()
    {
        $query = InstallmentPlan::query()
            ->with(['customer'])
            ->withSum('payments', 'amount')
            ->withCount([
                'schedules as overdue_schedules_count' => fn ($scheduleQuery) => $scheduleQuery
                    ->whereIn('status', ['pending', 'partial'])
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->when($this->search !== '', function ($builder) {
                $term = '%' . $this->search . '%';

                $builder->where(function ($query) use ($term) {
                    $query->where('plan_no', 'like', $term)
                        ->orWhere('package_name', 'like', $term)
                        ->orWhereHas('customer', function ($customerQuery) use ($term) {
                            $customerQuery->where('name', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            })
            ->when($this->statusFilter !== '', fn ($builder) => $builder->where('status', $this->statusFilter))
            ->when($this->frequencyFilter !== '', fn ($builder) => $builder->where('payment_frequency', $this->frequencyFilter))
            ->orderByDesc('created_at');

        $plans = $query->paginate($this->perPage);

        $summaryPlans = InstallmentPlan::query()->withSum('payments', 'amount')->get();
        $financed = (float) $summaryPlans->sum('package_price');
        $collected = (float) $summaryPlans->sum(fn (InstallmentPlan $plan) => (float) ($plan->payments_sum_amount ?? 0));

        return view('livewire.installments.plans.index', [
            'plans' => $plans,
            'statuses' => InstallmentPlanStatus::cases(),
            'frequencies' => InstallmentFrequency::cases(),
            'stats' => [
                'active' => $summaryPlans->filter(fn (InstallmentPlan $plan) => $plan->status === InstallmentPlanStatus::Active)->count(),
                'completed' => $summaryPlans->filter(fn (InstallmentPlan $plan) => $plan->status === InstallmentPlanStatus::Completed)->count(),
                'financed' => $financed,
                'outstanding' => max(0, $financed - $collected),
            ],
        ]);
    }
}
