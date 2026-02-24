<?php

namespace App\Livewire\Expenses\Categories;

use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
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

    protected string $paginationTheme = 'tailwind';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'isSubcategory' => ['boolean'],
            'parentCategoryId' => ['required_if:isSubcategory,true', 'nullable', 'integer', 'exists:expense_categories,id'],
        ];
    }

    public function updatedIsSubcategory(bool $value): void
    {
        if (! $value) {
            $this->parentCategoryId = null;
        }
    }

    public function mount(): void
    {
        $this->authorize('expenses.categories.manage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingId', 'name', 'isSubcategory', 'parentCategoryId']);
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
        } elseif ($this->isSubcategory) {
            $parentCategory = ExpenseCategory::findOrFail((int) $this->parentCategoryId);
            $this->authorize('update', $parentCategory);

            ExpenseSubcategory::create([
                'branch_id' => $parentCategory->branch_id,
                'expense_category_id' => $parentCategory->id,
                'name' => $this->name,
            ]);
            session()->flash('success', 'Subcategory created successfully.');
        } else {
            $this->authorize('create', ExpenseCategory::class);

            ExpenseCategory::create([
                'name' => $this->name,
            ]);
            session()->flash('success', 'Category created successfully.');
        }

        $this->showFormModal = false;
        $this->reset(['editingId', 'name', 'isSubcategory', 'parentCategoryId']);
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

        $parentCategories = ExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.expenses.categories.index', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
        ])->title(__('Expense Categories'));
    }
}
