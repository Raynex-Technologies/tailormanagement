<?php

namespace App\Livewire\Installments\Plans;

use App\Models\InstallmentPlan;
use App\Models\PaymentMethod;
use App\Services\Installments\InstallmentPlanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    public InstallmentPlan $plan;

    public bool $showPaymentModal = false;

    public ?float $paymentAmount = null;

    public ?int $paymentMethodId = null;

    public string $paymentReference = '';

    public string $paymentPaidAt = '';

    public string $paymentNote = '';

    public function mount(InstallmentPlan $plan): void
    {
        $this->authorize('view', $plan);
        $this->plan = $plan->load(['customer', 'package', 'creator', 'schedules', 'payments.paymentMethod', 'payments.receiver']);
        $this->paymentPaidAt = now()->format('Y-m-d\TH:i');
    }

    protected function paymentRules(): array
    {
        return [
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethodId' => ['nullable', 'exists:payment_methods,id'],
            'paymentReference' => ['nullable', 'string', 'max:100'],
            'paymentPaidAt' => ['required', 'date'],
            'paymentNote' => ['nullable', 'string', 'max:1000'],
        ];
    }

    #[Computed]
    public function paymentMethods()
    {
        return PaymentMethod::query()->orderBy('name')->get(['id', 'name']);
    }

    public function openPaymentModal(): void
    {
        $this->authorize('recordPayment', $this->plan);

        $this->paymentAmount = round($this->plan->remaining_balance, 2);
        $this->paymentMethodId = null;
        $this->paymentReference = '';
        $this->paymentPaidAt = now()->format('Y-m-d\TH:i');
        $this->paymentNote = '';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function savePayment(InstallmentPlanService $service): void
    {
        $this->authorize('recordPayment', $this->plan);

        $validated = $this->validate($this->paymentRules());

        $service->recordPayment($this->plan->fresh(['schedules', 'payments']), [
            'amount' => $validated['paymentAmount'],
            'payment_method_id' => $validated['paymentMethodId'] ?? null,
            'reference' => $validated['paymentReference'] ?: null,
            'paid_at' => $validated['paymentPaidAt'],
            'note' => $validated['paymentNote'] ?: null,
        ], auth()->user());

        $this->plan = $this->plan->fresh(['customer', 'package', 'creator', 'schedules', 'payments.paymentMethod', 'payments.receiver']);
        $this->showPaymentModal = false;
        session()->flash('success', 'Installment payment recorded successfully.');
    }

    public function render()
    {
        return view('livewire.installments.plans.show');
    }
}
