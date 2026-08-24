@php
    use App\Support\Orders\OrderPackagePresenter;

    $deliveryPresentation = app(OrderPackagePresenter::class)->forOrder($deliveryNote->order);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Note - {{ $deliveryNote->delivery_note_no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #1a1a1a;
            padding: 20mm;
            max-width: 210mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .company-name {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 10pt;
            color: #666;
        }

        .document-title {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 15px;
            color: #333;
        }

        .document-number {
            font-size: 14pt;
            color: #666;
            margin-top: 5px;
        }

        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .info-block {
            width: 48%;
        }

        .info-block h3 {
            font-size: 10pt;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-block p {
            margin: 3px 0;
        }

        .info-block .label {
            color: #666;
            font-size: 9pt;
        }

        .info-block .value {
            font-weight: 500;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background: #f5f5f5;
            padding: 12px 10px;
            text-align: left;
            font-size: 10pt;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .items-table th:nth-child(2),
        .items-table th:nth-child(3),
        .items-table td:nth-child(2),
        .items-table td:nth-child(3) {
            text-align: right;
        }

        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        .total-row {
            font-weight: bold;
            font-size: 14pt;
        }

        .total-row td {
            border-top: 2px solid #333;
            border-bottom: none;
            padding-top: 15px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 30px;
        }

        .signature-block {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }

        .signature-label {
            font-size: 10pt;
            color: #666;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }

        @media print {
            body {
                padding: 15mm;
            }

            .no-print {
                display: none;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12pt;
        }

        .print-button:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">Print</button>

    <div class="header">
        <div class="company-name">{{ config('app.name', 'Tailoring Business') }}</div>
        <div class="company-tagline">Quality Tailoring Services</div>
        <div class="document-title">Delivery Note</div>
        <div class="document-number">{{ $deliveryNote->delivery_note_no }}</div>
    </div>

    <div class="info-section">
        <div class="info-block">
            <h3>Customer Information</h3>
            <p><span class="label">Name:</span> <span class="value">{{ $deliveryNote->order?->customer?->name ?? 'N/A' }}</span></p>
            @if ($deliveryNote->order?->customer?->phone)
                <p><span class="label">Phone:</span> <span class="value">{{ $deliveryNote->order->customer->phone }}</span></p>
            @endif
            @if ($deliveryNote->order?->customer?->address)
                <p><span class="label">Address:</span> <span class="value">{{ $deliveryNote->order->customer->address }}</span></p>
            @endif
        </div>
        <div class="info-block">
            <h3>Delivery Information</h3>
            <p><span class="label">Order No:</span> <span class="value">{{ $deliveryNote->order?->order_no ?? 'N/A' }}</span></p>
            <p><span class="label">Delivery Date:</span> <span class="value">{{ $deliveryNote->delivered_at->format('M d, Y') }}</span></p>
            <p><span class="label">Delivery Time:</span> <span class="value">{{ $deliveryNote->delivered_at->format('H:i') }}</span></p>
            <p><span class="label">Delivered By:</span> <span class="value">{{ $deliveryNote->deliveredBy?->name ?? 'N/A' }}</span></p>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%">Item Description</th>
                <th style="width: 15%">Qty</th>
                <th style="width: 15%">Unit Price</th>
                <th style="width: 20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryPresentation['groups'] as $group)
                @if ($group['type'] === 'package')
                    <tr>
                        <td colspan="4" style="padding-top: 12px; padding-bottom: 6px; background: #f5f3ff; color: #4c1d95; font-weight: 700;">{{ $group['package']['name'] }}</td>
                    </tr>
                @elseif ($deliveryPresentation['has_packages'])
                    <tr>
                        <td colspan="4" style="padding-top: 12px; padding-bottom: 6px; background: #f4f4f5; color: #52525b; font-size: 10px; font-weight: 700; text-transform: uppercase;">{{ __('Additional Items') }}</td>
                    </tr>
                @endif
                @foreach ($group['lines'] as $displayLine)
                    @php($line = $displayLine['record'])
                    <tr>
                        <td>{{ $displayLine['display_name'] }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}</td>
                        <td>{{ number_format($line->unit_price, 0) }}</td>
                        <td>{{ number_format($line->line_total, 0) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($deliveryNote->order?->discount > 0)
                <tr>
                    <td colspan="3" style="text-align: right; padding-top: 15px;">Subtotal</td>
                    <td style="padding-top: 15px;">{{ number_format($deliveryNote->order->subtotal, 0) }}</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right; color: #e11d48;">Discount</td>
                    <td style="color: #e11d48;">-{{ number_format($deliveryNote->order->discount, 0) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td>{{ number_format($deliveryNote->order?->total ?? 0, 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-block">
            <div class="signature-line">
                <div class="signature-label">Delivered By</div>
                <div style="margin-top: 5px; font-weight: 500;">{{ $deliveryNote->deliveredBy?->name ?? '________________' }}</div>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-line">
                <div class="signature-label">Received By</div>
                <div style="margin-top: 5px; font-weight: 500;">
                    @if ($deliveryNote->received_by_name)
                        {{ $deliveryNote->received_by_name }}
                        @if ($deliveryNote->received_by_phone)
                            <br><span style="font-size: 9pt; color: #666;">{{ $deliveryNote->received_by_phone }}</span>
                        @endif
                    @else
                        ________________
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p style="margin-top: 5px;">This is a computer-generated document. No signature required.</p>
    </div>
</body>
</html>
