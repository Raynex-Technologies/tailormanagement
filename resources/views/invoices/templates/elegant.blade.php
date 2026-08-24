<div style="font-family: 'Montserrat', 'Arial', sans-serif; background: #0a5f59; border: 1px solid #0d746e; color: #defaf4; overflow: hidden;">
    <div style="display: grid; grid-template-columns: 1fr 34%; min-height: 820px;">
        <section style="padding: 18px 16px 14px; background: linear-gradient(180deg, #0a6f69, #08514c 58%, #084842);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px;">
                <div style="display: inline-flex; align-items: center; gap: 7px; border-radius: 0 0 12px 12px; background: rgba(255,255,255,.08); padding: 6px 10px 5px;">
                    @if ($settings->logo_url)
                        <img src="{{ $settings->logo_url }}" alt="Business Logo" style="max-height: 24px; max-width: 56px; border-radius: 3px;">
                    @endif
                    <span style="font-size: 12px; font-weight: 700; color: #c4f3ea;">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</span>
                </div>
                <div style="font-size: 11px; color: #b9ece2;">{{ $settings->email ?: config('mail.from.address') }}</div>
            </div>

            <p style="margin: 0; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #9ce0d5; font-weight: 700;">Invoice To</p>
            <p style="margin: 4px 0 4px; font-size: 50px; line-height: .88; font-weight: 800; color: #f4fffd;">{{ $invoice->order?->customer?->name ?? 'Customer' }}</p>
            @if ($invoice->order?->customer?->address)
                <p style="margin: 0; font-size: 11px; color: #b5e8de;">{{ $invoice->order->customer->address }}</p>
            @endif

            <div style="margin-top: 9px; padding-top: 8px; border-top: 1px solid rgba(196,243,234,.36); font-size: 12px; color: #d2f6ef;">
                <p style="margin: 0 0 3px;"><strong>Invoice No :</strong> {{ $invoice->invoice_no }}</p>
                <p style="margin: 0 0 3px;"><strong>Invoice Date :</strong> {{ optional($invoice->issue_date)->format('M d, Y') ?: 'N/A' }}</p>
                <p style="margin: 0;"><strong>Invoice Due :</strong> {{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}</p>
            </div>

            <table class="invoice-table" style="margin-top: 10px; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="background: #1cc5b0; color: #013732; border-color: #1cc5b0;">Description</th>
                        <th class="num" style="background: #1cc5b0; color: #013732; border-color: #1cc5b0; width: 70px;">Qty</th>
                        <th class="num" style="background: #1cc5b0; color: #013732; border-color: #1cc5b0; width: 120px;">Price</th>
                        <th class="num" style="background: #1cc5b0; color: #013732; border-color: #1cc5b0; width: 130px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoice->lines as $line)
                        @include('invoices.partials.line-group-heading', ['line' => $line, 'columns' => 4])
                        <tr style="background: {{ $loop->odd ? 'rgba(255,255,255,.04)' : 'rgba(255,255,255,.08)' }};">
                            <td style="border-color: rgba(255,255,255,.14); color: #e8fffb;">
                                <strong>{{ $line->item_name }}</strong>
                                @if ($line->notes)
                                    <div style="margin-top: 2px; font-size: 10px; color: #b8ebe2;">{{ $line->notes }}</div>
                                @endif
                            </td>
                            <td class="num" style="border-color: rgba(255,255,255,.14); color: #d6f7f1;">{{ number_format($line->qty, 0) }}</td>
                            <td class="num" style="border-color: rgba(255,255,255,.14); color: #d6f7f1;">{{ number_format($line->unit_price, 0) }}</td>
                            <td class="num" style="border-color: rgba(255,255,255,.14); color: #ffffff;">{{ number_format($line->line_total, 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; border-color: rgba(255,255,255,.14); color: #d0efe9;">No invoice lines</td>
                        </tr>
                    @endforelse
                    <tr style="background: #13a997; color: #ffffff; font-weight: 700;">
                        <td style="border-color: #13a997;" colspan="3">Total Due (Include Tax)</td>
                        <td class="num" style="border-color: #13a997;">{{ number_format($invoice->total, 0) }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($invoice->notes)
                <div style="margin-top: 10px;">
                    <p style="margin: 0 0 4px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: #a8e5db; font-weight: 700;">Terms and Conditions</p>
                    <p style="margin: 0; font-size: 11px; color: #c2efe6; white-space: pre-wrap;">{{ $invoice->notes }}</p>
                </div>
            @endif

            <div style="margin-top: 12px; border-radius: 10px; background: rgba(28,197,176,.2); border: 1px solid rgba(28,197,176,.45); padding: 6px 10px; display: flex; gap: 14px; font-size: 10px; color: #c6f2ea;">
                <span>{{ $settings->email ?: config('mail.from.address') }}</span>
                <span>{{ $settings->phone ?: '-' }}</span>
                <span>{{ $settings->address ?: '-' }}</span>
            </div>
        </section>

        <aside style="background: #f4f6f4; color: #0c3834; padding: 14px 14px 14px 18px; border-left: 4px solid #17bca8; border-radius: 28px 0 0 28px; margin: 10px 0 10px 4px; position: relative; overflow: hidden;">
            <div style="position: absolute; inset: 0; border: 3px solid #0ca899; border-radius: 28px 0 0 28px; pointer-events: none;"></div>
            <div style="height: 100%; display: flex; flex-direction: column; justify-content: space-between; position: relative; z-index: 1;">
                <div>
                    <div style="writing-mode: vertical-rl; transform: rotate(180deg); letter-spacing: .05em; font-size: 76px; line-height: .86; font-weight: 800; color: #0ea99b; margin: 6px auto 14px; text-align: center;">INVOICE</div>

                    <div style="border-top: 1px solid #9aa8a5; padding-top: 9px; margin-top: 6px;">
                        <p style="margin: 0 0 6px; font-size: 10px; font-weight: 700; color: #50645f; text-transform: uppercase;">Payment Method</p>
                        @if ($paymentMethods->isNotEmpty())
                            @foreach ($paymentMethods as $paymentMethod)
                                <p style="margin: 0 0 5px; font-size: 11px; color: #113d39;">
                                    <strong>{{ $paymentMethod->name }}</strong>
                                    @if ($paymentMethod->account_number)
                                        <br>{{ $paymentMethod->account_number }}
                                    @endif
                                    @if ($paymentMethod->account_holder_name)
                                        <br>{{ $paymentMethod->account_holder_name }}
                                    @endif
                                </p>
                            @endforeach
                        @else
                            <p style="margin: 0; font-size: 11px; color: #113d39;">No payment method</p>
                        @endif
                    </div>

                    <div style="border-top: 1px solid #9aa8a5; padding-top: 9px; margin-top: 8px;">
                        <p style="margin: 0 0 4px; font-size: 10px; font-weight: 700; color: #50645f; text-transform: uppercase;">Date</p>
                        <p style="margin: 0; font-size: 11px;">{{ optional($invoice->issue_date)->format('M d, Y') ?: 'N/A' }}</p>
                    </div>
                </div>

                <div style="border-left: 4px solid #17bca8; padding-left: 10px; margin-top: 12px;">
                    <p style="margin: 0; font-size: 17px; line-height: 1.1; font-weight: 800; color: #0c3834;">THANK YOU FOR<br>USING OUR SERVICE</p>
                </div>
            </div>
        </aside>
    </div>
</div>
