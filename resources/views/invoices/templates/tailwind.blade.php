@php
    $paymentMethods = ($paymentMethods ?? \App\Models\PaymentMethod::forInvoiceDocument())
        ->take(3)
        ->values();

    $invoiceSentAt = data_get($invoice, 'sent_at');
    $invoiceSentAtLabel = null;

    if ($invoiceSentAt instanceof \DateTimeInterface) {
        $invoiceSentAtLabel = $invoiceSentAt->format('M d, Y H:i');
    } elseif (filled($invoiceSentAt)) {
        $invoiceSentAtLabel = \Illuminate\Support\Carbon::parse($invoiceSentAt)->format('M d, Y H:i');
    }

    $logoSrc = null;
    if ($settings->logo_path) {
        $logoSrc = ($downloadMode ?? false)
            ? $settings->logo_file_path
            : $settings->logo_url;
    } elseif ($settings->logo_url) {
        $logoSrc = $settings->logo_url;
    }
@endphp

<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        color: #1f2937;
        background: #fff;
        padding: 24px;
    }
    .container { max-width: 900px; margin: 0 auto; }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 20px;
    }
    .brand { max-width: 60%; }
    .brand h1 { font-size: 24px; margin-bottom: 4px; }
    .brand p { margin-top: 4px; color: #4b5563; }
    .logo { max-height: 70px; max-width: 180px; margin-bottom: 8px; }
    .doc-meta { text-align: right; }
    .doc-meta h2 { font-size: 20px; margin-bottom: 8px; }
    .doc-meta p { margin-bottom: 4px; }
    .info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }
    .card {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 12px;
    }
    .card h3 {
        font-size: 12px;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7280;
    }
    .card p { margin-bottom: 4px; }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 18px;
    }
    th, td {
        border: 1px solid #d1d5db;
        padding: 10px;
        vertical-align: top;
    }
    th {
        background: #f3f4f6;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    td.num, th.num { text-align: right; white-space: nowrap; }
    .totals {
        margin-left: auto;
        width: 320px;
    }
    .totals .row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .totals .total {
        font-weight: 700;
        font-size: 18px;
        border-top: 2px solid #111827;
        border-bottom: 0;
        margin-top: 4px;
        padding-top: 10px;
    }
    .notes {
        margin-top: 18px;
        border-top: 1px dashed #d1d5db;
        padding-top: 12px;
        white-space: pre-wrap;
    }
    .footer {
        margin-top: 24px;
        font-size: 11px;
        color: #6b7280;
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        text-align: center;
    }
    .print-btn {
        position: fixed;
        top: 16px;
        right: 16px;
        background: #2563eb;
        color: #fff;
        border: 0;
        border-radius: 6px;
        padding: 10px 14px;
        cursor: pointer;
    }
    @media print {
        .no-print { display: none !important; }
        body { padding: 0; }
    }
</style>

<span style="display:none;">Tailwind Basic</span>

<div class="container">
    <div class="header">
        <div class="brand">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="Business Logo" class="logo">
            @endif
            <h1>{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</h1>
            @if ($settings->phone)
                <p>{{ $settings->phone }}</p>
            @endif
            @if ($settings->alternate_phone)
                <p>{{ $settings->alternate_phone }}</p>
            @endif
            @if ($settings->email)
                <p>{{ $settings->email }}</p>
            @endif
            @if ($settings->tin_number)
                <p><strong>TIN:</strong> {{ $settings->tin_number }}</p>
            @endif
            @if ($settings->address)
                <p>{{ $settings->address }}</p>
            @endif
        </div>
        <div class="doc-meta">
            <h2>INVOICE</h2>
            <p><strong>No:</strong> {{ $invoice->invoice_no }}</p>
            <p><strong>Issue:</strong> {{ optional($invoice->issue_date)->format('M d, Y') }}</p>
            <p><strong>Due:</strong> {{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}</p>
            <p><strong>Order:</strong> {{ $invoice->order?->order_no ?? 'N/A' }}</p>
        </div>
    </div>

    <div class="info">
        <div class="card">
            <h3>Bill To</h3>
            <p><strong>{{ $invoice->order?->customer?->name ?? 'Customer' }}</strong></p>
            @if ($invoice->order?->customer?->phone)
                <p>{{ $invoice->order->customer->phone }}</p>
            @endif
            @if ($invoice->order?->customer?->email)
                <p>{{ $invoice->order->customer->email }}</p>
            @endif
            @if ($invoice->order?->customer?->address)
                <p>{{ $invoice->order->customer->address }}</p>
            @endif
        </div>
        <div class="card">
            <h3>Invoice Summary</h3>
            <p><strong>Branch:</strong> {{ $invoice->branch?->name ?? 'N/A' }}</p>
            <p><strong>Status:</strong> {{ $invoice->order?->status?->label() ?? 'N/A' }}</p>
            <p><strong>Payment:</strong> {{ $invoice->order?->payment_status?->label() ?? 'N/A' }}</p>
            <p><strong>Paid Amount:</strong> {{ number_format($invoice->order?->paid_amount ?? 0, 0) }}</p>
            <p><strong>Balance Due:</strong> {{ number_format($invoice->order?->balance_due ?? 0, 0) }}</p>
            @if ($invoiceSentAtLabel)
                <p><strong>Sent:</strong> {{ $invoiceSentAtLabel }}</p>
            @endif
        </div>
    </div>

    @if ($paymentMethods->isNotEmpty())
        <div class="card" style="margin-bottom: 20px;">
            <h3>Payment Methods</h3>
            @foreach ($paymentMethods as $paymentMethod)
                <div @if (! $loop->last) style="margin-bottom: 10px;" @endif>
                    <p><strong>{{ $paymentMethod->name }}</strong></p>
                    @if ($paymentMethod->account_number)
                        <p><strong>Account Number:</strong> {{ $paymentMethod->account_number }}</p>
                    @endif
                    @if ($paymentMethod->account_holder_name)
                        <p><strong>Account Holder:</strong> {{ $paymentMethod->account_holder_name }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 45%">Item</th>
                <th class="num" style="width: 12%">Qty</th>
                <th class="num" style="width: 18%">Unit Price</th>
                <th class="num" style="width: 20%">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                @include('invoices.partials.line-group-heading', ['line' => $line, 'columns' => 4])
                <tr>
                    <td>
                        <strong>{{ $line->item_name }}</strong>
                        @if ($line->notes)
                            <div style="margin-top: 4px; color: #6b7280;">{{ $line->notes }}</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format($line->qty, 2) }}</td>
                    <td class="num">{{ number_format($line->unit_price, 0) }}</td>
                    <td class="num">{{ number_format($line->line_total, 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #6b7280;">No invoice lines</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="row">
            <span>Subtotal</span>
            <strong>{{ number_format($invoice->subtotal, 0) }}</strong>
        </div>
        <div class="row">
            <span>Discount</span>
            <strong>-{{ number_format($invoice->discount, 0) }}</strong>
        </div>
        <div class="row">
            <span>Tax</span>
            <strong>{{ number_format($invoice->tax_amount, 0) }}</strong>
        </div>
        <div class="row total">
            <span>Total</span>
            <strong>{{ number_format($invoice->total, 0) }}</strong>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <strong>Notes</strong>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="footer">
        {{ $settings->business_name ?: config('app.name', 'Tailoring Business') }} |
        {{ $settings->email ?: config('mail.from.address') }}
    </div>
</div>
