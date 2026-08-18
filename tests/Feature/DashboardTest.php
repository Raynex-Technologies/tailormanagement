<?php

namespace Tests\Feature;

use App\Livewire\Roles\Form as RoleForm;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->forBranch($branch)->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_cards_are_independently_permission_controlled(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->forBranch($branch)->create();
        $user->givePermissionTo([
            Permission::findByName('dashboard.view'),
            Permission::findByName('dashboard.kpi.orders.view'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('New Orders');
        $response->assertDontSeeText('Revenue This Month');
        $response->assertDontSeeText('Expenses This Month');
        $response->assertDontSeeText('Top Customers');
    }

    public function test_dashboard_child_permissions_are_hidden_until_dashboard_view_is_selected(): void
    {
        $user = User::factory()->create();
        $rolesManage = Permission::findByName('roles.manage');
        $dashboardView = Permission::findByName('dashboard.view');
        $user->givePermissionTo($rolesManage);

        Livewire::actingAs($user)
            ->test(RoleForm::class)
            ->assertSee('dashboard.view')
            ->assertDontSee('dashboard.kpi.orders.view')
            ->set("permissions.{$dashboardView->id}", true)
            ->assertSee('dashboard.kpi.orders.view');
    }
}
