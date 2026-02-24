<?php

namespace App\Livewire\Expenses\Categories;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    // Create/Edit form
    public bool $showFormModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public bool $isSubcategory = false;
    public ?int $parentCategoryId = null;
    public ?int $branchId = null;
    public bool $showBranchSelector = false;

    protected string $paginationTheme = 'tailwind';

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'isSubcategory' => ['boolean'],
            'parentCategoryId' => ['required_if:isSubcategory,true', 'nullable', 'integer', 'exists:expense_categories,id'],
        ];

        if (! $this->editingId) {
            $rules['branchId'] = ['required', 'integer', 'exists:branches,id'];
        }

        return $rules;
    }

    public function updatedIsSubcategory($value): void
    {
        if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->parentCategoryId = null;
            $this->resetErrorBag('parentCategoryId');
        }
    }

    public function mount(): void
    {
        $this->authorize('expenses.categories.manage');

        $user = auth()->user();
        $this->showBranchSelector = (bool) $user?->isGlobalAdmin();
        $this->branchId = $this->getDefaultBranchId();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingId', 'name', 'isSubcategory', 'parentCategoryId', 'branchId']);
        $this->branchId = $this->getDefaultBranchId();
        $this->showFormModal = true;
    }

    public function openEditModal(int $categoryId): void
    {
        $category = ExpenseCategory::findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->isSubcategory = false;
        $this->parentCategoryId = null;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $category = ExpenseCategory::findOrFail($this->editingId);
            $this->authorize('update', $category);

            $category->update(['name' => $this->name]);
            session()->flash('success', 'Category updated successfully.');
        } else {
            $effectiveBranchId = $this->getEffectiveBranchId();
            if (! $effectiveBranchId) {
                $this->addError('branchId', 'Please select a branch first.');

                return;
            }

            if ($this->isSubcategory) {
                $parentCategoryQuery = ExpenseCategory::query();
                if ($this->showBranchSelector) {
                    $parentCategoryQuery->withoutBranchScope();
                }

                $parentCategory = $parentCategoryQuery
                    ->where('branch_id', $effectiveBranchId)
                    ->findOrFail((int) $this->parentCategoryId);
                $this->authorize('update', $parentCategory);

                ExpenseSubcategory::create([
                    'branch_id' => $effectiveBranchId,
                    'expense_category_id' => $parentCategory->id,
                    'name' => $this->name,
                ]);
                session()->flash('success', 'Subcategory created successfully.');
            } else {
                $this->authorize('create', ExpenseCategory::class);

                ExpenseCategory::create([
                    'branch_id' => $effectiveBranchId,
                    'name' => $this->name,
                ]);
                session()->flash('success', 'Category created successfully.');
            }
        }

        $this->showFormModal = false;
        $this->reset(['editingId', 'name', 'isSubcategory', 'parentCategoryId']);
    }

    public function updatedBranchId(): void
    {
        if ($this->isSubcategory) {
            $this->parentCategoryId = null;
        }

        $this->resetErrorBag('parentCategoryId');
    }

    public function delete(int $categoryId): void
    {
        $category = ExpenseCategory::findOrFail($categoryId);
        $this->authorize('delete', $category);

        // Check if category is in use
        if ($category->expenses()->exists()) {
            session()->flash('error', 'Cannot delete category. It is being used by expenses.');
            return;
        }

        if ($category->subcategories()->exists()) {
            session()->flash('error', 'Cannot delete category. It has subcategories.');
            return;
        }

        $category->delete();
        session()->flash('success', 'Category deleted successfully.');
    }

    public function render()
    {
        $query = ExpenseCategory::withCount(['expenses', 'subcategories'])
            ->with(['subcategories' => fn ($q) => $q->orderBy('name')])
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhereHas('subcategories', function ($sq) {
                        $sq->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        $categories = $query->paginate(15);
        $selectedBranchId = $this->branchId ?? $this->getDefaultBranchId();

        $parentCategoriesQuery = ExpenseCategory::query()->orderBy('name');
        if ($this->showBranchSelector) {
            $parentCategoriesQuery->withoutBranchScope();
        }
        if ($selectedBranchId) {
            $parentCategoriesQuery->where('branch_id', $selectedBranchId);
        }

        $parentCategories = $parentCategoriesQuery->get(['id', 'name']);

        $branches = Branch::query()
            ->when(
                $this->showBranchSelector,
                fn ($q) => $q->active(),
                fn ($q) => $q->whereKey(auth()->user()?->branch_id)
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.expenses.categories.index', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'branches' => $branches,
        ])->title(__('Expense Categories'));
    }

    protected function getDefaultBranchId(): ?int
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

    protected function getEffectiveBranchId(): ?int
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
