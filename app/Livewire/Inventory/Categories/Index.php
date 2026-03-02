<?php

namespace App\Livewire\Inventory\Categories;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Support\BranchContext;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Categories')]
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
    public string $slug = '';
    public bool $slugManuallyEdited = false;

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
                abort(403, 'You must be assigned to a branch to manage inventory categories.');
            }
            $this->branchId = $user->branch_id;
        }
    }

    protected function rules(): array
    {
        $effectiveBranchId = $this->branchId ?? BranchContext::id() ?? auth()->user()->branch_id;
        $uniqueRule = $this->isEditing
            ? "unique:inventory_categories,name,{$this->editingId},id,branch_id,{$effectiveBranchId}"
            : "unique:inventory_categories,name,NULL,id,branch_id,{$effectiveBranchId}";
        $slugRule = Rule::unique('inventory_categories', 'slug')
            ->where(fn ($query) => $query->where('branch_id', $effectiveBranchId));

        if ($this->isEditing && $this->editingId) {
            $slugRule = $slugRule->ignore($this->editingId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $uniqueRule],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
        ];

        // Branch_id required for global admins if no context
        if ($this->showBranchSelector && ! $this->isEditing) {
            $rules['branchId'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    protected $messages = [
        'name.required' => 'Category name is required.',
        'name.unique' => 'A category with this name already exists.',
        'slug.unique' => 'This slug is already in use for the selected branch.',
        'branchId.required' => 'Please select a branch for this category.',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugManuallyEdited = trim($value) !== '';
    }

    public function openCreateModal(): void
    {
        $this->authorize('inventory.items.manage');

        $this->reset(['name', 'slug', 'editingId', 'slugManuallyEdited']);
        $this->isEditing = false;

        // Pre-fill branch_id for global admins if context exists
        if ($this->showBranchSelector) {
            $this->branchId = BranchContext::id();
        }

        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $category = InventoryCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->slugManuallyEdited = true;
        $this->branchId = $category->branch_id;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.items.manage');

        $this->name = trim($this->name);
        $this->slug = trim($this->slug);
        $this->slug = $this->slug === '' ? '' : Str::slug($this->slug);

        $this->validate();

        // Determine effective branch_id
        $effectiveBranchId = $this->branchId;
        if (! auth()->user()->isGlobalAdmin()) {
            $effectiveBranchId = BranchContext::requireId();
        }

        if ($this->isEditing) {
            $category = InventoryCategory::findOrFail($this->editingId);
            $category->update([
                'name' => $this->name,
                'slug' => $this->slug === '' ? null : $this->slug,
            ]);

            session()->flash('success', 'Category updated successfully.');
        } else {
            InventoryCategory::create([
                'branch_id' => $effectiveBranchId,
                'name' => $this->name,
                'slug' => $this->slug === '' ? null : $this->slug,
            ]);

            session()->flash('success', 'Category created successfully.');
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['name', 'slug', 'editingId', 'isEditing', 'slugManuallyEdited']);
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $category = InventoryCategory::withCount('items')->findOrFail($id);
        $this->deletingId = $category->id;
        $this->deletingName = $category->name;

        if ($category->items_count > 0) {
            session()->flash('error', "Cannot delete '{$category->name}'. It has {$category->items_count} item(s) assigned.");

            return;
        }

        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorize('inventory.items.manage');

        $category = InventoryCategory::withCount('items')->findOrFail($this->deletingId);

        if ($category->items_count > 0) {
            session()->flash('error', "Cannot delete '{$category->name}'. It has {$category->items_count} item(s) assigned.");
            $this->closeDeleteModal();

            return;
        }

        $category->delete();
        session()->flash('success', 'Category deleted successfully.');
        $this->closeDeleteModal();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingId', 'deletingName']);
    }

    public function render()
    {
        $categories = InventoryCategory::query()
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage);

        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get()
            : collect();

        return view('livewire.inventory.categories.index', [
            'categories' => $categories,
            'branches' => $branches,
        ]);
    }
}
