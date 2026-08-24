<?php

namespace App\Livewire\Payments;

use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Scopes\BranchScope;
use App\Support\Livewire\NormalizesMoneyInputs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Payments')]
class Index extends Component
{
    use NormalizesMoneyInputs;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $branchFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public int $perPage = 15;

    public bool $showEditAmountModal = false;

    public ?int $editingPaymentId = null;

    public ?string $editingAmount = null;

    protected string $paginationTheme = 'tailwind';

    protected function rules(): array
    {
        return [
            'editingAmount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('payments.view'), 403);

        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }

        if ($this->dateTo === '') {
            $this->dateTo = now()->endOfMonth()->toDateString();
        }

        if (! auth()->user()->isGlobalAdmin()) {
            $this->branchFilter = (string) auth()->user()->branch_id;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();

        if (auth()->user()->isGlobalAdmin()) {
            $this->branchFilter = '';
        } else {
            $this->branchFilter = (string) auth()->user()->branch_id;
        }

        $this->resetPage();
    }

    public function openEditAmountModal(int $paymentId): void
    {
        if (! $this->canEditAmounts()) {
            abort(403);
        }

        $payment = $this->paymentActionQuery()->findOrFail($paymentId);

        $this->editingPaymentId = $payment->id;
        $this->editingAmount = number_format((float) $payment->amount, 2, '.', '');
        $this->resetValidation();
        $this->showEditAmountModal = true;
    }

    public function updateAmount(): void
    {
        $this->normalizeMoneyInputs();
        if (! $this->canEditAmounts()) {
            abort(403);
        }

        $this->validate();

        DB::transaction(function () {
            $paymentsQuery = $this->paymentActionQuery();
            $ordersQuery = Order::query();

            if (auth()->user()->isGlobalAdmin()) {
                $ordersQuery->withoutGlobalScope(BranchScope::class);
            }

            $payment = (clone $paymentsQuery)
                ->lockForUpdate()
                ->findOrFail($this->editingPaymentId);

            $payment->update([
                'amount' => (float) $this->editingAmount,
            ]);

            $order = (clone $ordersQuery)
                ->lockForUpdate()
                ->findOrFail($payment->order_id);

            $paidAmount = (float) (clone $paymentsQuery)
                ->where('order_id', $order->id)
                ->sum('amount');

            $status = match (true) {
                $paidAmount <= 0 => PaymentStatus::Unpaid,
                $paidAmount >= (float) $order->total => PaymentStatus::Paid,
                default => PaymentStatus::Partial,
            };

            $order->update(['payment_status' => $status]);
        });

        $this->showEditAmountModal = false;
        $this->editingPaymentId = null;
        $this->editingAmount = null;

        session()->flash('success', 'Payment amount updated successfully.');
    }

    protected function canEditAmounts(): bool
    {
        return auth()->user()->hasRole('superadmin');
    }

    protected function basePaymentsQuery(): Builder
    {
        $query = OrderPayment::query()
            ->with(['branch:id,name', 'order:id,order_no,customer_id', 'order.customer:id,name', 'receiver:id,name', 'paymentMethod:id,name']);

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->whereHas('order', function (Builder $orderQuery) use ($search) {
                $orderQuery->where('order_no', 'like', "%{$search}%");
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('paid_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('paid_at', '<=', $this->dateTo);
        }

        if (auth()->user()->isGlobalAdmin() && $this->branchFilter !== '') {
            $query->where('order_payments.branch_id', (int) $this->branchFilter);
        }

        return $query;
    }

    protected function paymentActionQuery(): Builder
    {
        $query = OrderPayment::query();

        if (auth()->user()->isGlobalAdmin()) {
            $query->withoutGlobalScope(BranchScope::class);
        }

        return $query;
    }

    public function render()
    {
        $paymentsQuery = $this->basePaymentsQuery();

        $stats = [
            'total_amount' => (float) (clone $paymentsQuery)->sum('amount'),
            'total_count' => (int) (clone $paymentsQuery)->count(),
        ];

        $payments = $paymentsQuery
            ->latest('paid_at')
            ->latest('id')
            ->paginate($this->perPage);

        $branches = auth()->user()->isGlobalAdmin()
            ? Branch::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.payments.index', [
            'payments' => $payments,
            'stats' => $stats,
            'branches' => $branches,
            'canEditAmounts' => $this->canEditAmounts(),
        ]);
    }
}
