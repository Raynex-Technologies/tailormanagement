<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\User;
use App\Support\BranchContext;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    public function test_branch_manager_can_assign_a_task_to_staff(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        Livewire::test(TasksIndex::class)
            ->set('title', 'Finish wedding fitting')
            ->set('priority', 'high')
            ->set('assigneeId', $tailor->id)
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('todos', [
            'title' => 'Finish wedding fitting',
            'user_id' => $tailor->id,
            'assigned_by' => $manager->id,
        ]);

        $this->actingAs($tailor);
        BranchContext::setActiveBranch($this->branch->id);

        Livewire::test(TasksIndex::class)
            ->assertSee('Finish wedding fitting');
    }

    public function test_non_assigners_cannot_assign_tasks_to_other_staff(): void
    {
        $tailor = $this->actingAsRole('tailor', $this->branch);
        $sales = $this->createUserWithRole('sales', $this->branch);

        Livewire::test(TasksIndex::class)
            ->set('title', 'Unauthorized assignment')
            ->set('assigneeId', $sales->id)
            ->call('saveTask')
            ->assertHasErrors(['assigneeId']);

        $this->assertDatabaseMissing('todos', [
            'title' => 'Unauthorized assignment',
            'user_id' => $sales->id,
        ]);

        $this->assertDatabaseMissing('todos', [
            'title' => 'Unauthorized assignment',
            'user_id' => $tailor->id,
        ]);
    }

    public function test_manager_and_superadmin_have_task_assignment_permission_by_default(): void
    {
        $branchManager = $this->createUserWithRole('branch_manager', $this->branch);
        $superadmin = $this->createUserWithRole('superadmin', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        $this->assertTrue($branchManager->can('todos.assign'));
        $this->assertTrue($superadmin->can('todos.assign'));
        $this->assertFalse($tailor->can('todos.assign'));
    }

    public function test_branch_manager_can_assign_to_custom_non_manager_roles(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $customRole = Role::findOrCreate('finisher');

        $customStaff = User::factory()
            ->forBranch($this->branch)
            ->create();
        $customStaff->assignRole($customRole);

        Livewire::test(TasksIndex::class)
            ->set('title', 'Custom role assignment')
            ->set('assigneeId', $customStaff->id)
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('todos', [
            'title' => 'Custom role assignment',
            'user_id' => $customStaff->id,
            'assigned_by' => $manager->id,
        ]);
    }

    public function test_branch_manager_cannot_assign_to_another_branch_manager(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $otherManager = $this->createUserWithRole('branch_manager', $this->branch);

        Livewire::test(TasksIndex::class)
            ->set('title', 'Manager to manager assignment')
            ->set('assigneeId', $otherManager->id)
            ->call('saveTask')
            ->assertHasErrors(['assigneeId']);

        $this->assertDatabaseMissing('todos', [
            'title' => 'Manager to manager assignment',
            'user_id' => $otherManager->id,
        ]);
    }

    public function test_assigner_can_track_progress_updates_for_assigned_tasks(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        $todo = \App\Models\Todo::create([
            'branch_id' => $this->branch->id,
            'user_id' => $tailor->id,
            'assigned_by' => $manager->id,
            'title' => 'Track sleeve stitching',
            'priority' => 'normal',
            'is_done' => false,
        ]);

        Livewire::test(TasksIndex::class)
            ->assertSee('Assigned Tasks Progress')
            ->assertSee('Track sleeve stitching')
            ->assertSee($tailor->name)
            ->assertSee('In Progress');

        $todo->markAsDone();

        Livewire::test(TasksIndex::class)
            ->assertSee('Track sleeve stitching')
            ->assertSee('Completed');
    }
}
