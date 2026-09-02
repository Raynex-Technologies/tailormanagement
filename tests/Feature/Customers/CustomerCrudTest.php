<?php

namespace Tests\Feature\Customers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Customers\Index;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    public function test_users_with_customers_view_permission_can_access_customers_page(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Customers');
    }

    public function test_users_without_customers_view_permission_cannot_access_customers_page(): void
    {
        $this->actingAsRole('sales', $this->branch);

        $this->get(route('customers.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_can_create_edit_and_delete_customer(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Alice Mushi')
            ->set('phone', '+255700111222')
            ->set('email', 'alice@example.test')
            ->set('address', 'Mbezi, Dar es Salaam')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::query()->where('email', 'alice@example.test')->first();

        $this->assertNotNull($customer);
        $this->assertEquals($this->branch->id, $customer->branch_id);

        Livewire::test(Index::class)
            ->call('openEditModal', $customer->id)
            ->set('name', 'Alice Updated')
            ->set('phone', '+255700333444')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Alice Updated',
            'phone' => '+255700333444',
        ]);

        Livewire::test(Index::class)
            ->call('delete', $customer->id);

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_customer_phone_must_be_unique_within_the_same_branch(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Existing Customer',
            'phone' => '+255700999111',
        ]);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Duplicate Phone Customer')
            ->set('phone', '+255700999111')
            ->call('save')
            ->assertHasErrors(['phone' => 'unique']);
    }

    public function test_customer_phone_can_be_reused_in_a_different_branch(): void
    {
        $phone = '+255700999222';

        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Branch One Customer')
            ->set('phone', $phone)
            ->call('save')
            ->assertHasNoErrors();

        $this->actingAsRole('branch_manager', $this->otherBranch);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Branch Two Customer')
            ->set('phone', $phone)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'branch_id' => $this->branch->id,
            'phone' => $phone,
        ]);

        $this->assertDatabaseHas('customers', [
            'branch_id' => $this->otherBranch->id,
            'phone' => $phone,
        ]);
    }

    public function test_database_rejects_duplicate_customer_phone_in_same_branch(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'First Customer',
            'phone' => '+255700999333',
        ]);

        $this->expectException(QueryException::class);

        Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Second Customer',
            'phone' => '+255700999333',
        ]);
    }

    public function test_cannot_delete_customer_with_existing_orders(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 10000,
            'discount' => 0,
            'total' => 10000,
            'created_by' => $user->id,
        ]);

        Livewire::test(Index::class)
            ->call('delete', $customer->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_customer_show_displays_order_history(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'History Customer']);

        $orderA = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 35000,
            'discount' => 0,
            'total' => 35000,
            'created_by' => $user->id,
            'order_date' => now()->toDateString(),
        ]);

        $orderB = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'created_by' => $user->id,
            'order_date' => now()->subDay()->toDateString(),
        ]);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('History Customer')
            ->assertSee('Order History')
            ->assertSee($orderA->order_no)
            ->assertSee($orderB->order_no);
    }

    public function test_branch_manager_cannot_view_customer_from_other_branch(): void
    {
        $otherBranchCustomer = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);

        $this->actingAsRole('branch_manager', $this->branch);

        $this->get(route('customers.show', $otherBranchCustomer))
            ->assertNotFound();
    }
    public function test_customer_create_visibility_and_server_authorization_are_permission_based(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $user->givePermissionTo('customers.view');

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('New Customer');

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();

        $user->givePermissionTo('customers.create');

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('New Customer');
    }

    public function test_customer_update_permission_is_independent_from_view_and_create(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $user->givePermissionTo(['customers.view', 'customers.create']);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(Index::class)
            ->call('openEditModal', $customer->id)
            ->assertForbidden();

        $user->givePermissionTo('customers.update');

        Livewire::test(Index::class)
            ->call('openEditModal', $customer->id)
            ->assertSet('editingId', $customer->id);
    }

    public function test_order_customer_lookup_does_not_require_customer_module_permissions(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->removeRole('branch_manager');
        $user->assignRole('customer');
        $user->givePermissionTo(['orders.view', 'orders.create']);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Lookup Only Customer',
        ]);

        Livewire::test(\App\Livewire\Orders\Form::class)
            ->set('customerSearch', 'Lookup Only')
            ->assertSee($customer->name)
            ->call('openNewCustomerModal')
            ->assertForbidden();
    }

    public function test_customer_permissions_are_grouped_with_friendly_labels_and_not_granted_to_accountant(): void
    {
        $grouped = \App\Support\PermissionGroups::groupPermissions(
            \Spatie\Permission\Models\Permission::query()->orderBy('name')->get()
        );

        $this->assertSame(
            ['customers.create', 'customers.delete', 'customers.update', 'customers.view'],
            $grouped['Customers']->pluck('name')->sort()->values()->all()
        );
        $this->assertSame('View Customers', \App\Support\PermissionGroups::displayName('customers.view'));
        $this->assertSame('Create Customers', \App\Support\PermissionGroups::displayName('customers.create'));
        $this->assertSame('Edit Customers', \App\Support\PermissionGroups::displayName('customers.update'));

        $accountant = \Spatie\Permission\Models\Role::findByName('accountant');
        $this->assertFalse($accountant->hasAnyPermission([
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
        ]));
    }
}
