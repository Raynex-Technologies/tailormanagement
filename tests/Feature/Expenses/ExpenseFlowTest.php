<?php

namespace Tests\Feature\Expenses;

use App\Enums\CapitalAllocationStatus;
use App\Enums\CapitalTransactionType;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Expenses\ExpenseService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseFlowTest extends TestCase
{
    protected ExpenseService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expenseService = app(ExpenseService::class);
    }

    public function test_expense_linked_to_capital_creates_debit_transaction(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);

        // Create open allocation
        $allocation = CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-EXP-001',
            'accountant_id' => $user->id,
            'initial_amount' => 500000,
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Open,
            'created_by' => $user->id,
        ]);

        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);

        $initialTransactionCount = CapitalTransaction::count();

        // Create expense linked to allocation
        $expense = $this->expenseService->create([
            'expense_category_id' => $category->id,
            'capital_allocation_id' => $allocation->id,
            'expense_date' => now()->toDateString(),
            'amount' => 75000,
            'vendor' => 'Test Vendor',
            'note' => 'Test expense with capital link',
        ], $user);

        $allocation->refresh();

        // Check capital transaction created
        $this->assertEquals($initialTransactionCount + 1, CapitalTransaction::count());

        $transaction = CapitalTransaction::latest()->first();
        $this->assertEquals(CapitalTransactionType::Debit, $transaction->type);
        $this->assertEquals(75000, $transaction->amount);
        $this->assertEquals($allocation->id, $transaction->capital_allocation_id);

        // Check allocation spent amount updated
        $this->assertEquals(75000, $allocation->spent_amount);
    }

    public function test_expense_linked_to_capital_blocks_overspend(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);

        // Create allocation with small balance
        $allocation = CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-EXP-002',
            'accountant_id' => $user->id,
            'initial_amount' => 50000,
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Open,
            'created_by' => $user->id,
        ]);

        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);

        $this->expectException(ValidationException::class);

        // Try to create expense larger than allocation balance
        $this->expenseService->create([
            'expense_category_id' => $category->id,
            'capital_allocation_id' => $allocation->id,
            'expense_date' => now()->toDateString(),
            'amount' => 75000, // More than 50000 allocation
            'vendor' => 'Test Vendor',
            'note' => 'Expense exceeding allocation',
        ], $user);
    }

    public function test_expense_without_capital_link_does_not_create_transaction(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);

        $initialTransactionCount = CapitalTransaction::count();

        // Create expense WITHOUT allocation link
        $expense = $this->expenseService->create([
            'expense_category_id' => $category->id,
            'capital_allocation_id' => null,
            'expense_date' => now()->toDateString(),
            'amount' => 50000,
            'vendor' => 'Test Vendor',
            'note' => 'Standalone expense',
        ], $user);

        // No new capital transaction
        $this->assertEquals($initialTransactionCount, CapitalTransaction::count());

        // Expense should exist
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 50000,
            'capital_allocation_id' => null,
        ]);
    }

    public function test_expense_cannot_link_to_closed_allocation(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);

        // Create closed allocation
        $allocation = CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-EXP-003',
            'accountant_id' => $user->id,
            'initial_amount' => 500000,
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Closed,
            'created_by' => $user->id,
        ]);

        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);

        $this->expectException(ValidationException::class);

        // Try to create expense linked to closed allocation
        $this->expenseService->create([
            'expense_category_id' => $category->id,
            'capital_allocation_id' => $allocation->id,
            'expense_date' => now()->toDateString(),
            'amount' => 25000,
            'vendor' => 'Test Vendor',
            'note' => 'Expense with closed allocation',
        ], $user);
    }

    public function test_expense_is_branch_scoped(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);

        $expense = $this->expenseService->create([
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => 30000,
            'vendor' => 'Branch Test Vendor',
            'note' => 'Testing branch scoping',
        ], $user);

        $this->assertEquals($this->branch->id, $expense->branch_id);
    }
}
