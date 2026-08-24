<?php

namespace App\Livewire\Orders\Payments;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderPaymentService;
use App\Support\Livewire\NormalizesMoneyInputs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Panel extends Component
{
    use AuthorizesRequests;
    use NormalizesMoneyInputs;

    public Order $order;

    // Authorization flags
    public bool $canViewPayments = false;

    public bool $canRecordPayments = false;

    // Payment form fields
    public bool $showPaymentModal = false;

    public string|float|null $amount = null;

    public ?int $payment_method_id = null;

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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.($this->balanceAmount + 0.01)],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paidAt' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'amount.max' => 'Amount cannot exceed remaining balance of '.money_tzs($this->balanceAmount),
        ];
    }

    public function mount(Order $order): void
    {
        $user = auth()->user();
        $orderIsCancelled = $order->status === OrderStatus::Cancelled;

        // Users who can view payments can see history; users who can record
        // payments can access the card and modal without history access.
        $this->canViewPayments = $user->can('viewPayments', $order);
        $this->canRecordPayments = ! $orderIsCancelled && $user->can('recordPayments', $order);

        if (! $this->canViewPayments && ! $this->canRecordPayments) {
            abort(403, 'You do not have permission to access payments for this order.');
        }

        $this->order = $this->canViewPayments
            ? $order->load('payments.receiver', 'payments.paymentMethod')
            : $order;
        $this->refreshSummary();
        $this->payment_method_id = $this->getDefaultPaymentMethodId();
    }

    public function refreshSummary(): void
    {
        $this->totalAmount = (float) $this->order->total;
        $this->paidAmount = $this->order->paid_amount;
        $this->balanceAmount = $this->order->balance_amount;
    }

    public function openPaymentModal(): void
    {
        if ($this->order->status === OrderStatus::Cancelled) {
            session()->flash('error', 'Cannot record payment for a cancelled order.');

            return;
        }

        // Use policy-based authorization
        $this->authorize('recordPayments', $this->order);

        if ($this->paymentMethods->isEmpty()) {
            session()->flash('error', 'No payment methods configured. Please add one in Administration Settings.');

            return;
        }

        $this->resetPaymentForm();
        $this->amount = $this->balanceAmount > 0 ? $this->balanceAmount : null;
        $this->paidAt = now()->format('Y-m-d\TH:i');
        $this->showPaymentModal = true;
    }

    public function recordPayment(OrderPaymentService $paymentService): void
    {
        $this->normalizeMoneyInputs();
        if ($this->order->status === OrderStatus::Cancelled) {
            session()->flash('error', 'Cannot record payment for a cancelled order.');

            return;
        }

        // Use policy-based authorization
        $this->authorize('recordPayments', $this->order);

        $this->validate();

        try {
            $paymentService->recordPayment($this->order, [
                'amount' => $this->amount,
                'payment_method_id' => $this->payment_method_id,
                'reference' => $this->reference,
                'paid_at' => $this->paidAt ? \Carbon\Carbon::parse($this->paidAt) : now(),
                'note' => $this->note,
            ], auth()->user());

            // Refresh order and summary
            $this->order->refresh();
            $this->order->load('payments.receiver', 'payments.paymentMethod');
            $this->refreshSummary();

            $this->showPaymentModal = false;
            $this->resetPaymentForm();

            // Dispatch browser event for parent component refresh
            $this->dispatch('payment-recorded');

            session()->flash('success', 'Payment recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    protected function resetPaymentForm(): void
    {
        $this->amount = null;
        $this->payment_method_id = $this->getDefaultPaymentMethodId();
        $this->reference = null;
        $this->paidAt = null;
        $this->note = null;
        $this->resetValidation();
    }

    protected function getDefaultPaymentMethodId(): ?int
    {
        return PaymentMethod::query()->whereKey(1)->value('id')
            ?? PaymentMethod::query()->orderBy('name')->value('id');
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::query()
            ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'account_holder_name']);
    }

    public function render()
    {
        return view('livewire.orders.payments.panel', [
            'payments' => $this->canViewPayments
                ? $this->order->payments()->with(['receiver', 'paymentMethod'])->latest('paid_at')->get()
                : collect(),
            'paymentMethods' => $this->paymentMethods,
            'canViewPayments' => $this->canViewPayments,
            'canRecordPayments' => $this->canRecordPayments,
        ]);
    }
}
