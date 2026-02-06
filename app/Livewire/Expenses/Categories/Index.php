<?php

namespace App\Livewire\Expenses\Categories;

use App\Models\ExpenseCategory;
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

    protected string $paginationTheme = 'tailwind';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
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
        $this->reset(['editingId', 'name']);
        $this->showFormModal = true;
    }

    public function openEditModal(int $categoryId): void
    {
        $category = ExpenseCategory::findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
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
            $this->authorize('create', ExpenseCategory::class);

            ExpenseCategory::create([
                'name' => $this->name,
            ]);
            session()->flash('success', 'Category created successfully.');
        }

        $this->showFormModal = false;
        $this->reset(['editingId', 'name']);
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

        $category->delete();
        session()->flash('success', 'Category deleted successfully.');
    }

    public function render()
    {
        $query = ExpenseCategory::withCount('expenses')
            ->latest();

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        $categories = $query->paginate(15);

        return view('livewire.expenses.categories.index', [
            'categories' => $categories,
        ])->title(__('Expense Categories'));
    }
}
