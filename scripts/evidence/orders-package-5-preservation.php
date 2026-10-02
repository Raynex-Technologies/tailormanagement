<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = storage_path('app/orders-package-5-preservation.json');
$capture = in_array('--capture', $argv, true);
if ($capture && is_file($path)) {
    throw new RuntimeException('Baseline already exists.');
}
$before = $capture ? [] : json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$result = [];
foreach (['inventory_items', 'inventory_item_variants', 'inventory_stock_units', 'inventory_stocks', 'inventory_transactions', 'order_lines', 'order_package_template_items', 'order_package_instances', 'invoice_lines'] as $table) {
    $columns = $capture ? Illuminate\Support\Facades\Schema::getColumnListing($table) : $before[$table]['columns'];
    sort($columns);
    $hash = hash_init('sha256');
    $count = 0;
    foreach (Illuminate\Support\Facades\DB::table($table)->select($columns)->orderBy('id')->cursor() as $row) {
        hash_update($hash, json_encode((array) $row, JSON_THROW_ON_ERROR)."\n");
        $count++;
    }
    $result[$table] = ['columns' => $columns, 'count' => $count, 'sha256' => hash_final($hash)];
}
if ($capture) {
    file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT));
    echo "Preservation hashes captured; no record payloads stored.\n";
} else {
    foreach ($result as $table => $value) {
        echo $table.': '.($value === $before[$table] ? 'PRESERVED' : 'CHANGED')."\n";
    } if ($result !== $before) {
        exit(1);
    }
}
