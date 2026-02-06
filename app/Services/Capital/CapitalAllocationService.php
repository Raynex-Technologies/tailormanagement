<?php

namespace App\Services\Capital;

use App\Enums\CapitalAllocationStatus;
use App\Enums\CapitalTransactionType;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapitalAllocationService
{
    /**
     * Assign a new capital allocation to an accountant.
     */
    public function assign(array $data, User $actor): CapitalAllocation
    {
        // Validate required fields
        if (empty($data['accountant_id'])) {
            throw ValidationException::withMessages([
                'accountant_id' => 'An accountant must be selected.',
            ]);
        }

        if (empty($data['initial_amount']) || (float) $data['initial_amount'] <= 0) {
            throw ValidationException::withMessages([
                'initial_amount' => 'Initial amount must be greater than zero.',
            ]);
        }

        if (empty($data['starts_on'])) {
            throw ValidationException::withMessages([
                'starts_on' => 'Start date is required.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor) {
            // Determine effective branch_id
            if ($actor->isGlobalAdmin()) {
                $branchId = $data['branch_id'] ?? BranchContext::id();
                if ($branchId === null) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Branch is required for this action.',
                    ]);
                }
            } else {
                $branchId = BranchContext::requireId();
            }

            // Validate accountant belongs to same branch (unless global admin)
            $accountant = User::find($data['accountant_id']);
            if (! $accountant) {
                throw ValidationException::withMessages([
                    'accountant_id' => 'Selected accountant not found.',
                ]);
            }

            if (! $actor->isGlobalAdmin() && $accountant->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'accountant_id' => 'Accountant must belong to the same branch.',
                ]);
            }

            // For global admins, verify accountant matches selected branch
            if ($actor->isGlobalAdmin() && $accountant->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'accountant_id' => 'Accountant must belong to the selected branch.',
                ]);
            }

            // Create allocation
            $allocation = CapitalAllocation::create([
                'branch_id' => $branchId,
                'accountant_id' => $data['accountant_id'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'initial_amount' => $data['initial_amount'],
                'spent_amount' => 0,
                'status' => CapitalAllocationStatus::Open,
                'created_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]);

            return $allocation->load('accountant', 'creator');
        });
    }

    /**
     * Close a capital allocation.
     */
    public function close(CapitalAllocation $allocation, User $actor, ?string $note = null): CapitalAllocation
    {
        if ($allocation->status !== CapitalAllocationStatus::Open) {
            throw ValidationException::withMessages([
                'allocation' => 'Only open allocations can be closed.',
            ]);
        }

        return DB::transaction(function () use ($allocation, $actor, $note) {
            // Calculate closing balance
            $closingBalance = $this->availableBalance($allocation);

            // Create closing transaction
            CapitalTransaction::create([
                'branch_id' => $allocation->branch_id,
                'capital_allocation_id' => $allocation->id,
                'type' => CapitalTransactionType::Closing,
                'amount' => $closingBalance,
                'created_by' => $actor->id,
                'note' => $note ?? 'Allocation closed',
            ]);

            // Update allocation
            $allocation->update([
                'status' => CapitalAllocationStatus::Closed,
                'closing_balance' => $closingBalance,
                'closed_at' => now(),
            ]);

            return $allocation->fresh(['accountant', 'creator', 'transactions']);
        });
    }

    /**
     * Calculate available balance for an allocation.
     */
    public function availableBalance(CapitalAllocation $allocation): float
    {
        // Sum all transactions
        $transactions = $allocation->transactions;

        $totalDebits = $transactions
            ->where('type', CapitalTransactionType::Debit)
            ->sum('amount');

        $totalCredits = $transactions
            ->where('type', CapitalTransactionType::Credit)
            ->sum('amount');

        $totalAdjustments = $transactions
            ->where('type', CapitalTransactionType::Adjustment)
            ->sum('amount');

        // Available = initial - debits + credits + adjustments
        return (float) $allocation->initial_amount - $totalDebits + $totalCredits + $totalAdjustments;
    }

    /**
     * Create a debit transaction (e.g., when PR is approved).
     */
    public function createDebit(
        CapitalAllocation $allocation,
        float $amount,
        User $actor,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        ?string $note = null
    ): CapitalTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Debit amount must be greater than zero.',
            ]);
        }

        $availableBalance = $this->availableBalance($allocation);
        if ($amount > $availableBalance) {
            throw ValidationException::withMessages([
                'amount' => "Insufficient balance. Available: " . money_tzs($availableBalance) . ", Requested: " . money_tzs($amount),
            ]);
        }

        return DB::transaction(function () use ($allocation, $amount, $actor, $reference, $note) {
            $transaction = CapitalTransaction::create([
                'branch_id' => $allocation->branch_id,
                'capital_allocation_id' => $allocation->id,
                'type' => CapitalTransactionType::Debit,
                'amount' => $amount,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference ? $reference->id : null,
                'created_by' => $actor->id,
                'note' => $note,
            ]);

            // Update spent_amount on allocation
            $allocation->increment('spent_amount', $amount);

            return $transaction;
        });
    }

    /**
     * Find active allocation for an accountant in a branch.
     * Active = open status and today is within starts_on/ends_on range.
     */
    public function findActiveAllocationForAccountant(int $accountantId, int $branchId): ?CapitalAllocation
    {
        $today = now()->toDateString();

        return CapitalAllocation::where('branch_id', $branchId)
            ->where('accountant_id', $accountantId)
            ->where('status', CapitalAllocationStatus::Open)
            ->where('starts_on', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $today);
            })
            ->latest('starts_on')
            ->first();
    }

    /**
     * Get all open allocations for a branch.
     */
    public function getOpenAllocations(?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = CapitalAllocation::where('status', CapitalAllocationStatus::Open)
            ->with(['accountant', 'creator']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->latest()->get();
    }
}
