<?php

namespace Tests\Feature\Settings;

use App\Models\SmsLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmsLogSoftDeleteMigrationTest extends TestCase
{
    public function test_existing_column_and_deleted_log_are_preserved_on_repeated_runs(): void
    {
        $log = SmsLog::create([
            'branch_id' => $this->branch->id, 'provider' => 'beem',
            'to' => '+255712345678', 'message' => 'Preserve log', 'status' => 'failed',
        ]);
        $log->delete();
        $before = $log->fresh()->getRawOriginal('deleted_at');
        $migration = require database_path('migrations/2026_09_12_000001_add_soft_deletes_to_sms_logs_table.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('sms_logs', ['id' => $log->id, 'deleted_at' => $before, 'message' => 'Preserve log']);
        $this->assertTrue(Schema::hasColumn('sms_logs', 'deleted_at'));
    }

    public function test_missing_column_is_added(): void
    {
        Schema::table('sms_logs', fn (Blueprint $table) => $table->dropSoftDeletes());
        $this->assertFalse(Schema::hasColumn('sms_logs', 'deleted_at'));

        $migration = require database_path('migrations/2026_09_12_000001_add_soft_deletes_to_sms_logs_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sms_logs', 'deleted_at'));
    }
}
