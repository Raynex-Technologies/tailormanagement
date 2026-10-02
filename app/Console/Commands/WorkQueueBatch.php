<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WorkQueueBatch extends Command
{
    protected $signature = 'queue:work-batch';

    protected $description = 'Process a bounded database queue batch for shared-hosting cron';

    public function handle(): int
    {
        if (config('queue.default') !== 'database' || config('queue.connections.database.driver') !== 'database') {
            $this->error('Set QUEUE_CONNECTION=database before running the cron worker.');

            return self::FAILURE;
        }
        if ((int) config('queue.connections.database.retry_after', 90) <= 60 || (int) config('twilio.timeout', 30) > 30) {
            $this->error('Use DB_QUEUE_RETRY_AFTER greater than 60 seconds and TWILIO_TIMEOUT at most 30 seconds.');

            return self::FAILURE;
        }

        $handle = @fopen(storage_path('framework/queue-work-batch.lock'), 'c');
        if ($handle === false) {
            $this->error('The cron user must be able to write to storage/framework.');

            return self::FAILURE;
        }
        try {
            // OS-owned lock is released on process exit, including termination by the host.
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                $this->info('A queue batch is already running; this cron tick was skipped.');

                return self::SUCCESS;
            }

            // Calls Laravel in this PHP process; no shell, proc_open, or supervisor required.
            return $this->call('queue:work', [
                'connection' => 'database',
                '--queue' => (string) config('queue.connections.database.queue', 'default'),
                '--stop-when-empty' => true,
                '--max-time' => 40,
                '--max-jobs' => 25,
                '--timeout' => 45,
                '--tries' => 3,
                '--sleep' => 1,
                '--memory' => 128,
            ]);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
