<?php

namespace App\Livewire\Inventory\Suppliers;

use App\Models\Branch;
use App\Models\Supplier;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Suppliers')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $branchFilter = null;

    public int $perPage = 15;

    public bool $showFormModal = false;

    public ?int $editingId = null;

    public ?int $branchId = null;

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public ?string $notes = null;

    public bool $showBranchSelector = false;

    protected string $paginationTheme = 'tailwind';

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $this->editingId && $this->showBranchSelector) {
            $rules['branchId'] = ['required', 'integer', 'exists:branches,id'];
        }

        return $rules;
    }

    public function mount(): void
    {
        $this->authorize('inventory.view');

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
        $this->authorize('inventory.items.manage');

        $this->resetForm();
        $this->editingId = null;
        $this->branchId = $this->defaultBranchId();
        $this->showFormModal = true;
    }

    public function openEditModal(int $supplierId): void
    {
        $this->authorize('inventory.items.manage');

        $supplier = $this->findManageableSupplier($supplierId);

        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone;
        $this->email = $supplier->email;
        $this->address = $supplier->address;
        $this->notes = $supplier->notes;
        $this->branchId = $supplier->branch_id;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.items.manage');
        $this->validate();

        $payload = [
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $supplier = $this->findManageableSupplier($this->editingId);
            $supplier->update($payload);
            session()->flash('success', 'Supplier updated successfully.');
        } else {
            $branchId = $this->effectiveCreateBranchId();
            if (! $branchId) {
                $this->addError('branchId', 'Please select a branch first.');

                return;
            }

            Supplier::create([
                ...$payload,
                'branch_id' => $branchId,
            ]);

            session()->flash('success', 'Supplier created successfully.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function delete(int $supplierId): void
    {
        $this->authorize('inventory.items.manage');

        $supplier = $this->findManageableSupplier($supplierId);

        if ($supplier->purchaseOrders()->exists()) {
            session()->flash('error', 'Cannot delete supplier with existing purchase orders.');

            return;
        }

        $supplier->delete();
        session()->flash('success', 'Supplier deleted successfully.');
    }

    public function render()
    {
        $user = auth()->user();

        $query = Supplier::query()
            ->with('branch')
            ->withCount('purchaseOrders')
            ->orderBy('name');

        if ($this->showBranchSelector && $this->branchFilter) {
            $query->where('branch_id', $this->branchFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('address', 'like', "%{$this->search}%");
            });
        }

        $suppliers = $query->paginate($this->perPage);

        $statsQuery = Supplier::query();
        if ($this->showBranchSelector && $this->branchFilter) {
            $statsQuery->where('branch_id', $this->branchFilter);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'with_purchase_orders' => (clone $statsQuery)->has('purchaseOrders')->count(),
        ];

        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.inventory.suppliers.index', [
            'suppliers' => $suppliers,
            'stats' => $stats,
            'branches' => $branches,
            'canView' => $user?->can('inventory.view') ?? false,
            'canManage' => $user?->can('inventory.items.manage') ?? false,
        ]);
    }

    protected function findManageableSupplier(int $supplierId): Supplier
    {
        return Supplier::query()->findOrFail($supplierId);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'phone',
            'email',
            'address',
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
