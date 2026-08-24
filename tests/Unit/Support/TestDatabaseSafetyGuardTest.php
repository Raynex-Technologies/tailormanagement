<?php

namespace Tests\Unit\Support;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafetyGuard;

class TestDatabaseSafetyGuardTest extends TestCase
{
    public function test_it_accepts_the_effective_database_resolved_by_laravel(): void
    {
        $app = $this->applicationWithDatabaseConfiguration('sqlite', 'sqlite', ':memory:');

        TestDatabaseSafetyGuard::assertApplicationIsSafe($app);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_an_unsafe_database_resolved_by_laravel_without_connecting(): void
    {
        $app = $this->applicationWithDatabaseConfiguration('mysql', 'mysql', 'tailor2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Refusing to run tests: active database 'tailor2'");

        TestDatabaseSafetyGuard::assertApplicationIsSafe($app);
    }

    #[DataProvider('approvedDatabaseConfigurations')]
    public function test_it_accepts_only_explicitly_safe_database_configurations(
        string $driver,
        string $database,
        string $connection,
    ): void {
        TestDatabaseSafetyGuard::assertSafeConfiguration($driver, $database, $connection);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function approvedDatabaseConfigurations(): array
    {
        return [
            'SQLite in memory' => ['sqlite', ':memory:', 'sqlite'],
            'dedicated MySQL test database' => ['mysql', 'tailormanagement_test', 'mysql'],
            'dedicated MySQL testing database' => ['mysql', 'something_testing', 'mysql'],
            'test-specific SQLite file' => ['sqlite', 'storage/testing/orders_test.sqlite', 'sqlite'],
        ];
    }

    #[DataProvider('unsafeDatabaseConfigurations')]
    public function test_it_rejects_unsafe_or_ambiguous_database_configurations(
        string $driver,
        ?string $database,
        string $connection,
        string $expectedDatabaseLabel,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Refusing to run tests: active database '{$expectedDatabaseLabel}'");

        TestDatabaseSafetyGuard::assertSafeConfiguration($driver, $database, $connection);
    }

    /**
     * @return array<string, array{string, string|null, string, string}>
     */
    public static function unsafeDatabaseConfigurations(): array
    {
        return [
            'known development database' => ['mysql', 'tailor2', 'mysql', 'tailor2'],
            'ordinary application database' => ['mysql', 'tailormanagement', 'mysql', 'tailormanagement'],
            'missing database' => ['mysql', null, 'mysql', '[missing]'],
            'missing driver' => ['', 'tailormanagement_test', 'mysql', 'tailormanagement_test'],
            'missing connection' => ['mysql', 'tailormanagement_test', '', 'tailormanagement_test'],
            'normal application SQLite file' => ['sqlite', 'database/database.sqlite', 'sqlite', 'database/database.sqlite'],
            'unsupported driver' => ['pgsql', 'tailormanagement_test', 'pgsql', 'tailormanagement_test'],
        ];
    }

    private function applicationWithDatabaseConfiguration(
        string $connection,
        string $driver,
        string $database,
    ): Application {
        $app = new Application(__DIR__);
        $app->instance('config', new Repository([
            'database' => [
                'default' => $connection,
                'connections' => [
                    $connection => [
                        'driver' => $driver,
                        'database' => $database,
                    ],
                ],
            ],
        ]));

        return $app;
    }
}
