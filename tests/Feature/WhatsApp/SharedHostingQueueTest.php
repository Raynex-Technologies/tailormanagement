<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedHostingQueueTest extends TestCase
{
    public function test_cron_worker_processes_a_database_job_and_exits_when_empty(): void
    {
        config(['queue.default' => 'database']);
        SharedHostingProbeJob::$handled = false;
        app('queue')->connection('database')->push(new SharedHostingProbeJob);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->artisan('queue:work-batch')->assertSuccessful();
        $this->assertTrue(SharedHostingProbeJob::$handled);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}

class SharedHostingProbeJob implements ShouldQueue
{
    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }
}
