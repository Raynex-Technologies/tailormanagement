@php
    $defaultPaymentMethod = $paymentMethods->firstWhere('id', 1) ?? $paymentMethods->first();
@endphp

<div style="font-family: Arial, Helvetica, sans-serif; color: #171821;">
    {{-- HEADER --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr>
            <td style="vertical-align: middle; padding: 0;">
                <table style="border-collapse: collapse;">
                    <tr>
                        @if ($settings->logo_path)
                            @php
                                $logoSrc = ($downloadMode ?? false)
                                    ? $settings->logo_file_path
                                    : $settings->logo_url;
                            @endphp
                            <td style="vertical-align: middle; padding: 0 12px 0 0;">
                                <img src="{{ $logoSrc }}" alt="Logo" style="width: 54px; height: 54px;">
                            </td>
                        @endif
                        <td style="vertical-align: middle; padding: 0;">
                            <p style="margin: 0; font-size: 22px; line-height: 1.1; font-weight: 800; letter-spacing: .02em; text-transform: uppercase;">
                                {{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}
                            </p>
                            @if ($settings->address)
                                <p style="margin: 3px 0 0; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em;">
                                    {{ $settings->address }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="vertical-align: top; text-align: right; padding: 0;">
                <span style="font-size: 52px; line-height: .85; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #11131a;">
                    Invoice
                </span>
            </td>
        </tr>
    </table>

    {{-- CUSTOMER + INVOICE INFO --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <tr>
            <td style="vertical-align: top; padding: 0 14px 0 0;">
                <span style="display: inline-block; margin-bottom: 8px; border-radius: 999px; background: #c93d95; color: #ffffff; padding: 2px 14px; font-size: 12px; font-weight: 700;">
                    Invoice To
                </span>
                <p style="margin: 0 0 5px; font-size: 26px; line-height: .9; font-weight: 800;">
                    {{ $invoice->order?->customer?->name ?? 'Customer' }}
                </p>
                @if ($invoice->order?->customer?->phone)
                    <p style="margin: 0 0 2px; font-size: 13px; color: #2f3440;">{{ $invoice->order->customer->phone }}</p>
                @endif
                @if ($invoice->order?->customer?->email)
                    <p style="margin: 0 0 2px; font-size: 13px; color: #2f3440;">{{ $invoice->order->customer->email }}</p>
                @endif
                @if ($invoice->order?->customer?->address)
                    <p style="margin: 0; font-size: 13px; color: #2f3440;">{{ $invoice->order->customer->address }}</p>
                @endif
            </td>
            <td style="vertical-align: top; padding: 0; width: 260px;">
                <div style="border: 1.5px solid #2f3240; border-radius: 18px; padding: 12px 14px; background: #ffffff;">
                    <p style="margin: 0; font-size: 16px; line-height: 1.1; font-weight: 800; white-space: nowrap;">
                        No. #{{ $invoice->invoice_no }}
                    </p>
                    <p style="margin: 8px 0 0; font-size: 13px;">
                        <strong>Date.</strong> {{ optional($invoice->issue_date)->format('d F Y') ?: 'N/A' }}
                    </p>
                    <p style="margin: 3px 0 0; font-size: 13px;">
                        <strong>Due Date.</strong> {{ optional($invoice->due_date)->format('d F Y') ?: 'N/A' }}
                    </p>
                </div>
            </td>
        </tr>
    </table>

    {{-- ITEMS TABLE --}}
    <table class="invoice-table" style="width: 100%; border-collapse: separate; border-spacing: 0 6px; margin-top: 0;">
        <thead>
            <tr>
                <th style="border: 0; background: #171821; color: #ffffff; border-radius: 999px 0 0 999px; width: 60px; text-align: center; padding: 8px 6px; font-size: 11px;">Qty</th>
                <th style="border: 0; background: #171821; color: #ffffff; padding: 8px 10px; font-size: 11px; text-align: left;">Item Description</th>
                <th style="border: 0; background: #171821; color: #ffffff; width: 130px; padding: 8px 10px; font-size: 11px; text-align: right;">Price</th>
                <th style="border: 0; background: #171821; color: #ffffff; width: 130px; border-radius: 0 999px 999px 0; padding: 8px 10px; font-size: 11px; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                @include('invoices.partials.line-group-heading', ['line' => $line, 'columns' => 4])
                @php($outlined = $loop->even)
                <tr>
                    <td style="text-align: center; font-size: 14px; padding: 7px 6px; {{ $outlined ? 'border: 1.4px solid #2f3240; border-right: 0; border-radius: 999px 0 0 999px;' : 'border: 0;' }}">
                        {{ number_format($line->qty, 0) }}
                    </td>
                    <td style="font-size: 15px; padding: 7px 10px; {{ $outlined ? 'border: 1.4px solid #2f3240; border-left: 0; border-right: 0;' : 'border: 0;' }}">
                        {{ $line->item_name }}
                        @if ($line->notes)
                            <div style="margin-top: 2px; font-size: 11px; color: #4b5563;">{{ $line->notes }}</div>
                        @endif
                    </td>
                    <td style="font-size: 15px; padding: 7px 10px; text-align: right; white-space: nowrap; {{ $outlined ? 'border: 1.4px solid #2f3240; border-left: 0; border-right: 0;' : 'border: 0;' }}">
                        {{ number_format($line->unit_price, 2) }}
                    </td>
                    <td style="font-size: 15px; padding: 7px 10px; text-align: right; white-space: nowrap; {{ $outlined ? 'border: 1.4px solid #2f3240; border-left: 0; border-radius: 0 999px 999px 0;' : 'border: 0;' }}">
                        {{ number_format($line->line_total, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="border: 1px solid #d1d5db; border-radius: 12px; text-align: center; padding: 14px;">
                        No invoice lines
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- BOTTOM SECTION --}}
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="vertical-align: top; padding: 0 16px 0 0;">
                <span style="display: inline-block; margin-bottom: 8px; border-radius: 999px; background: #c93d95; color: #ffffff; padding: 2px 14px; font-size: 12px; font-weight: 700;">
                    Payment Method
                </span>
                @if ($defaultPaymentMethod)
                    <p style="margin: 0 0 2px; font-size: 13px; color: #2f3440;">
                        Account {{ $defaultPaymentMethod->account_number ?: '-' }}
                    </p>
                    <p style="margin: 0; font-size: 13px; color: #2f3440;">
                        A/C Name {{ $defaultPaymentMethod->account_holder_name ?: ($defaultPaymentMethod->name ?: '-') }}
                    </p>
                @else
                    <p style="margin: 0; font-size: 13px; color: #2f3440;">No default payment method configured.</p>
                @endif

                <span style="display: inline-block; margin: 16px 0 8px; border-radius: 999px; background: #c93d95; color: #ffffff; padding: 2px 14px; font-size: 12px; font-weight: 700;">
                    Terms & Condition
                </span>
                <p style="margin: 0; font-size: 12px; line-height: 1.45; color: #2f3440;">
                    Payment is due within the invoice due date. Alterations are limited to approved scope.
                    Deposits are non-refundable once production has started. Kindly retain this invoice as proof
                    of service and payment reference.
                </p>
            </td>
            <td style="vertical-align: top; padding: 0; width: 300px;">
                <div style="border: 1.4px solid #2f3240; border-radius: 16px; padding: 10px 12px; background: #ffffff;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="font-size: 13px; padding: 3px 0;">Sub-Total:</td>
                            <td style="font-size: 16px; line-height: 1; font-weight: 800; text-align: right; padding: 3px 0;">{{ number_format($invoice->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-size: 13px; padding: 3px 0;">{{ $settings->tax_name ?? 'VAT' }} ({{ number_format($settings->tax_rate ?? 0, 0) }}%):</td>
                            <td style="font-size: 16px; line-height: 1; font-weight: 800; text-align: right; padding: 3px 0;">{{ number_format($invoice->tax_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top: 6px; border-radius: 999px; background: #171821; color: #ffffff; padding: 8px 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="font-size: 14px; font-weight: 700; color: #ffffff; padding: 0;">Total:</td>
                            <td style="font-size: 20px; line-height: 1; font-weight: 800; text-align: right; color: #ffffff; padding: 0;">{{ number_format($invoice->total, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- FOOTER BAR --}}
    <div style="margin-top: 20px; height: 4px; background-color: #c93d95;"></div>
</div>
