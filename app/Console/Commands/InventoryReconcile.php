<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Console\Command;

class InventoryReconcile extends Command
{
    protected $signature = 'inventory:reconcile {--detailed : Include item, variant and reference evidence} {--json : Emit structured evidence}';

    protected $description = 'Inspect stock, ledger, branch and variant consistency without changing data';

    public function handle(InventoryReconciliationService $service): int
    {
        $report = $service->inspect();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Inventory Reconciliation');
            $this->table(['Check', 'Count'], collect($report['summary'])->map(fn ($count, $key) => [ucfirst(str_replace('_', ' ', $key)), $count])->values()->all());
            $this->info('Stock-unit identity and cutover checks (separate from historical ledger differences)');
            $this->table(['Check', 'Count'], collect($report['stock_units']['summary'])->map(fn ($count, $key) => [ucfirst(str_replace('_', ' ', $key)), $count])->values()->all());
            if ($this->option('detailed')) {
                foreach ([...$report['issues'], ...$report['stock_units']['issues']] as $issue) {
                    $this->line(json_encode($issue, JSON_THROW_ON_ERROR));
                }
                foreach ($report['variants'] as $item) {
                    $this->line(json_encode($item, JSON_THROW_ON_ERROR));
                }
            }
            $this->info('No data changed. Ledger differences require review; they are not proof of missing movements.');
        }

        return self::SUCCESS;
    }
}
