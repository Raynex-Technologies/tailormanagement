<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestDatabaseSafetyGuardIntegrationTest extends TestCase
{
    public function test_refresh_database_executes_only_with_the_isolated_sqlite_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(app()->configurationIsCached());
        $this->assertDatabaseHas('branches', ['id' => $this->branch->id]);
    }
}
