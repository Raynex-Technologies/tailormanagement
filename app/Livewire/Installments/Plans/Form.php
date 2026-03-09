<?php

namespace App\Livewire\Installments\Plans;

use App\Enums\InstallmentFrequency;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Package;
use App\Services\Installments\InstallmentCalculator;
use App\Services\Installments\InstallmentPlanService;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Create Installment Plan')]
class Form extends Component
{
    public ?int $branch_id = null;

    public ?int $customer_id = null;

    public ?int $package_id = null;

    public ?float $package_price = null;

    public int $installments_count = 6;

    public string $payment_frequency = 'monthly';

    public string $start_date = '';

    public string $first_due_date = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', InstallmentPlan::class);

        $user = auth()->user();
        $this->branch_id = $user->isGlobalAdmin()
            ? (BranchContext::id() ?? $user->branch_id)
            : $user->branch_id;

        $this->start_date = now()->toDateString();
        $this->first_due_date = now()->addMonth()->toDateString();
    }

    protected function rules(): array
    {
        $rules = [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('branch_id', $this->effectiveBranchId())),
            ],
            'package_id' => [
                'required',
                Rule::exists('packages', 'id')->where(fn ($query) => $query->where('branch_id', $this->effectiveBranchId())),
            ],
            'package_price' => ['required', 'numeric', 'min:0.01'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:60'],
            'payment_frequency' => ['required', 'in:' . implode(',', InstallmentFrequency::values())],
            'start_date' => ['required', 'date'],
            'first_due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (auth()->user()->isGlobalAdmin()) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    public function updatedPackageId(): void
    {
        $package = $this->selectedPackage;
        $this->package_price = $package ? (float) $package->price : null;
    }

    public function updatedPaymentFrequency(): void
    {
        $startDate = CarbonImmutable::parse($this->start_date ?: now()->toDateString());
        $this->first_due_date = InstallmentFrequency::from($this->payment_frequency)
            ->addTo($startDate)
            ->toDateString();
    }

    #[Computed]
    public function branches()
    {
        return Branch::active()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function customers()
    {
        if (auth()->user()->isGlobalAdmin() && ! $this->branch_id) {
            return collect();
        }

        return Customer::query()
            ->when($this->branch_id, fn ($query) => $query->where('branch_id', $this->branch_id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    #[Computed]
    public function packages()
    {
        if (auth()->user()->isGlobalAdmin() && ! $this->branch_id) {
            return collect();
        }

        return Package::query()
            ->when($this->branch_id, fn ($query) => $query->where('branch_id', $this->branch_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'duration_value', 'duration_unit']);
    }

    #[Computed]
    public function selectedPackage(): ?Package
    {
        return $this->package_id ? $this->packages->firstWhere('id', $this->package_id) : null;
    }

    #[Computed]
    public function installmentPreview(): array
    {
        if (! $this->package_price || $this->installments_count < 1 || blank($this->first_due_date)) {
            return [];
        }

        return app(InstallmentCalculator::class)->buildSchedule(
            (float) $this->package_price,
            $this->installments_count,
            InstallmentFrequency::from($this->payment_frequency),
            CarbonImmutable::parse($this->first_due_date),
        );
    }

    public function save(InstallmentPlanService $service)
    {
        $validated = $this->validate();

        $plan = $service->create([
            ...$validated,
            'payment_frequency' => InstallmentFrequency::from($validated['payment_frequency']),
        ], auth()->user());

        session()->flash('success', 'Installment plan created successfully.');

        return redirect()->route('installments.plans.show', $plan);
    }

    public function render()
    {
        return view('livewire.installments.plans.form');
    }

    protected function effectiveBranchId(): ?int
    {
        return auth()->user()->isGlobalAdmin() ? $this->branch_id : auth()->user()->branch_id;
    }
}
