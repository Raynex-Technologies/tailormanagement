<?php

namespace Tests\Unit\Console;

use App\Console\Commands\WorkQueueBatch;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class WorkQueueBatchTest extends TestCase
{
    private Application $app;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/tailor-queue-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/framework', 0700, true);
        $this->app = new Application;
        $this->app->useStoragePath($this->directory);
        $this->app->instance('config', new Repository([
            'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'retry_after' => 90, 'queue' => 'default']]],
            'twilio' => ['timeout' => 30],
        ]));
    }

    protected function tearDown(): void
    {
        $path = $this->directory.'/framework/queue-work-batch.lock';
        if (is_file($path)) {
            unlink($path);
        }
        rmdir($this->directory.'/framework');
        rmdir($this->directory);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_worker_is_bounded_and_lock_is_released_after_failure(): void
    {
        $command = $this->command(function ($name, $options) {
            $this->assertSame('queue:work', $name);
            $this->assertSame('database', $options['connection']);
            $this->assertTrue($options['--stop-when-empty']);
            $this->assertSame(40, $options['--max-time']);
            $this->assertSame(25, $options['--max-jobs']);
            $lock = fopen($this->directory.'/framework/queue-work-batch.lock', 'c');
            $this->assertFalse(flock($lock, LOCK_EX | LOCK_NB));
            fclose($lock);
            throw new \RuntimeException('Simulated worker failure');
        });
        try {
            $command->handle();
            $this->fail('Expected worker failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated worker failure', $e->getMessage());
        }
        $lock = fopen($this->directory.'/framework/queue-work-batch.lock', 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        fclose($lock);
    }

    public function test_overlapping_cron_tick_does_not_start_another_worker(): void
    {
        $lock = fopen($this->directory.'/framework/queue-work-batch.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->assertSame(0, $this->command(fn () => $this->fail('Worker must not run'))->handle());
        } finally {
            fclose($lock);
        }
    }

    public function test_invalid_queue_or_timeout_configuration_does_not_consume_jobs(): void
    {
        $command = $this->command(fn () => $this->fail('Worker must not run'));
        config(['queue.default' => 'sync']);
        $this->assertSame(1, $command->handle());
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 30]);
        $this->assertSame(1, $command->handle());
        config(['queue.connections.database.retry_after' => 90, 'twilio.timeout' => 60]);
        $this->assertSame(1, $command->handle());
    }

    private function command(\Closure $callback): WorkQueueBatch
    {
        $command = new class($callback) extends WorkQueueBatch
        {
            public function __construct(private \Closure $callback)
            {
                parent::__construct();
            }

            public function call($command, array $arguments = [])
            {
                return ($this->callback)($command, $arguments);
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        return $command;
    }
}
