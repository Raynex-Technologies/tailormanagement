<?php

namespace App\Livewire\Orders\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Panel extends Component
{
    use AuthorizesRequests;

    public Order $order;

    // Authorization flags
    public bool $canViewPayments = false;
    public bool $canRecordPayments = false;

    // Payment form fields
    public bool $showPaymentModal = false;
    public ?float $amount = null;
    public ?string $method = null;
    public ?string $reference = null;
    public ?string $paidAt = null;
    public ?string $note = null;

    // Payment summary (cached)
    public float $totalAmount = 0;
    public float $paidAmount = 0;
    public float $balanceAmount = 0;

    protected function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($this->balanceAmount + 0.01)],
            'method' => ['required', 'string', 'in:' . implode(',', PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'paidAt' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'amount.max' => 'Amount cannot exceed remaining balance of ' . money_tzs($this->balanceAmount),
        ];
    }

    public function mount(Order $order): void
    {
        $user = auth()->user();

        // Check permissions - this panel should only be rendered if user can view payments
        // but we double-check here for security
        $this->canViewPayments = $user->can('viewPayments', $order);
        $this->canRecordPayments = $user->can('recordPayments', $order);

        if (! $this->canViewPayments) {
            abort(403, 'You do not have permission to view payments.');
        }

        $this->order = $order->load('payments.receiver');
        $this->refreshSummary();
        $this->method = PaymentMethod::Cash->value;
    }

    public function refreshSummary(): void
    {
        $this->totalAmount = (float) $this->order->total;
        $this->paidAmount = $this->order->paid_amount;
        $this->balanceAmount = $this->order->balance_amount;
    }

    public function openPaymentModal(): void
    {
        // Use policy-based authorization
        $this->authorize('recordPayments', $this->order);

        $this->resetPaymentForm();
        $this->amount = $this->balanceAmount > 0 ? $this->balanceAmount : null;
        $this->paidAt = now()->format('Y-m-d\TH:i');
        $this->showPaymentModal = true;
    }

    public function recordPayment(OrderPaymentService $paymentService): void
    {
        // Use policy-based authorization
        $this->authorize('recordPayments', $this->order);

        $this->validate();

        try {
            $paymentService->recordPayment($this->order, [
                'amount' => $this->amount,
                'method' => $this->method,
                'reference' => $this->reference,
                'paid_at' => $this->paidAt ? \Carbon\Carbon::parse($this->paidAt) : now(),
                'note' => $this->note,
            ], auth()->user());

            // Refresh order and summary
            $this->order->refresh();
            $this->order->load('payments.receiver');
            $this->refreshSummary();

            $this->showPaymentModal = false;
            $this->resetPaymentForm();

            // Dispatch browser event for parent component refresh
            $this->dispatch('payment-recorded');

            session()->flash('success', 'Payment recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    protected function resetPaymentForm(): void
    {
        $this->amount = null;
        $this->method = PaymentMethod::Cash->value;
        $this->reference = null;
        $this->paidAt = null;
        $this->note = null;
        $this->resetValidation();
    }

    public function getPaymentMethodsProperty(): array
    {
        return PaymentMethod::cases();
    }

    public function render()
    {
        return view('livewire.orders.payments.panel', [
            'payments' => $this->order->payments()->with('receiver')->latest('paid_at')->get(),
            'paymentMethods' => $this->paymentMethods,
            'canRecordPayments' => $this->canRecordPayments,
        ]);
    }
}
