<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = ['inventory_items', 'inventory_item_variants', 'inventory_stocks', 'inventory_transactions', 'order_lines', 'pos_sale_items', 'cart_items', 'fabric_variants'];
$evidence = [];
foreach ($tables as $table) {
    $context = hash_init('sha256');
    $count = 0;
    foreach (Illuminate\Support\Facades\DB::table($table)->orderBy('id')->cursor() as $row) {
        $values = (array) $row;
        unset($values['inventory_stock_unit_id'], $values['variant_mode'], $values['stock_identity_status']);
        ksort($values);
        hash_update($context, json_encode($values, JSON_THROW_ON_ERROR)."\n");
        $count++;
    }
    $evidence[$table] = ['count' => $count, 'sha256' => hash_final($context)];
}
$path = storage_path('app/stock-unit-2-preservation.json');
if (in_array('--capture', $argv, true)) {
    if (is_file($path)) {
        throw new RuntimeException('Preservation evidence already exists; will not overwrite the baseline.');
    }
    file_put_contents($path, json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['captured' => true, 'tables' => array_map(fn ($v) => $v['count'], $evidence)], JSON_PRETTY_PRINT).PHP_EOL;
} else {
    $before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $results = [];
    foreach ($evidence as $table => $value) {
        $results[$table] = $before[$table] === $value ? 'PRESERVED' : 'CHANGED';
    }
    echo json_encode($results, JSON_PRETTY_PRINT).PHP_EOL;
    exit(in_array('CHANGED', $results, true) ? 1 : 0);
}
