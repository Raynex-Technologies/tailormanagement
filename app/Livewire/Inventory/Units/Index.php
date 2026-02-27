<?php

namespace App\Livewire\Inventory\Units;

use App\Models\Branch;
use App\Models\InventoryUnit;
use App\Support\BranchContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Units')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public int $perPage = 15;

    // Modal state
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    // Branch selection for global admins
    public ?int $branchId = null;
    public bool $showBranchSelector = false;

    // Delete confirmation
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;
    public string $deletingName = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($this->showBranchSelector) {
            $this->branchId = BranchContext::id();
        } else {
            if (! $user->branch_id) {
                abort(403, 'You must be assigned to a branch to manage inventory units.');
            }

            $this->branchId = $user->branch_id;
        }
    }

    protected function rules(): array
    {
        $effectiveBranchId = $this->branchId ?? BranchContext::id() ?? auth()->user()->branch_id;
        $uniqueRule = $this->isEditing
            ? "unique:inventory_units,name,{$this->editingId},id,branch_id,{$effectiveBranchId}"
            : "unique:inventory_units,name,NULL,id,branch_id,{$effectiveBranchId}";

        $rules = [
            'name' => ['required', 'string', 'max:50', $uniqueRule],
        ];

        if ($this->showBranchSelector && ! $this->isEditing) {
            $rules['branchId'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    protected $messages = [
        'name.required' => 'Unit name is required.',
        'name.unique' => 'A unit with this name already exists.',
        'branchId.required' => 'Please select a branch for this unit.',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('inventory.items.manage');

        $this->reset(['name', 'editingId']);
        $this->isEditing = false;

        if ($this->showBranchSelector) {
            $this->branchId = BranchContext::id();
        }

        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $unit = InventoryUnit::findOrFail($id);

        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->branchId = $unit->branch_id;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.items.manage');

        $this->validate();

        $effectiveBranchId = $this->branchId;
        if (! auth()->user()->isGlobalAdmin()) {
            $effectiveBranchId = BranchContext::requireId();
        }

        if ($this->isEditing) {
            $unit = InventoryUnit::findOrFail($this->editingId);
            $unit->update([
                'name' => $this->name,
            ]);

            session()->flash('success', 'Unit updated successfully.');
        } else {
            InventoryUnit::create([
                'branch_id' => $effectiveBranchId,
                'name' => $this->name,
            ]);

            session()->flash('success', 'Unit created successfully.');
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['name', 'editingId', 'isEditing']);
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $unit = InventoryUnit::withCount('items')->findOrFail($id);
        $this->deletingId = $unit->id;
        $this->deletingName = $unit->name;

        if ($unit->items_count > 0) {
            session()->flash('error', "Cannot delete '{$unit->name}'. It has {$unit->items_count} item(s) assigned.");

            return;
        }

        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorize('inventory.items.manage');

        $unit = InventoryUnit::withCount('items')->findOrFail($this->deletingId);

        if ($unit->items_count > 0) {
            session()->flash('error', "Cannot delete '{$unit->name}'. It has {$unit->items_count} item(s) assigned.");
            $this->closeDeleteModal();

            return;
        }

        $unit->delete();
        session()->flash('success', 'Unit deleted successfully.');
        $this->closeDeleteModal();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingId', 'deletingName']);
    }

    public function render()
    {
        $units = InventoryUnit::query()
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage);

        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get()
            : collect();

        return view('livewire.inventory.units.index', [
            'units' => $units,
            'branches' => $branches,
        ]);
    }
}
