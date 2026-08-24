<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestDatabaseSafetyGuard
{
    public static function assertApplicationIsSafe(Application $app): void
    {
        $connection = trim((string) $app['config']->get('database.default'));
        $driver = trim(strtolower((string) $app['config']->get("database.connections.{$connection}.driver")));
        $database = $app['config']->get("database.connections.{$connection}.database");

        self::assertSafeConfiguration($driver, is_string($database) ? $database : null, $connection);
    }

    public static function assertSafeConfiguration(string $driver, ?string $database, string $connection = ''): void
    {
        $driver = trim(strtolower($driver));
        $database = is_string($database) ? trim($database) : '';
        $connection = trim($connection);

        if ($connection === '' || $driver === '' || $database === '') {
            throw self::unsafeDatabaseException($database, $driver, $connection);
        }

        if ($driver === 'sqlite' && self::isApprovedSqliteDatabase($database)) {
            return;
        }

        if ($driver === 'mysql' && preg_match('/_(?:test|testing)$/i', $database) === 1) {
            return;
        }

        throw self::unsafeDatabaseException($database, $driver, $connection);
    }

    private static function isApprovedSqliteDatabase(string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        $filename = strtolower(basename(str_replace('\\', '/', $database)));

        return preg_match('/^(?:test|testing|.+_(?:test|testing))\.(?:sqlite|sqlite3|db)$/', $filename) === 1;
    }

    private static function unsafeDatabaseException(string $database, string $driver, string $connection): RuntimeException
    {
        $databaseLabel = $database !== '' ? $database : '[missing]';
        $driverLabel = $driver !== '' ? $driver : '[missing]';
        $connectionLabel = $connection !== '' ? $connection : '[missing]';

        return new RuntimeException(
            "Refusing to run tests: active database '{$databaseLabel}' on connection "
            ."'{$connectionLabel}' using driver '{$driverLabel}' is not an approved test database. "
            ."Use SQLite ':memory:', a clearly test-specific SQLite file, or a MySQL database ending in '_test' or '_testing'."
        );
    }
}
