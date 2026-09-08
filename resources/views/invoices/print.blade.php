@php
    use App\Models\BusinessSetting;
    use App\Models\PaymentMethod;
    use App\Support\InvoiceTemplateResolver;
    use App\Support\Orders\OrderPackagePresenter;

    $settings = $settings ?? BusinessSetting::instance();
    $paymentMethods = ($paymentMethods ?? PaymentMethod::forInvoiceDocument())
        ->values();
    $template = $template ?? app(InvoiceTemplateResolver::class)->resolve($settings);
    $emailMode = (bool) ($emailMode ?? false);
    $downloadMode = (bool) ($downloadMode ?? false);
    $previewMode = (bool) ($previewMode ?? false);
    $invoicePresentation = app(OrderPackagePresenter::class)->forInvoice($invoice);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $invoice->invoice_no }}</title>
    @if (! $downloadMode)
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @endif
    <style>
        @if ($downloadMode)
        @page {
            margin: 10mm 8mm;
        }
        @endif

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f4f5;
            color: #18181b;
            padding: 20px;
        }
        .invoice-shell {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 16px;
            padding: 26px;
        }
        @if ($previewMode)
        html {
            background: #e4e4e7;
        }
        body {
            min-width: 0;
            min-height: 100vh;
            background: #e4e4e7;
            padding: 0;
        }
        .invoice-shell {
            width: 794px;
            max-width: 100%;
            min-height: 1123px;
            border-radius: 0;
            box-shadow: 0 18px 45px rgba(24, 24, 27, .12);
        }
        @endif
        .print-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 12px;
        }
        .invoice-table {
            width: 100%;
            margin-top: 14px;
        }
        .invoice-table th,
        .invoice-table td {
            vertical-align: top;
        }
        .invoice-table th {
            text-transform: uppercase;
            letter-spacing: .06em;
            text-align: left;
        }
        .block-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #71717a;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .muted { color: #52525b; }
        .small { font-size: 11px; }
        .round-card {
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 12px;
            background: #fafafa;
        }
        @if ($downloadMode)
        body {
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .invoice-shell {
            width: 100%;
            max-width: none;
            margin: 0;
            border: 0;
            border-radius: 0;
            padding: 0;
        }
        @endif
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-shell {
                max-width: none;
                border: 0;
                border-radius: 0;
                padding: 0;
            }
            .no-print { display: none !important; }
        }
        @if ($previewMode)
        @media (max-width: 840px) {
            body { padding: 0; }
            .invoice-shell {
                width: 100%;
                border: 0;
                box-shadow: none;
            }
        }
        @endif
    </style>
</head>
<body data-invoice-template="{{ $template->slug }}" @if ($previewMode) data-invoice-preview="true" @endif>
    @if (! $emailMode && ! $downloadMode && ! $previewMode)
        <button class="print-btn no-print" onclick="window.print()">Print</button>
    @endif

    @if ($previewMode)
        <span style="display: none;" data-preview-template-name>{{ $template->name }}</span>
    @endif

    <div class="invoice-shell">
        @includeFirst(
            [$template->blade_view, 'invoices.templates.classic'],
            [
                'invoice' => $invoice,
                'settings' => $settings,
                'paymentMethods' => $paymentMethods,
                'template' => $template,
                'emailMode' => $emailMode,
                'downloadMode' => $downloadMode,
                'invoicePresentation' => $invoicePresentation,
            ]
        )
    </div>
</body>
</html>
