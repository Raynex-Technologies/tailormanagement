<?php

namespace App\Services\Expenses;

use App\Enums\CapitalAllocationStatus;
use App\Enums\CapitalTransactionType;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Models\Expense;
use App\Models\ExpenseSubcategory;
use App\Models\User;
use App\Notifications\ExpenseCreatedNotification;
use App\Services\Capital\CapitalAllocationService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        protected CapitalAllocationService $capitalService
    ) {}

    /**
     * Create a new expense.
     *
     * @param  array  $data  Expense data including optional 'branch_id' for global admins
     * @param  User  $actor  The user creating the expense
     */
    public function create(array $data, User $actor): Expense
    {
        $this->validateData($data);

        return DB::transaction(function () use ($data, $actor) {
            // Determine effective branch_id
            $explicitBranchId = $data['branch_id'] ?? null;
            $branchId = BranchContext::getEffectiveBranchId($explicitBranchId);

            // Validate capital allocation if provided
            $allocationId = $data['capital_allocation_id'] ?? null;
            if ($allocationId) {
                $this->validateCapitalAllocation($allocationId, (float) $data['amount'], $branchId);
            }

            $categoryId = $data['expense_category_id'] ?? null;
            $subcategoryId = $data['expense_subcategory_id'] ?? null;
            $this->validateExpenseSubcategory($subcategoryId, $categoryId, $branchId);

            // Create the expense
            $expense = Expense::create([
                'branch_id' => $branchId,
                'expense_category_id' => $categoryId,
                'expense_subcategory_id' => $subcategoryId,
                'amount' => $data['amount'],
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'expense_date' => $data['expense_date'],
                'capital_allocation_id' => $allocationId,
                'created_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]);

            // Create capital transaction if linked to allocation
            if ($allocationId) {
                $allocation = CapitalAllocation::find($allocationId);
                $this->capitalService->createDebit(
                    $allocation,
                    (float) $data['amount'],
                    $actor,
                    $expense,
                    "Expense: " . ($data['vendor'] ?? 'N/A')
                );
            }

            // Notify stakeholders
            $this->notifyExpenseCreated($expense);

            return $expense->load(['category', 'subcategory', 'capitalAllocation', 'creator']);
        });
    }

    /**
     * Update an existing expense.
     * Note: If expense is linked to capital allocation, only certain fields can be edited.
     */
    public function update(Expense $expense, array $data, User $actor): Expense
    {
        $targetCategoryId = array_key_exists('expense_category_id', $data)
            ? $data['expense_category_id']
            : $expense->expense_category_id;
        $targetSubcategoryId = array_key_exists('expense_subcategory_id', $data)
            ? $data['expense_subcategory_id']
            : $expense->expense_subcategory_id;

        $this->validateExpenseSubcategory($targetSubcategoryId, $targetCategoryId, $expense->branch_id);

        // Check if expense is linked to capital allocation
        $isLinkedToCapital = $expense->capital_allocation_id !== null;

        if ($isLinkedToCapital) {
            // Block editing amount or capital_allocation_id for linked expenses
            if (isset($data['amount']) && (float) $data['amount'] !== (float) $expense->amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot modify the amount of an expense linked to a capital allocation.',
                ]);
            }

            if (isset($data['capital_allocation_id']) && $data['capital_allocation_id'] !== $expense->capital_allocation_id) {
                throw ValidationException::withMessages([
                    'capital_allocation_id' => 'Cannot change the capital allocation for a linked expense.',
                ]);
            }
        } else {
            // Validate new data for unlinked expenses
            if (isset($data['amount'])) {
                if ((float) $data['amount'] <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Amount must be greater than zero.',
                    ]);
                }
            }

            // If linking to capital allocation now
            $newAllocationId = $data['capital_allocation_id'] ?? null;
            if ($newAllocationId && $newAllocationId !== $expense->capital_allocation_id) {
                $amount = $data['amount'] ?? $expense->amount;
                $this->validateCapitalAllocation($newAllocationId, (float) $amount, $expense->branch_id);
            }
        }

        return DB::transaction(function () use ($expense, $data, $actor, $isLinkedToCapital) {
            // Determine what can be updated
            $updateData = [];

            // These fields can always be updated
            $editableFields = ['expense_category_id', 'expense_subcategory_id', 'vendor', 'reference', 'expense_date', 'note'];
            foreach ($editableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Amount and capital_allocation_id only if not linked
            if (! $isLinkedToCapital) {
                if (isset($data['amount'])) {
                    $updateData['amount'] = $data['amount'];
                }

                $newAllocationId = $data['capital_allocation_id'] ?? null;
                if ($newAllocationId && $newAllocationId !== $expense->capital_allocation_id) {
                    $updateData['capital_allocation_id'] = $newAllocationId;

                    // Create capital transaction for the new linkage
                    $allocation = CapitalAllocation::find($newAllocationId);
                    $amount = $data['amount'] ?? $expense->amount;
                    $this->capitalService->createDebit(
                        $allocation,
                        (float) $amount,
                        $actor,
                        $expense,
                        "Expense: " . ($data['vendor'] ?? $expense->vendor ?? 'N/A')
                    );
                }
            }

            $expense->update($updateData);

            return $expense->fresh(['category', 'subcategory', 'capitalAllocation', 'creator']);
        });
    }

    /**
     * Validate basic expense data.
     */
    protected function validateData(array $data): void
    {
        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        if (empty($data['expense_date'])) {
            throw ValidationException::withMessages([
                'expense_date' => 'Expense date is required.',
            ]);
        }
    }

    /**
     * Validate capital allocation for expense linkage.
     */
    protected function validateCapitalAllocation(int $allocationId, float $amount, ?int $branchId): void
    {
        $allocation = CapitalAllocation::find($allocationId);

        if (! $allocation) {
            throw ValidationException::withMessages([
                'capital_allocation_id' => 'Selected capital allocation not found.',
            ]);
        }

        if ($branchId && $allocation->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'capital_allocation_id' => 'Capital allocation must belong to the same branch.',
            ]);
        }

        if ($allocation->status !== CapitalAllocationStatus::Open) {
            throw ValidationException::withMessages([
                'capital_allocation_id' => 'Selected capital allocation is not open.',
            ]);
        }

        $availableBalance = $this->capitalService->availableBalance($allocation);
        if ($amount > $availableBalance) {
            throw ValidationException::withMessages([
                'capital_allocation_id' => "Insufficient capital balance. Available: " . money_tzs($availableBalance) . ", Required: " . money_tzs($amount),
            ]);
        }
    }

    protected function validateExpenseSubcategory(?int $subcategoryId, ?int $categoryId, ?int $branchId): void
    {
        if (! $subcategoryId) {
            return;
        }

        if (! $categoryId) {
            throw ValidationException::withMessages([
                'expense_subcategory_id' => 'A category is required when a subcategory is selected.',
            ]);
        }

        $subcategoryQuery = ExpenseSubcategory::whereKey($subcategoryId);
        if ($branchId) {
            $subcategoryQuery->where('branch_id', $branchId);
        }

        $subcategory = $subcategoryQuery->first();

        if (! $subcategory) {
            throw ValidationException::withMessages([
                'expense_subcategory_id' => 'Selected subcategory is invalid for this branch.',
            ]);
        }

        if ((int) $subcategory->expense_category_id !== (int) $categoryId) {
            throw ValidationException::withMessages([
                'expense_subcategory_id' => 'Selected subcategory does not belong to the chosen category.',
            ]);
        }
    }

    /**
     * Notify stakeholders about new expense.
     */
    protected function notifyExpenseCreated(Expense $expense): void
    {
        $recipients = collect();

        // Notify accountants in branch
        $accountants = User::role('accountant')
            ->where('branch_id', $expense->branch_id)
            ->get();
        $recipients = $recipients->merge($accountants);

        // Notify branch managers
        $branchManagers = User::role('branch_manager')
            ->where('branch_id', $expense->branch_id)
            ->get();
        $recipients = $recipients->merge($branchManagers);

        // Remove duplicates and the creator
        $recipients = $recipients->unique('id')
            ->filter(fn ($user) => $user->id !== $expense->created_by);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ExpenseCreatedNotification($expense));
        }
    }

    /**
     * Check if expense can be fully edited (not linked to capital).
     */
    public function canEditAmountAndAllocation(Expense $expense): bool
    {
        return $expense->capital_allocation_id === null;
    }

    /**
     * Get the capital transaction linked to this expense.
     */
    public function getLinkedCapitalTransaction(Expense $expense): ?CapitalTransaction
    {
        if (! $expense->capital_allocation_id) {
            return null;
        }

        return CapitalTransaction::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->first();
    }
}
