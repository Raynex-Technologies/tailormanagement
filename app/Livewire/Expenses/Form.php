<?php

namespace App\Livewire\Expenses;

use App\Enums\CapitalAllocationStatus;
use App\Models\Branch;
use App\Models\CapitalAllocation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Services\Capital\CapitalAllocationService;
use App\Services\Expenses\ExpenseService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Expense $expense = null;
    public bool $isEdit = false;
    public bool $isLinkedToCapital = false;

    // Branch (for global admins)
    public ?int $branchId = null;
    public bool $showBranchSelector = false;

    // Form fields
    public ?string $expenseDate = null;
    public ?int $expenseCategoryId = null;
    public ?int $expenseSubcategoryId = null;
    public ?string $vendor = null;
    public ?float $amount = null;
    public ?string $reference = null;
    public ?int $capitalAllocationId = null;
    public ?string $note = null;

    // For allocation preview
    public ?float $availableBalance = null;

    protected function rules(): array
    {
        $user = auth()->user();
        $effectiveBranchId = $this->getFormBranchId();

        $categoryExistsRule = Rule::exists('expense_categories', 'id');
        if ($effectiveBranchId) {
            $categoryExistsRule = $categoryExistsRule->where(fn ($query) => $query->where('branch_id', $effectiveBranchId));
        }

        $subcategoryExistsRule = Rule::exists('expense_subcategories', 'id')
            ->where(function ($query) use ($effectiveBranchId) {
                if ($this->expenseCategoryId) {
                    $query->where('expense_category_id', $this->expenseCategoryId);
                } else {
                    $query->whereRaw('1 = 0');
                }

                if ($effectiveBranchId) {
                    $query->where('branch_id', $effectiveBranchId);
                }
            });

        $rules = [
            'expenseDate' => ['required', 'date'],
            'expenseCategoryId' => ['nullable', 'integer', $categoryExistsRule],
            'expenseSubcategoryId' => ['nullable', 'integer', $subcategoryExistsRule],
            'vendor' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'capitalAllocationId' => ['nullable', 'exists:capital_allocations,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];

        // Amount can only be edited if not linked to capital
        if (! $this->isLinkedToCapital) {
            $rules['amount'] = ['required', 'numeric', 'min:0.01'];
        }

        // Global admins must select branch on create
        if ($user->isGlobalAdmin() && ! $this->isEdit) {
            $rules['branchId'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    public function mount(?Expense $expense = null): void
    {
        if ($expense && $expense->exists) {
            $this->authorize('update', $expense);
            $this->expense = $expense;
            $this->isEdit = true;
            $this->isLinkedToCapital = $expense->capital_allocation_id !== null;
            $this->branchId = $expense->branch_id;

            // Populate form
            $this->expenseDate = $expense->expense_date->format('Y-m-d');
            $this->expenseCategoryId = $expense->expense_category_id;
            $this->expenseSubcategoryId = $expense->expense_subcategory_id;
            $this->vendor = $expense->vendor;
            $this->amount = (float) $expense->amount;
            $this->reference = $expense->reference;
            $this->capitalAllocationId = $expense->capital_allocation_id;
            $this->note = $expense->note;

            if ($this->capitalAllocationId) {
                $this->updateAvailableBalance();
            }
        } else {
            $this->authorize('expenses.manage');
            $this->expenseDate = now()->format('Y-m-d');
            $this->initializeBranchContext();
        }
    }

    /**
     * Initialize branch context for new expenses.
     */
    protected function initializeBranchContext(): void
    {
        $user = auth()->user();

        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($user->isGlobalAdmin()) {
            $this->branchId = BranchContext::id() ?? $user->branch_id;
        } else {
            $this->branchId = $user->branch_id;
        }
    }

    public function updatedCapitalAllocationId(): void
    {
        $this->updateAvailableBalance();
    }

    public function updatedExpenseCategoryId(): void
    {
        $this->expenseSubcategoryId = null;
        $this->resetErrorBag('expenseSubcategoryId');
    }

    public function updatedBranchId(): void
    {
        if (! $this->isEdit) {
            $this->expenseCategoryId = null;
            $this->expenseSubcategoryId = null;
            $this->capitalAllocationId = null;
            $this->availableBalance = null;
            $this->resetErrorBag(['expenseCategoryId', 'expenseSubcategoryId', 'capitalAllocationId']);
        }
    }

    protected function updateAvailableBalance(): void
    {
        if ($this->capitalAllocationId) {
            $allocation = CapitalAllocation::find($this->capitalAllocationId);
            if ($allocation) {
                $this->availableBalance = app(CapitalAllocationService::class)->availableBalance($allocation);
            } else {
                $this->availableBalance = null;
            }
        } else {
            $this->availableBalance = null;
        }
    }

    public function save(ExpenseService $service): void
    {
        $this->validate();

        $user = auth()->user();

        try {
            $data = [
                'expense_date' => $this->expenseDate,
                'expense_category_id' => $this->expenseCategoryId,
                'expense_subcategory_id' => $this->expenseSubcategoryId,
                'vendor' => $this->vendor,
                'reference' => $this->reference,
                'note' => $this->note,
            ];

            // Only include amount and capital_allocation_id if not linked
            if (! $this->isLinkedToCapital) {
                $data['amount'] = $this->amount;
                $data['capital_allocation_id'] = $this->capitalAllocationId;
            }

            // Include branch_id for global admins on create
            if (! $this->isEdit && $user->isGlobalAdmin()) {
                $data['branch_id'] = $this->branchId;
            }

            if ($this->isEdit) {
                $expense = $service->update($this->expense, $data, $user);
                session()->flash('success', 'Expense updated successfully.');
            } else {
                $expense = $service->create($data, $user);
                session()->flash('success', 'Expense created successfully.');
            }

            $this->redirect(route('expenses.show', $expense), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save expense: '.$e->getMessage());
        }
    }

    public function render()
    {
        $effectiveBranchId = $this->getFormBranchId();
        $isGlobalAdmin = (bool) auth()->user()?->isGlobalAdmin();

        if ($this->showBranchSelector && ! $this->isEdit && ! $effectiveBranchId) {
            $categories = collect();
        } else {
            $categoriesQuery = ExpenseCategory::query()->orderBy('name');
            if ($isGlobalAdmin) {
                $categoriesQuery->withoutBranchScope();
            }
            if ($effectiveBranchId) {
                $categoriesQuery->where('branch_id', $effectiveBranchId);
            }
            $categories = $categoriesQuery->get(['id', 'name']);
        }

        if ($this->expenseCategoryId && ! $categories->contains('id', $this->expenseCategoryId)) {
            $this->expenseCategoryId = null;
            $this->expenseSubcategoryId = null;
        }

        $subcategories = collect();
        if ($this->expenseCategoryId) {
            $subcategoriesQuery = ExpenseSubcategory::query()
                ->where('expense_category_id', $this->expenseCategoryId)
                ->orderBy('name');

            if ($isGlobalAdmin) {
                $subcategoriesQuery->withoutBranchScope();
            }
            if ($effectiveBranchId) {
                $subcategoriesQuery->where('branch_id', $effectiveBranchId);
            }

            $subcategories = $subcategoriesQuery->get(['id', 'name']);
        }

        if ($this->expenseSubcategoryId && ! $subcategories->contains('id', $this->expenseSubcategoryId)) {
            $this->expenseSubcategoryId = null;
        }

        // Get open allocations in selected/current branch
        $allocationsQuery = CapitalAllocation::where('status', CapitalAllocationStatus::Open)
            ->with('accountant')
            ->orderBy('allocation_no');

        if ($this->branchId) {
            $allocationsQuery->where('branch_id', $this->branchId);
        }

        $allocations = $allocationsQuery->get();

        // Get branches for global admin selector
        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.expenses.form', [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'allocations' => $allocations,
            'branches' => $branches,
        ])->title($this->isEdit ? __('Edit Expense') : __('New Expense'));
    }

    protected function getFormBranchId(): ?int
    {
        if ($this->expense?->exists) {
            return $this->expense->branch_id;
        }

        $user = auth()->user();
        if (! $user) {
            return $this->branchId;
        }

        if ($user->isGlobalAdmin()) {
            return $this->branchId ?? BranchContext::id() ?? $user->branch_id;
        }

        return $user->branch_id;
    }
}
