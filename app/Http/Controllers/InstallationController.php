<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\EnvironmentWriter;
use App\Support\InstallationState;
use Database\Seeders\InventoryCategoriesAndItemsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InstallationController extends Controller
{
    public function __construct(
        protected InstallationState $installationState,
        protected EnvironmentWriter $environmentWriter,
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->installationState->isInstalled()) {
            return redirect()->route('home');
        }

        return view('install.wizard', [
            'defaults' => $this->defaults($request),
            'connections' => $this->connections(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->installationState->isInstalled()) {
            return redirect()->route('home');
        }

        $data = $this->validateRequest($request);

        try {
            $this->installationState->bootstrapPreInstallRuntime();
            $this->environmentWriter->assertRootEnvironmentFile();
            app()->loadEnvironmentFrom(basename($this->environmentWriter->path()));

            $this->writeEnvironment($data);

            Artisan::call('optimize:clear');

            $this->configureRuntimeDatabase($data);
            $this->assertDatabaseConnection();

            Artisan::call('key:generate', ['--force' => true]);

            config([
                'app.key' => $this->environmentWriter->get('APP_KEY'),
                'app.url' => $data['app_url'],
            ]);

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

            $this->persistWizardData($data);

            Artisan::call('db:seed', ['--class' => InventoryCategoriesAndItemsSeeder::class, '--force' => true]);

            $this->installationState->markAsInstalled();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']))
                ->withErrors([
                    'installation' => $exception->getMessage() ?: 'Installation failed. Review the submitted values and try again.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Installation complete. Sign in with the superadmin account you just created.');
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'db_connection' => ['required', Rule::in(array_keys($this->connections()))],
            'db_host' => [
                Rule::requiredIf(fn () => $request->string('db_connection')->toString() !== 'sqlite'),
                'nullable',
                'string',
                'max:255',
            ],
            'db_port' => [
                Rule::requiredIf(fn () => $request->string('db_connection')->toString() !== 'sqlite'),
                'nullable',
                'integer',
                'between:1,65535',
            ],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => [
                Rule::requiredIf(fn () => $request->string('db_connection')->toString() !== 'sqlite'),
                'nullable',
                'string',
                'max:255',
            ],
            'db_password' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'business_name' => ['required', 'string', 'max:191'],
            'business_phone' => ['required', 'string', 'max:50'],
            'business_tin' => ['nullable', 'string', 'max:100'],
            'business_email' => ['nullable', 'email', 'max:191'],
            'branch_name' => ['required', 'string', 'max:191'],
            'branch_phone' => ['nullable', 'string', 'max:50'],
            'branch_address' => ['nullable', 'string', 'max:1000'],
            'admin_name' => ['required', 'string', 'max:191'],
            'admin_email' => ['required', 'email', 'max:191'],
            'admin_password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);
    }

    protected function defaults(Request $request): array
    {
        return [
            'db_connection' => old('db_connection', config('database.default', 'mysql')),
            'db_host' => old('db_host', config('database.connections.mysql.host', '127.0.0.1')),
            'db_port' => old('db_port', config('database.connections.mysql.port', '3306')),
            'db_database' => old('db_database', config('database.connections.mysql.database', '')),
            'db_username' => old('db_username', config('database.connections.mysql.username', '')),
            'app_url' => old('app_url', $request->root()),
            'business_name' => old('business_name', config('app.name', 'Tailor Management')),
            'business_phone' => old('business_phone', ''),
            'business_tin' => old('business_tin', ''),
            'business_email' => old('business_email', ''),
            'branch_name' => old('branch_name', 'Main Branch'),
            'branch_phone' => old('branch_phone', ''),
            'branch_address' => old('branch_address', ''),
            'admin_name' => old('admin_name', 'Super Admin'),
            'admin_email' => old('admin_email', ''),
        ];
    }

    protected function connections(): array
    {
        return [
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ];
    }

    protected function writeEnvironment(array $data): void
    {
        $values = [
            'APP_NAME' => $data['business_name'],
            'APP_URL' => $data['app_url'],
            'DB_CONNECTION' => $data['db_connection'],
            'DB_DATABASE' => $data['db_database'],
        ];

        if ($data['db_connection'] === 'sqlite') {
            $values['DB_HOST'] = null;
            $values['DB_PORT'] = null;
            $values['DB_USERNAME'] = null;
            $values['DB_PASSWORD'] = null;
        } else {
            $values['DB_HOST'] = $data['db_host'];
            $values['DB_PORT'] = $data['db_port'];
            $values['DB_USERNAME'] = $data['db_username'];
            $values['DB_PASSWORD'] = $data['db_password'] ?? '';
        }

        $this->environmentWriter->write($values);
    }

    protected function configureRuntimeDatabase(array $data): void
    {
        $connection = $data['db_connection'];
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            throw new RuntimeException("Unsupported database connection [{$connection}].");
        }

        if ($connection === 'sqlite') {
            $config['database'] = $data['db_database'];
        } else {
            $config['host'] = $data['db_host'];
            $config['port'] = (string) $data['db_port'];
            $config['database'] = $data['db_database'];
            $config['username'] = $data['db_username'];
            $config['password'] = $data['db_password'] ?? '';
        }

        config([
            'database.default' => $connection,
            "database.connections.{$connection}" => $config,
        ]);

        DB::purge($connection);
    }

    protected function assertDatabaseConnection(): void
    {
        try {
            DB::connection(config('database.default'))->getPdo();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'db_database' => 'Unable to connect with the supplied database settings: '.$exception->getMessage(),
            ]);
        }
    }

    protected function persistWizardData(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $branch = Branch::query()->first();

            if ($branch) {
                $branch->update([
                    'name' => $data['branch_name'],
                    'phone' => $data['branch_phone'] ?: null,
                    'address' => $data['branch_address'] ?: null,
                    'is_active' => true,
                ]);
            } else {
                $branch = Branch::query()->create([
                    'name' => $data['branch_name'],
                    'phone' => $data['branch_phone'] ?: null,
                    'address' => $data['branch_address'] ?: null,
                    'is_active' => true,
                ]);
            }

            BusinessSetting::query()->updateOrCreate(
                ['id' => 1],
                [
                    'business_name' => $data['business_name'],
                    'phone' => $data['business_phone'],
                    'email' => $data['business_email'] ?: null,
                    'tin_number' => $data['business_tin'] ?: null,
                    'email_from_name' => $data['business_name'],
                    'email_from_address' => $data['business_email'] ?: null,
                ]
            );

            $user = User::query()->firstOrNew(['email' => $data['admin_email']]);
            $user->fill([
                'name' => $data['admin_name'],
                'password' => Hash::make($data['admin_password']),
                'branch_id' => null,
            ]);
            $user->email_verified_at ??= now();
            $user->save();

            if (! $user->hasRole('superadmin')) {
                $user->assignRole('superadmin');
            }
        });
    }
}
