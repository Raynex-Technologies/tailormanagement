<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$invoice = App\Models\Invoice::withoutGlobalScopes()
    ->where('invoice_no', 'INV-2026-000014')->firstOrFail();
$settings = App\Models\BusinessSetting::query()->firstOrFail();
file_put_contents(__DIR__.'/corrected-invoice.pdf', app(App\Support\CanonicalInvoicePdf::class)->render($invoice, $settings));
echo "Rendered invoice PDF.\n";
