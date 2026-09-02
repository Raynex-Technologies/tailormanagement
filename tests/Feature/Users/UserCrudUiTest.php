<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserCrudUiTest extends TestCase
{
    public function test_authorized_user_can_view_index_and_create_action_respects_permission(): void
    {
        $this->actingAsRole('branch_manager');

        Livewire::test(Index::class)
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('New User');

        $viewer = User::factory()->forBranch($this->branch)->create();
        $viewer->givePermissionTo(Permission::findByName('users.view'));
        $this->actingAs($viewer);

        Livewire::test(Index::class)
            ->assertOk()
            ->assertDontSee('New User');
    }

    public function test_search_and_role_filter_remain_functional(): void
    {
        $this->actingAsRole('branch_manager');
        $sales = $this->createUserWithRole('sales');
        $sales->update(['name' => 'Simon Mwakyoma', 'email' => 'simon@example.test']);
        $this->createUserWithRole('tailor')->update(['name' => 'Different Person']);

        Livewire::test(Index::class)
            ->set('search', 'simon@example.test')
            ->assertSee('Simon Mwakyoma')
            ->assertDontSee('Different Person')
            ->set('search', '')
            ->set('roleFilter', 'sales')
            ->assertSee('Simon Mwakyoma')
            ->assertDontSee('Different Person');
    }

    public function test_accessible_users_and_table_structure_render_with_no_filters(): void
    {
        $this->actingAsRole('branch_manager');
        $first = $this->createUserWithRole('sales');
        $second = $this->createUserWithRole('tailor');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($first->email)
            ->assertSee($second->name)
            ->assertSee($second->email)
            ->assertSee('data-user-list', false)
            ->assertSee('data-user-desktop-list', false)
            ->assertSee('data-user-mobile-list', false)
            ->assertSeeTextInOrder(['User', 'Role', 'Access Scope', 'Created', 'Actions'])
            ->assertDontSee('hidden overflow-x-auto md:block', false);
    }

    public function test_kpis_use_canonical_unambiguous_role_metrics(): void
    {
        $this->actingAsRole('branch_manager');
        $this->createUserWithRole('sales');
        $this->createUserWithRole('tailor');
        User::factory()->forBranch($this->branch)->create();

        Livewire::test(Index::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['roles_in_use'] === 3
                && $stats['without_roles'] === 1
                && $stats['with_roles'] === 3)
            ->assertSee('Roles in Use')
            ->assertSee('Users with Roles')
            ->assertSee('Users without Roles')
            ->assertDontSee('Global Accounts')
            ->assertDontSee('Roles Assigned');
    }

    public function test_filter_panel_wrapper_is_fully_collapsible(): void
    {
        $this->actingAsRole('branch_manager');

        Livewire::test(Index::class)
            ->assertSee('filtersOpen: false', false)
            ->assertSee('x-show="filtersOpen"', false)
            ->assertSee('x-collapse', false)
            ->assertSee('x-cloak', false)
            ->assertSee('data-user-filter-panel', false)
            ->set('search', 'active filter')
            ->assertSee('filtersOpen: true', false);
    }

    public function test_branch_scope_and_pagination_are_preserved(): void
    {
        $this->actingAsRole('branch_manager');
        User::factory()->count(16)->forBranch($this->branch)->create();
        $other = $this->createUserWithRole('sales', $this->otherBranch);

        Livewire::test(Index::class)
            ->set('perPage', 10)
            ->assertSet('paginators.page', 1)
            ->assertDontSee($other->email)
            ->call('nextPage')
            ->assertSet('paginators.page', 2);
    }

    public function test_creation_assigns_the_canonical_role_and_branch(): void
    {
        $this->actingAsRole('branch_manager');

        Livewire::test(Form::class)
            ->set('name', 'New Tailor')
            ->set('email', 'tailor@example.test')
            ->set('role', 'tailor')
            ->set('password', 'StrongPass1')
            ->set('password_confirmation', 'StrongPass1')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'tailor@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('tailor'));
        $this->assertSame($this->branch->id, $user->branch_id);
    }

    public function test_profile_edit_does_not_require_or_replace_password(): void
    {
        $this->actingAsRole('branch_manager');
        $target = $this->createUserWithRole('sales');
        $originalHash = $target->password;

        Livewire::test(Form::class, ['user' => $target])
            ->set('name', 'Updated Staff Name')
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertSame('Updated Staff Name', $target->name);
        $this->assertSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('password', $target->password));
    }

    public function test_password_is_never_rendered(): void
    {
        $this->actingAsRole('branch_manager');
        $target = $this->createUserWithRole('sales');

        Livewire::test(Form::class, ['user' => $target])
            ->assertSet('password', '')
            ->assertDontSee($target->password);
    }

    public function test_edit_action_and_super_admin_account_respect_policy(): void
    {
        $this->actingAsRole('branch_manager');
        $editable = $this->createUserWithRole('sales');
        $protected = User::factory()->create();
        $protected->assignRole('superadmin');

        Livewire::test(Index::class)
            ->assertSee(route('users.edit', $editable), false)
            ->assertDontSee(route('users.edit', $protected), false);

        $this->get(route('users.edit', $protected))->assertForbidden();
    }
}
