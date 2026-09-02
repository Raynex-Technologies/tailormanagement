<?php

namespace Tests\Feature\Roles;

use App\Livewire\Roles\Form;
use App\Livewire\Roles\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionsUiTest extends TestCase
{
    public function test_index_uses_count_only_role_cards_without_nested_permission_reference(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $role = Role::findByName('accountant');

        $response = $this->get(route('access-control.roles.index'));

        $response->assertOk()
            ->assertSee('data-role-grid', false)
            ->assertSee('data-role-card', false)
            ->assertSee((string) $role->users()->count())
            ->assertSee((string) $role->permissions()->count())
            ->assertDontSee('Permissions by Module')
            ->assertDontSee('orders.view')
            ->assertDontSee('Ã¢', false);
    }

    public function test_add_role_requires_role_management_permission(): void
    {
        $this->actingAsRole('sales', $this->branch);

        $this->get(route('access-control.roles.create'))->assertForbidden();
    }

    public function test_superadmin_is_marked_protected_and_cannot_be_deleted(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $superadmin = Role::findByName('superadmin');

        $this->get(route('access-control.roles.index'))
            ->assertSee('Protected')
            ->assertDontSee('deleteRole('.$superadmin->id.')', false);

        Livewire::test(Index::class)
            ->call('deleteRole', $superadmin->id)
            ->assertForbidden();
    }

    public function test_create_and_edit_render_grouped_friendly_customer_permissions(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $accountant = Role::findByName('accountant');

        foreach ([
            route('access-control.roles.create'),
            route('access-control.roles.edit', $accountant),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-permission-editor', false)
                ->assertSee('data-permission-group="Customers"', false)
                ->assertSee('View Customers')
                ->assertSee('Create Customers')
                ->assertSee('Edit Customers')
                ->assertSee('Delete Customers')
                ->assertSee('Search permissions...')
                ->assertDontSee('customers.create');
        }
    }

    public function test_group_select_all_and_clear_only_change_that_group(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $customerIds = Permission::query()->where('name', 'like', 'customers.%')->pluck('id');
        $ordersView = Permission::findByName('orders.view');

        $component = Livewire::test(Form::class)
            ->set('permissions.'.$ordersView->id, true)
            ->call('selectModule', 'Customers');

        foreach ($customerIds as $id) {
            $component->assertSet('permissions.'.$id, true);
        }

        $component->call('clearModule', 'Customers')
            ->assertSet('permissions.'.$ordersView->id, true);

        foreach ($customerIds as $id) {
            $component->assertSet('permissions.'.$id, false);
        }
    }

    public function test_permission_search_is_local_and_does_not_bind_to_permission_selection(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $this->get(route('access-control.roles.create'))
            ->assertSee('x-model="permissionSearch"', false)
            ->assertDontSee('wire:model="permissionSearch"', false);
    }
}
