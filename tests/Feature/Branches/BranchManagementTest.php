<?php

namespace Tests\Feature\Branches;

use App\Livewire\Branches\Index;
use App\Models\Branch;
use Livewire\Livewire;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    public function test_admin_can_create_and_update_branches(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Kilimani Branch')
            ->set('code', 'BR-KILI-01')
            ->set('phone', '+255700000111')
            ->set('address', 'Kilimani Road')
            ->call('save')
            ->assertHasNoErrors();

        $branch = Branch::query()->where('code', 'BR-KILI-01')->firstOrFail();

        Livewire::test(Index::class)
            ->call('openEditModal', $branch->id)
            ->set('name', 'Kilimani Main Branch')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Kilimani Main Branch',
            'is_active' => false,
        ]);
    }

    public function test_branch_manager_only_sees_their_own_branch(): void
    {
        $this->actingAsRole('branch_manager');

        Livewire::test(Index::class)
            ->assertSee($this->branch->name)
            ->assertDontSee($this->otherBranch->name);
    }

    public function test_superadmin_must_confirm_password_before_delete_branch(): void
    {
        $this->actingAsRole('superadmin');

        Livewire::test(Index::class)
            ->call('openDeletePasswordModal', $this->otherBranch->id)
            ->set('deletePassword', 'wrong-password')
            ->call('verifyDeletePassword')
            ->assertHasErrors(['deletePassword']);

        $this->assertDatabaseHas('branches', [
            'id' => $this->otherBranch->id,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_cannot_delete_branch_when_non_soft_deletable_records_exist(): void
    {
        $this->actingAsRole('superadmin');
        $this->createUserWithRole('sales', $this->otherBranch);

        Livewire::test(Index::class)
            ->call('openDeletePasswordModal', $this->otherBranch->id)
            ->set('deletePassword', 'password')
            ->call('verifyDeletePassword')
            ->assertSet('showDeleteConfirmModal', true)
            ->assertSet('deleteBlockers.Users', 1)
            ->call('deleteBranch');

        $this->assertDatabaseHas('branches', [
            'id' => $this->otherBranch->id,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_archive_empty_branch_after_confirming_password(): void
    {
        $this->actingAsRole('superadmin');

        $branch = Branch::factory()->create([
            'name' => 'Temporary Branch',
            'code' => 'BR-TEMP-01',
        ]);

        Livewire::test(Index::class)
            ->call('openDeletePasswordModal', $branch->id)
            ->set('deletePassword', 'password')
            ->call('verifyDeletePassword')
            ->assertSet('showDeleteConfirmModal', true)
            ->call('deleteBranch')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'is_active' => false,
        ]);
    }
}
