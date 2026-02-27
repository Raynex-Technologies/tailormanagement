<?php

namespace App\Livewire\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public ?int $branchFilter = null;
    public int $perPage = 15;

    // Form modal state
    public bool $showFormModal = false;
    public ?int $editingId = null;
    public ?int $branchId = null;
    public string $name = '';
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $address = null;
    public ?string $dob = null;
    public ?string $notes = null;

    public bool $showBranchSelector = false;

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'branchFilter' => ['except' => null],
    ];

    protected function rules(): array
    {
        $targetBranchId = $this->editingId
            ? $this->branchId
            : $this->effectiveCreateBranchId();

        $phoneRules = ['nullable', 'string', 'max:50'];

        if ($targetBranchId) {
            $phoneUniqueRule = Rule::unique('customers', 'phone')
                ->where(fn ($query) => $query->where('branch_id', $targetBranchId));

            if ($this->editingId) {
                $phoneUniqueRule = $phoneUniqueRule->ignore($this->editingId);
            }

            $phoneRules[] = $phoneUniqueRule;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => $phoneRules,
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $this->editingId && $this->showBranchSelector) {
            $rules['branchId'] = ['required', 'integer', 'exists:branches,id'];
        }

        return $rules;
    }

    public function mount(): void
    {
        $this->authorize('users.view');

        $user = auth()->user();
        $this->showBranchSelector = (bool) $user?->isGlobalAdmin();
        $this->branchId = $this->defaultBranchId();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'branchFilter']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('users.manage');

        $this->resetForm();
        $this->editingId = null;
        $this->branchId = $this->defaultBranchId();
        $this->showFormModal = true;
    }

    public function openEditModal(int $customerId): void
    {
        $this->authorize('users.manage');

        $customer = $this->findManageableCustomer($customerId);

        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->email = $customer->email;
        $this->address = $customer->address;
        $this->dob = $customer->dob?->format('Y-m-d');
        $this->notes = $customer->notes;
        $this->branchId = $customer->branch_id;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorize('users.manage');
        $this->validate();

        $payload = [
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'dob' => $this->dob ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $customer = $this->findManageableCustomer($this->editingId);
            $customer->update($payload);
            session()->flash('success', 'Customer updated successfully.');
        } else {
            $branchId = $this->effectiveCreateBranchId();
            if (! $branchId) {
                $this->addError('branchId', 'Please select a branch first.');

                return;
            }

            Customer::create([
                ...$payload,
                'branch_id' => $branchId,
            ]);

            session()->flash('success', 'Customer created successfully.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function delete(int $customerId): void
    {
        $this->authorize('users.manage');

        $customer = $this->findManageableCustomer($customerId);

        if ($customer->orders()->exists()) {
            session()->flash('error', 'Cannot delete customer with existing orders.');

            return;
        }

        $customer->delete();
        session()->flash('success', 'Customer deleted successfully.');
    }

    public function render()
    {
        $user = auth()->user();

        $query = Customer::query()
            ->with('branch')
            ->withCount('orders')
            ->orderBy('name');

        if ($this->showBranchSelector && $this->branchFilter) {
            $query->where('branch_id', $this->branchFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        $customers = $query->paginate($this->perPage);

        $statsQuery = Customer::query();
        if ($this->showBranchSelector && $this->branchFilter) {
            $statsQuery->where('branch_id', $this->branchFilter);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'with_orders' => (clone $statsQuery)->has('orders')->count(),
        ];

        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.customers.index', [
            'customers' => $customers,
            'stats' => $stats,
            'branches' => $branches,
            'canView' => $user?->can('users.view') ?? false,
            'canManage' => $user?->can('users.manage') ?? false,
        ])->title(__('Customers'));
    }

    protected function findManageableCustomer(int $customerId): Customer
    {
        return Customer::query()->findOrFail($customerId);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'phone',
            'email',
            'address',
            'dob',
            'notes',
        ]);

        $this->resetErrorBag();
    }

    protected function defaultBranchId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isGlobalAdmin()) {
            return BranchContext::id() ?? $user->branch_id;
        }

        return $user->branch_id;
    }

    protected function effectiveCreateBranchId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isGlobalAdmin()) {
            return $this->branchId ?? BranchContext::id() ?? $user->branch_id;
        }

        return $user->branch_id;
    }
}
