<?php

namespace Tests\Feature\Branch;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Support\BranchContext;
use Tests\TestCase;

class BranchScopingTest extends TestCase
{
    public function test_branch_manager_cannot_access_other_branch_orders(): void
    {
        // Create order in main branch
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
        ]);

        // Create order in other branch
        $otherCustomer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $otherOrder = Order::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'customer_id' => $otherCustomer->id,
        ]);

        // Act as branch manager of main branch
        $this->actingAsRole('branch_manager', $this->branch);

        // Should see own branch order
        $this->get(route('orders.show', $order))
            ->assertStatus(200);

        // Should NOT see other branch order
        // Returns 404 because BranchScoped scope filters out records from other branches
        $this->get(route('orders.show', $otherOrder))
            ->assertStatus(404);
    }

    public function test_branch_manager_cannot_access_other_branch_inventory_items(): void
    {
        // Create inventory category and item in main branch
        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);

        // Create inventory in other branch
        $otherCategory = InventoryCategory::factory()->create(['branch_id' => $this->otherBranch->id]);
        $otherItem = InventoryItem::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'inventory_category_id' => $otherCategory->id,
        ]);

        // Act as branch manager of main branch
        $user = $this->actingAsRole('branch_manager', $this->branch);

        // Query should only return main branch items
        $items = InventoryItem::all();
        $this->assertTrue($items->contains($item));
        $this->assertFalse($items->contains($otherItem));
    }

    public function test_branch_manager_cannot_access_other_branch_expenses(): void
    {
        // Create expense in main branch
        $category = ExpenseCategory::factory()->create(['branch_id' => $this->branch->id]);
        $expense = Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'expense_category_id' => $category->id,
        ]);

        // Create expense in other branch
        $otherCategory = ExpenseCategory::factory()->create(['branch_id' => $this->otherBranch->id]);
        $otherExpense = Expense::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'expense_category_id' => $otherCategory->id,
        ]);

        // Act as branch manager of main branch
        $this->actingAsRole('branch_manager', $this->branch);

        // Should see own branch expense
        $this->get(route('expenses.show', $expense))
            ->assertStatus(200);

        // Should NOT see other branch expense
        // Returns 404 because BranchScoped scope filters out records from other branches
        $this->get(route('expenses.show', $otherExpense))
            ->assertStatus(404);
    }

    public function test_admin_switching_branch_sees_different_data(): void
    {
        // Create orders in both branches
        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order1 = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer1->id,
        ]);

        $customer2 = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $order2 = Order::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'customer_id' => $customer2->id,
        ]);

        // Act as admin
        $admin = $this->actingAsRole('admin', $this->branch);

        // Set context to main branch
        BranchContext::setActiveBranch($this->branch->id);
        $ordersMainBranch = Order::all();
        $this->assertTrue($ordersMainBranch->contains($order1));
        $this->assertFalse($ordersMainBranch->contains($order2));

        // Switch to other branch
        BranchContext::setActiveBranch($this->otherBranch->id);
        $ordersOtherBranch = Order::all();
        $this->assertFalse($ordersOtherBranch->contains($order1));
        $this->assertTrue($ordersOtherBranch->contains($order2));
    }

    public function test_global_admin_can_access_any_branch_after_context_switch(): void
    {
        // Create order in other branch
        $customer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $order = Order::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'customer_id' => $customer->id,
        ]);

        // Act as admin (no branch required)
        $admin = $this->createUserWithRole('admin');
        $this->actingAs($admin);

        // Set context to other branch
        BranchContext::setActiveBranch($this->otherBranch->id);

        // Should be able to access the order
        $this->get(route('orders.show', $order))
            ->assertStatus(200);
    }
}
