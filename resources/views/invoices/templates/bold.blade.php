<div style="font-family: 'Montserrat', 'Arial', sans-serif; background: #ffffff; border: 1px solid #e5e7eb; overflow: hidden; color: #141723;">
    <div style="display: grid; grid-template-columns: 37% 63%;">
        <section style="padding: 20px 18px 16px; background: #ffffff;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                @if ($settings->logo_url)
                    <img src="{{ $settings->logo_url }}" alt="Business Logo" style="max-height: 34px; max-width: 76px; border-radius: 4px;">
                @endif
                <div>
                    <p style="margin: 0; font-size: 30px; line-height: 1; font-weight: 800;">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</p>
                    <p style="margin: 0; font-size: 11px; color: #6b7280; letter-spacing: .12em; text-transform: uppercase;">{{ __('Your Tagline Here') }}</p>
                </div>
            </div>

            <p style="margin: 0; font-size: 12px; font-weight: 700; color: #374151; letter-spacing: .08em; text-transform: uppercase;">Invoice To.</p>
            <p style="margin: 4px 0 6px; font-size: 44px; line-height: .9; font-weight: 800;">{{ $invoice->order?->customer?->name ?? 'Customer' }}</p>
            @if ($invoice->order?->customer?->phone)
                <p style="margin: 0; font-size: 12px; color: #4b5563;">{{ $invoice->order->customer->phone }}</p>
            @endif
            @if ($invoice->order?->customer?->address)
                <p style="margin: 2px 0 0; font-size: 12px; color: #4b5563;">{{ $invoice->order->customer->address }}</p>
            @endif
            @if ($invoice->order?->customer?->email)
                <p style="margin: 2px 0 0; font-size: 12px; color: #4b5563;">{{ $invoice->order->customer->email }}</p>
            @endif
        </section>

        <section style="background: #1d202a; color: #f8fafc; padding: 18px 18px 16px; border-bottom-left-radius: 28px; position: relative; overflow: hidden;">
            <h1 style="margin: 0; font-size: 92px; line-height: .82; font-weight: 800; letter-spacing: .01em;">Invoice</h1>
            <div style="margin-top: 8px; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; font-size: 12px;">
                <div>
                    <p style="margin: 0; opacity: .75;">Invoice No.</p>
                    <p style="margin: 2px 0 0; font-weight: 700;">{{ $invoice->invoice_no }}</p>
                </div>
                <div>
                    <p style="margin: 0; opacity: .75;">Date.</p>
                    <p style="margin: 2px 0 0; font-weight: 700;">{{ optional($invoice->issue_date)->format('d M Y') ?: 'N/A' }}</p>
                </div>
                <div>
                    <p style="margin: 0; opacity: .75;">Due Date.</p>
                    <p style="margin: 2px 0 0; font-weight: 700;">{{ optional($invoice->due_date)->format('d M Y') ?: 'N/A' }}</p>
                </div>
            </div>

            <div style="position: absolute; right: -10px; bottom: -8px; width: 140px; height: 110px; background: linear-gradient(132deg, transparent 0 24%, #e8c6a4 24% 58%, #e8c6a4 58% 70%, transparent 70%);"></div>
        </section>
    </div>

    <table class="invoice-table" style="margin-top: 12px; border-collapse: separate; border-spacing: 0 8px;">
        <thead>
            <tr>
                <th style="border: 0; background: #1d202a; color: #ffffff; border-radius: 999px 0 0 999px; width: 70px; text-align: center;">Qty</th>
                <th style="border: 0; background: #1d202a; color: #ffffff;">Item Description</th>
                <th class="num" style="border: 0; background: #1d202a; color: #ffffff; width: 150px;">Price</th>
                <th class="num" style="border: 0; background: #1d202a; color: #ffffff; width: 150px; border-radius: 0 999px 999px 0;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr style="background: {{ $loop->odd ? '#ffffff' : '#ead8c4' }};">
                    <td style="border: 0; border-radius: 999px 0 0 999px; text-align: center; font-weight: 700;">{{ number_format($line->qty, 0) }}</td>
                    <td style="border: 0;">
                        <strong>{{ $line->item_name }}</strong>
                        @if ($line->notes)
                            <div class="small muted" style="margin-top: 3px;">{{ $line->notes }}</div>
                        @endif
                    </td>
                    <td class="num" style="border: 0;">{{ number_format($line->unit_price, 0) }}</td>
                    <td class="num" style="border: 0; border-radius: 0 999px 999px 0;">{{ number_format($line->line_total, 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; border: 0; border-radius: 10px; background: #ffffff;">No invoice lines</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 18px; margin-top: 12px; padding: 0 14px 14px;">
        <div>
            @if ($paymentMethods->isNotEmpty())
                <p style="margin: 0 0 6px; font-size: 32px; line-height: .9; font-weight: 800; color: #1d202a;">Payment Method.</p>
                @foreach ($paymentMethods as $paymentMethod)
                    <p style="margin: 0 0 4px; font-size: 12px; color: #1f2937;">
                        <strong>{{ $paymentMethod->name }}</strong>
                        @if ($paymentMethod->account_number)
                            - {{ $paymentMethod->account_number }}
                        @endif
                        @if ($paymentMethod->account_holder_name)
                            - {{ $paymentMethod->account_holder_name }}
                        @endif
                    </p>
                @endforeach
            @endif

            @if ($invoice->notes)
                <p style="margin: 14px 0 4px; font-size: 32px; line-height: .9; font-weight: 800; color: #1d202a;">Terms & Condition.</p>
                <p style="margin: 0; font-size: 12px; color: #4b5563; white-space: pre-wrap;">{{ $invoice->notes }}</p>
            @endif
        </div>

        <div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #d1d5db; padding: 6px 0;">
                <span style="font-size: 15px;">Sub-Total:</span>
                <strong style="font-size: 31px; line-height: .9;">{{ number_format($invoice->subtotal, 0) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #d1d5db; padding: 6px 0;">
                <span style="font-size: 15px;">Tax Vat (15%):</span>
                <strong style="font-size: 31px; line-height: .9;">{{ number_format($invoice->tax_amount, 0) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 8px;">
                <span style="font-size: 16px; font-weight: 800;">Total:</span>
                <strong style="font-size: 46px; line-height: .84;">{{ number_format($invoice->total, 0) }}</strong>
            </div>

            <div style="margin-top: 14px; text-align: right;">
                <p style="margin: 0; font-size: 26px; font-style: italic; color: #505866;">{{ __('Signature') }}</p>
                <p style="margin: 0; font-size: 30px; font-weight: 800; color: #1d202a;">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</p>
                <p style="margin: 0; font-size: 12px; color: #4b5563;">{{ __('Accountants') }}</p>
            </div>
        </div>
    </div>

    <div style="background: #1d202a; color: #ffffff; padding: 10px 14px; display: flex; justify-content: space-between; gap: 10px; align-items: center;">
        <div style="display: flex; gap: 18px; font-size: 11px; opacity: .95;">
            <span>{{ $settings->phone ?: '-' }}</span>
            <span>{{ $settings->email ?: config('mail.from.address') }}</span>
            <span>{{ $settings->address ?: '-' }}</span>
        </div>
        <div style="width: 64px; height: 64px; background: #e8c6a4; display: grid; place-items: center; color: #1d202a; font-size: 10px; font-weight: 700;">QR</div>
    </div>
</div>