<?php

namespace Tests\Feature\Installation;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallationWizardTest extends TestCase
{
    protected string $installLockFile;

    protected string $installEnvFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installLockFile = storage_path('framework/testing/install.lock');
        $this->installEnvFile = base_path('.env.install.testing');

        File::delete($this->installLockFile);
        File::delete($this->installEnvFile);
        File::copy(base_path('.env.example'), $this->installEnvFile);

        config([
            'install.skip_during_tests' => false,
            'install.lock_file' => $this->installLockFile,
            'install.env_file' => $this->installEnvFile,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->installLockFile);
        File::delete($this->installEnvFile);

        parent::tearDown();
    }

    public function test_public_routes_redirect_to_the_installer_when_app_is_not_installed(): void
    {
        $this->get('/')
            ->assertRedirect(route('install.index'));
    }

    public function test_installer_bootstraps_the_application_with_submitted_values(): void
    {
        $response = $this->post(route('install.store'), [
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
            'app_url' => 'http://tailor.test',
            'business_name' => 'Needle & Thread',
            'business_phone' => '+255700000111',
            'business_tin' => 'TIN-12345',
            'business_email' => 'owner@needle.test',
            'branch_name' => 'Flagship Branch',
            'branch_phone' => '+255700000222',
            'branch_address' => 'Samora Avenue',
            'admin_name' => 'System Owner',
            'admin_email' => 'admin@needle.test',
            'admin_password' => 'AdminPass123',
            'admin_password_confirmation' => 'AdminPass123',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('business_settings', [
            'id' => 1,
            'business_name' => 'Needle & Thread',
            'phone' => '+255700000111',
            'tin_number' => 'TIN-12345',
            'email' => 'owner@needle.test',
        ]);

        $this->assertDatabaseHas('branches', [
            'name' => 'Flagship Branch',
            'phone' => '+255700000222',
            'address' => 'Samora Avenue',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'dashboard.view',
        ]);

        $branch = Branch::query()->firstOrFail();

        $this->assertDatabaseHas('inventory_categories', [
            'branch_id' => $branch->id,
            'slug' => 'fabrics',
        ]);

        $user = User::query()->where('email', 'admin@needle.test')->firstOrFail();

        $this->assertTrue($user->hasRole('superadmin'));
        $this->assertTrue(File::exists($this->installLockFile));
        $this->assertStringContainsString('APP_URL=http://tailor.test', File::get($this->installEnvFile));
    }
}
