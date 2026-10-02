<?php

namespace App\Console\Commands;

use App\Services\Inventory\StockUnitBackfillService;
use Illuminate\Console\Command;

class InventoryStockUnitsBackfill extends Command
{
    protected $signature = 'inventory:stock-units:backfill {--dry-run : Inspect without writes (the default)} {--apply : Apply reviewed identity-only backfill} {--json}';

    protected $description = 'Prepare stock identities while preserving physical balances and historical records';

    public function handle(StockUnitBackfillService $service): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose dry-run or apply, not both.');

            return self::FAILURE;
        }
        $report = $service->run(! $this->option('apply'));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
