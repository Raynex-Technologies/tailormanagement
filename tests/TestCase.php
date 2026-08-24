<?php

namespace Tests;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestDatabaseSafetyGuard;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Branch $otherBranch;

    /**
     * Boot Laravel and reject unsafe database configuration before test traits run.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        TestDatabaseSafetyGuard::assertApplicationIsSafe($app);

        return $app;
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create test branches
        $this->branch = Branch::factory()->create(['name' => 'Main Branch']);
        $this->otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
    }

    /**
     * Create and authenticate a user with the given role.
     */
    protected function actingAsRole(string $role, ?Branch $branch = null): User
    {
        $branch = $branch ?? $this->branch;

        $user = User::factory()
            ->forBranch($branch)
            ->create();

        $user->assignRole($role);

        // Set branch context
        BranchContext::setActiveBranch($branch->id);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Create a user with the given role without authenticating.
     */
    protected function createUserWithRole(string $role, ?Branch $branch = null): User
    {
        $branch = $branch ?? $this->branch;

        $user = User::factory()
            ->forBranch($branch)
            ->create();

        $user->assignRole($role);

        return $user;
    }

    /**
     * Set the active branch context for testing.
     */
    protected function setBranchContext(?Branch $branch = null): void
    {
        $branch = $branch ?? $this->branch;
        BranchContext::setActiveBranch($branch->id);
    }

    /**
     * Clear branch context.
     */
    protected function clearBranchContext(): void
    {
        BranchContext::clearActiveBranch();
    }
}
