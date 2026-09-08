@php
    $businessName = $settings->business_name ?: config('app.name', 'Tailoring Business');
    $customer = $invoice->order?->customer;
    $isPdf = (bool) ($downloadMode ?? false);
@endphp

@if ($isPdf)
    <div style="font-family: DejaVu Sans, Arial, sans-serif; background: #ffffff; border: 1px solid #d2d5cf; border-radius: 16px; overflow: hidden; color: #121713;">
        <span style="display: none;">[MODERN TEMPLATE]</span>
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <tr>
                <td style="width: 27%; vertical-align: top; background: #ffffff; padding: 16px 0 16px 0;">
                    <div style="margin-left: -42px; margin-right: 12px; border-radius: 0 26px 26px 0; background: #d9dbd7; padding: 30px 14px 24px 42px;">
                        <p style="margin: 0; font-size: 15px; font-weight: 700;">Invoice To :</p>

                        <p style="margin: 12px 0; display: inline-block; padding: 6px 14px; border-radius: 999px; background: #8ca18d; color: #ffffff; font-size: 12px; font-weight: 700;">
                            {{ $customer?->name ?? 'Customer' }}
                        </p>

                        @if ($customer?->phone)
                            <p style="margin: 8px 0 0; font-size: 12px; color: #2f3f34;">+ {{ $customer->phone }}</p>
                        @endif
                        @if ($customer?->email)
                            <p style="margin: 8px 0 0; font-size: 12px; color: #2f3f34;">@ {{ $customer->email }}</p>
                        @endif
                        @if ($customer?->address)
                            <p style="margin: 8px 0 0; font-size: 12px; color: #2f3f34; word-break: break-word;"># {{ $customer->address }}</p>
                        @endif

                        <div style="margin-top: 28px; width: 78px; height: 2px; background: #7d8f80;"></div>

                        <div style="margin-top: 120px; color: #849985; font-size: 32px; line-height: 1.02; font-weight: 700; text-transform: lowercase;">
                            i<br>n<br>v<br>o<br>i<br>c<br>e.
                        </div>
                    </div>
                </td>

                <td style="width: 73%; vertical-align: top; padding: 18px 22px 18px 10px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="vertical-align: middle;">
                                <p style="margin: 0; font-size: 28px; line-height: 1; font-weight: 700; color: #617562;">{{ $businessName }}</p>
                            </td>
                            <td style="text-align: right; vertical-align: middle;">
                                <span style="display: inline-block; max-width: 100%; border: 2px solid #8da18e; border-radius: 999px; padding: 6px 10px; font-size: 11px; color: #3b4a3e; line-height: 1.3; white-space: normal; word-break: break-word;">
                                    {{ $settings->phone ?: '-' }} | {{ $settings->email ?: config('mail.from.address') }}
                                </span>
                            </td>
                        </tr>
                    </table>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <tr>
                            <td style="vertical-align: top;">
                                <p style="margin: 0; font-size: 18px; font-weight: 800;">Grand Total :</p>
                                <p style="margin: 10px 0 12px; display: inline-block; padding: 6px 12px; border-radius: 8px; background: #d8ddd7; color: #7a907b; font-size: 32px; line-height: 1; font-weight: 800;">
                                    {{ number_format($invoice->total, 2) }}
                                </p>
                                <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #2d3b31;">
                                    Here is your invoice for the selected services. Please review the summary and payment details below.
                                </p>
                            </td>
                            <td style="width: 190px; vertical-align: top; padding-left: 8px;">
                                <p style="margin: 0 0 6px; font-size: 14px;"><strong>Invoice No :</strong> {{ $invoice->invoice_no }}</p>
                                <p style="margin: 0 0 6px; font-size: 14px;"><strong>Invoice Date :</strong> {{ optional($invoice->issue_date)->format('d/m/Y') ?: 'N/A' }}</p>
                                <p style="margin: 0; font-size: 14px;"><strong>Invoice Due :</strong> {{ optional($invoice->due_date)->format('d/m/Y') ?: 'N/A' }}</p>
                            </td>
                        </tr>
                    </table>

                    <table style="width: 100%; border-collapse: separate; border-spacing: 0 6px; margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 0 8px 8px 6px; border-bottom: 2px solid #67766a; font-size: 12px; font-weight: 700;">NO</th>
                                <th style="text-align: left; padding: 0 8px 8px; border-bottom: 2px solid #67766a; font-size: 12px; font-weight: 700;">ITEM DESCRIPTION</th>
                                <th style="text-align: right; padding: 0 8px 8px; border-bottom: 2px solid #67766a; font-size: 12px; font-weight: 700;">PRICE</th>
                                <th style="text-align: right; padding: 0 8px 8px; border-bottom: 2px solid #67766a; font-size: 12px; font-weight: 700;">QTY</th>
                                <th style="text-align: right; padding: 0 6px 8px 8px; border-bottom: 2px solid #67766a; font-size: 12px; font-weight: 700;">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice->lines as $line)
                                @include('invoices.partials.line-group-heading', ['line' => $line, 'columns' => 5])
                                @php($striped = $loop->even)
                                <tr style="page-break-inside: avoid;">
                                    <td style="padding: 8px 6px; font-size: 13px; {{ $striped ? 'background:#dfe1de; border-radius:8px 0 0 8px;' : '' }}">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td style="padding: 8px; font-size: 13px; {{ $striped ? 'background:#dfe1de;' : '' }}">
                                        {{ $line->item_name }}
                                        @if ($line->notes)
                                            <div style="margin-top: 2px; font-size: 11px; color: #4b5b4f;">{{ $line->notes }}</div>
                                        @endif
                                    </td>
                                    <td style="padding: 8px; text-align: right; font-size: 13px; white-space: nowrap; {{ $striped ? 'background:#dfe1de;' : '' }}">
                                        {{ number_format($line->unit_price, 2) }}
                                    </td>
                                    <td style="padding: 8px; text-align: right; font-size: 13px; white-space: nowrap; {{ $striped ? 'background:#dfe1de;' : '' }}">
                                        {{ number_format($line->qty, 0) }}
                                    </td>
                                    <td style="padding: 8px 6px; text-align: right; font-size: 13px; white-space: nowrap; {{ $striped ? 'background:#dfe1de; border-radius:0 8px 8px 0;' : '' }}">
                                        {{ number_format($line->line_total, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 12px; text-align: center; font-size: 13px; border-radius: 8px; background: #dfe1de;">
                                        No invoice lines
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
                        <tr>
                            <td style="vertical-align: top; width: 76px; padding-right: 10px;">
                                <div style="width: 62px; height: 62px; border-radius: 999px; background: #8da18e; color: #ffffff; font-size: 30px; line-height: 62px; text-align: center;">&#8600;</div>
                            </td>
                            <td style="vertical-align: top;">
                                <table style="width: 100%; border-collapse: separate; border-spacing: 0 6px;">
                                    <tr>
                                        <td style="padding: 6px 10px; font-size: 14px; font-weight: 600;">Subtotal</td>
                                        <td style="padding: 6px 0; text-align: center; font-size: 14px; font-weight: 600;">:</td>
                                        <td style="padding: 6px 12px; text-align: right; font-size: 14px; font-weight: 600;">{{ number_format($invoice->subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 10px; font-size: 14px; font-weight: 600; background: #dfe1de; border-radius: 6px 0 0 6px;">Discount</td>
                                        <td style="padding: 6px 0; text-align: center; font-size: 14px; font-weight: 600; background: #dfe1de;">:</td>
                                        <td style="padding: 6px 12px; text-align: right; font-size: 14px; font-weight: 600; background: #dfe1de; border-radius: 0 6px 6px 0;">{{ number_format($invoice->discount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 10px; font-size: 14px; font-weight: 600;">Tax</td>
                                        <td style="padding: 6px 0; text-align: center; font-size: 14px; font-weight: 600;">:</td>
                                        <td style="padding: 6px 12px; text-align: right; font-size: 14px; font-weight: 600;">{{ number_format($invoice->tax_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 7px 10px; font-size: 14px; font-weight: 700; color: #ffffff; background: #8da18e; border-radius: 6px 0 0 6px;">Total</td>
                                        <td style="padding: 7px 0; text-align: center; font-size: 14px; font-weight: 700; color: #ffffff; background: #8da18e;">:</td>
                                        <td style="padding: 7px 12px; text-align: right; font-size: 14px; font-weight: 700; color: #ffffff; background: #8da18e; border-radius: 0 6px 6px 0;">{{ number_format($invoice->total, 2) }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 14px;">
                        <p style="margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase;">Terms & Conditions</p>
                        <p style="margin: 8px 0 0; font-size: 13px; line-height: 1.45; color: #2f3e33;">
                            Payment is required on or before the due date. Approved alterations only are allowed after confirmation.
                            Deposits are non-refundable once work has started. Please keep this invoice for support and records.
                        </p>
                    </div>

                    <div style="margin-top: 16px;">
                        <p style="margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase;">Payment Method</p>

                        @foreach ($paymentMethods as $defaultPaymentMethod)
                            <p style="margin: 8px 0 0; font-size: 13px; color: #2d3d31;">
                                <strong>Account No :</strong> {{ $defaultPaymentMethod->account_number ?: '-' }}
                            </p>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #2d3d31;">
                                <strong>Account Name :</strong> {{ $defaultPaymentMethod->account_holder_name ?: ($defaultPaymentMethod->name ?: '-') }}
                            </p>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #2d3d31;">
                                <strong>Bank :</strong> {{ $defaultPaymentMethod->name ?: '-' }}
                            </p>
                        @endforeach
                    </div>
                </td>
            </tr>
        </table>
    </div>
@else
    <div style="font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; background: #ffffff; border: 1px solid #d2d5cf; border-radius: 34px; overflow: hidden; min-height: 1035px; color: #121713;">
        <span style="display: none;">Modern template</span>

        <div style="display: grid; grid-template-columns: 25% 75%; min-height: 1035px; background: #ffffff;">
            <aside style="position: relative; background: #ffffff; overflow: hidden;">
                <div style="position: absolute; top: 24px; bottom: 24px; left: -52px; right: 16px; border-radius: 0 34px 34px 0; background: #d9dbd7;"></div>
                <div style="position: relative; z-index: 1; height: 100%; padding: 64px 24px 50px 28px;">
                    <p style="margin: 0; font-size: 16px; line-height: 1; font-weight: 700;">Invoice To :</p>

                    <p style="margin: 12px 0 12px; display: inline-block; padding: 6px 16px; border-radius: 999px; background: #8ca18d; color: #ffffff; font-size: 13px; line-height: 1; font-weight: 700;">
                        {{ $customer?->name ?? 'Customer' }}
                    </p>

                    @if ($customer?->phone)
                        <p style="margin: 8px 0 0; font-size: 13px; color: #2f3f34;">
                            <i class="fa-light fa-phone" style="width: 14px; margin-right: 8px; color: #7c907d;"></i>
                            {{ $customer->phone }}
                        </p>
                    @endif
                    @if ($customer?->email)
                        <p style="margin: 8px 0 0; font-size: 13px; color: #2f3f34;">
                            <i class="fa-light fa-envelope" style="width: 14px; margin-right: 8px; color: #7c907d;"></i>
                            {{ $customer->email }}
                        </p>
                    @endif
                    @if ($customer?->address)
                        <p style="margin: 8px 0 0; font-size: 13px; color: #2f3f34;">
                            <i class="fa-light fa-location-dot" style="width: 14px; margin-right: 8px; color: #7c907d;"></i>
                            {{ $customer->address }}
                        </p>
                    @endif

                    <div style="margin-top: 28px; width: 86px; height: 2px; background: #7d8f80;"></div>

                    <p style="position: absolute; left: 10px; top: 50%; margin: 0; writing-mode: vertical-rl; transform: translateY(-50%) rotate(180deg); font-size: 124px; line-height: 0.84; letter-spacing: 0.01em; font-weight: 700; color: #849985;">
                        Invoice.
                    </p>
                </div>
            </aside>

            <section style="padding: 30px 34px 30px 28px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa-light fa-leaf" style="font-size: 14px; color: #8ea28f;"></i>
                        <p style="margin: 0; font-size: 35px; line-height: 1; font-weight: 700; color: #617562;">{{ $businessName }}</p>
                    </div>

                    <div style="display: inline-flex; align-items: center; gap: 12px; border: 2px solid #8da18e; border-radius: 999px; padding: 6px 14px;">
                        <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #3b4a3e;">
                            <i class="fa-light fa-phone" style="font-size: 12px; color: #8da18e;"></i>
                            {{ $settings->phone ?: '-' }}
                        </span>
                        <span style="display: inline-block; width: 1px; height: 14px; background: #8da18e;"></span>
                        <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #3b4a3e;">
                            <i class="fa-light fa-envelope" style="font-size: 12px; color: #8da18e;"></i>
                            {{ $settings->email ?: config('mail.from.address') }}
                        </span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 265px; gap: 16px; margin-top: 24px;">
                    <div>
                        <p style="margin: 0; font-size: 20px; line-height: 1; font-weight: 800;">Grand Total :</p>
                        <p style="margin: 10px 0 12px; display: inline-block; padding: 6px 12px; border-radius: 8px; background: #d8ddd7; color: #7a907b; font-size: 48px; line-height: 0.88; font-weight: 800;">
                            {{ number_format($invoice->total, 2) }}
                        </p>

                        <p style="margin: 0; font-size: 17px; line-height: 1.5; color: #2d3b31;">
                            Here is your invoice for the selected services. Please review the summary and payment details below.
                        </p>
                    </div>

                    <div style="padding-top: 8px;">
                        <p style="margin: 0 0 6px; font-size: 16px;">
                            <strong>Invoice No :</strong> {{ $invoice->invoice_no }}
                        </p>
                        <p style="margin: 0 0 6px; font-size: 16px;">
                            <strong>Invoice Date :</strong> {{ optional($invoice->issue_date)->format('d/m/Y') ?: 'N/A' }}
                        </p>
                        <p style="margin: 0; font-size: 16px;">
                            <strong>Invoice Due :</strong> {{ optional($invoice->due_date)->format('d/m/Y') ?: 'N/A' }}
                        </p>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: 18px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 0 10px 8px 8px; border-bottom: 3px solid #67766a; font-size: 15px; line-height: 1; font-weight: 700;">NO</th>
                            <th style="text-align: left; padding: 0 10px 8px; border-bottom: 3px solid #67766a; font-size: 15px; line-height: 1; font-weight: 700;">ITEM DESCRIPTION</th>
                            <th style="text-align: right; padding: 0 10px 8px; border-bottom: 3px solid #67766a; font-size: 15px; line-height: 1; font-weight: 700;">PRICE</th>
                            <th style="text-align: right; padding: 0 10px 8px; border-bottom: 3px solid #67766a; font-size: 15px; line-height: 1; font-weight: 700;">QTY</th>
                            <th style="text-align: right; padding: 0 8px 8px 10px; border-bottom: 3px solid #67766a; font-size: 15px; line-height: 1; font-weight: 700;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoice->lines as $line)
                            @include('invoices.partials.line-group-heading', ['line' => $line, 'columns' => 5])
                            @php($striped = $loop->even)
                            <tr>
                                <td style="padding: 10px 8px; font-size: 18px; {{ $striped ? 'background: #dfe1de; border-radius: 8px 0 0 8px;' : '' }}">
                                    {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </td>
                                <td style="padding: 10px; font-size: 18px; {{ $striped ? 'background: #dfe1de;' : '' }}">
                                    {{ $line->item_name }}
                                    @if ($line->notes)
                                        <div style="margin-top: 3px; font-size: 12px; color: #4b5b4f;">{{ $line->notes }}</div>
                                    @endif
                                </td>
                                <td style="padding: 10px; text-align: right; font-size: 18px; white-space: nowrap; {{ $striped ? 'background: #dfe1de;' : '' }}">
                                    {{ number_format($line->unit_price, 2) }}
                                </td>
                                <td style="padding: 10px; text-align: right; font-size: 18px; white-space: nowrap; {{ $striped ? 'background: #dfe1de;' : '' }}">
                                    {{ number_format($line->qty, 0) }}
                                </td>
                                <td style="padding: 10px 8px; text-align: right; font-size: 18px; white-space: nowrap; {{ $striped ? 'background: #dfe1de; border-radius: 0 8px 8px 0;' : '' }}">
                                    {{ number_format($line->line_total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 14px; text-align: center; font-size: 16px; border-radius: 10px; background: #dfe1de;">
                                    No invoice lines
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div style="display: grid; grid-template-columns: 110px 1fr; gap: 14px; margin-top: 14px; align-items: center;">
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <div style="width: 84px; height: 84px; border-radius: 999px; background: #8da18e; color: #ffffff; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-light fa-arrow-down-right" style="font-size: 44px;"></i>
                        </div>
                    </div>

                    <div style="width: 100%; max-width: 355px; margin-left: auto;">
                        <table style="width: 100%; border-collapse: separate; border-spacing: 0 6px;">
                            <tr>
                                <td style="padding: 7px 10px; font-size: 16px; font-weight: 600;">Subtotal</td>
                                <td style="padding: 7px 0; text-align: center; font-size: 16px; font-weight: 600;">:</td>
                                <td style="padding: 7px 12px; text-align: right; font-size: 16px; font-weight: 600;">{{ number_format($invoice->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 7px 10px; font-size: 16px; font-weight: 600; background: #dfe1de; border-radius: 7px 0 0 7px;">Discount</td>
                                <td style="padding: 7px 0; text-align: center; font-size: 16px; font-weight: 600; background: #dfe1de;">:</td>
                                <td style="padding: 7px 12px; text-align: right; font-size: 16px; font-weight: 600; background: #dfe1de; border-radius: 0 7px 7px 0;">{{ number_format($invoice->discount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 7px 10px; font-size: 16px; font-weight: 600;">Tax</td>
                                <td style="padding: 7px 0; text-align: center; font-size: 16px; font-weight: 600;">:</td>
                                <td style="padding: 7px 12px; text-align: right; font-size: 16px; font-weight: 600;">{{ number_format($invoice->tax_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 10px; font-size: 17px; font-weight: 700; color: #ffffff; background: #8da18e; border-radius: 7px 0 0 7px;">Total</td>
                                <td style="padding: 8px 0; text-align: center; font-size: 17px; font-weight: 700; color: #ffffff; background: #8da18e;">:</td>
                                <td style="padding: 8px 12px; text-align: right; font-size: 17px; font-weight: 700; color: #ffffff; background: #8da18e; border-radius: 0 7px 7px 0;">{{ number_format($invoice->total, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <p style="margin: 0; font-size: 22px; line-height: 1; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase;">Terms & Conditions</p>
                    <p style="margin: 8px 0 0; font-size: 16px; line-height: 1.5; color: #2f3e33;">
                        Payment is required on or before the due date. Approved alterations only are allowed after confirmation.
                        Deposits are non-refundable once work has started. Please keep this invoice for support and records.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <p style="margin: 0; font-size: 22px; line-height: 1; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase;">Payment Method</p>

                    @foreach ($paymentMethods as $defaultPaymentMethod)
                        <p style="margin: 10px 0 0; font-size: 16px; color: #2d3d31;">
                            <strong>Account No :</strong> {{ $defaultPaymentMethod->account_number ?: '-' }}
                        </p>
                        <p style="margin: 5px 0 0; font-size: 16px; color: #2d3d31;">
                            <strong>Account Name :</strong> {{ $defaultPaymentMethod->account_holder_name ?: ($defaultPaymentMethod->name ?: '-') }}
                        </p>
                        <p style="margin: 5px 0 0; font-size: 16px; color: #2d3d31;">
                            <strong>Bank :</strong> {{ $defaultPaymentMethod->name ?: '-' }}
                        </p>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endif
