<div style="font-family: 'Cormorant Garamond', Georgia, 'Times New Roman', serif; background: #efe8d8; border: 1px solid #d4c5ac; box-shadow: inset -74px 0 0 #3d2a20; color: #3f2c20; padding: 22px 18px 18px;">
    <div style="text-align: center; margin-bottom: 12px;">
        <div style="width: 58px; height: 58px; border-radius: 999px; margin: 0 auto 10px; background: #a33a2b; color: #f8ebd9; display: flex; align-items: center; justify-content: center; font-size: 21px; font-weight: 800;">*</div>
        <h1 style="margin: 0; font-size: 56px; line-height: .84; color: #9d3326; font-style: italic; font-weight: 600;">Invoice</h1>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 260px; gap: 12px; margin-bottom: 12px;">
        <div>
            <p style="margin: 0; font-size: 12px;">{{ $settings->phone ?: '-' }}</p>
            <p style="margin: 2px 0 0; font-size: 12px;">{{ $settings->email ?: config('mail.from.address') }}</p>
            <p style="margin: 2px 0 0; font-size: 12px;">{{ $settings->address ?: '-' }}</p>
        </div>
        <div style="text-align: right;">
            <p style="margin: 0; font-size: 12px;"><strong>No:</strong> {{ $invoice->invoice_no }}</p>
            <p style="margin: 2px 0 0; font-size: 12px;"><strong>Date:</strong> {{ optional($invoice->issue_date)->format('d F Y') ?: 'N/A' }}</p>
            <p style="margin: 2px 0 0; font-size: 12px;"><strong>Due:</strong> {{ optional($invoice->due_date)->format('d F Y') ?: 'N/A' }}</p>
            <p style="margin: 2px 0 0; font-size: 12px;"><strong>Order:</strong> {{ $invoice->order?->order_no ?? 'N/A' }}</p>
        </div>
    </div>

    <div style="height: 1px; background: #cab79a; margin-bottom: 10px;"></div>

    <table class="invoice-table" style="border: 0; background: transparent; margin-top: 0;">
        <thead>
            <tr>
                <th style="border: 0; background: #a33a2b; color: #f8ecdd; border-radius: 999px 0 0 999px; width: 58px;">No.</th>
                <th style="border: 0; background: #a33a2b; color: #f8ecdd;">Description</th>
                <th class="num" style="border: 0; background: #a33a2b; color: #f8ecdd; width: 90px;">Qty</th>
                <th class="num" style="border: 0; background: #a33a2b; color: #f8ecdd; width: 130px; border-radius: 0 999px 999px 0;">Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td style="border: 0; border-bottom: 1px solid #d6c4a9; text-align: center;">{{ $loop->iteration }}</td>
                    <td style="border: 0; border-bottom: 1px solid #d6c4a9;">
                        {{ $line->item_name }}
                        @if ($line->notes)
                            <div style="margin-top: 2px; font-size: 11px; color: #6a4f3e;">{{ $line->notes }}</div>
                        @endif
                    </td>
                    <td class="num" style="border: 0; border-bottom: 1px solid #d6c4a9;">{{ number_format($line->qty, 0) }}</td>
                    <td class="num" style="border: 0; border-bottom: 1px solid #d6c4a9;">{{ number_format($line->line_total, 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; border: 0; border-bottom: 1px solid #d6c4a9;">No invoice lines</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
        <div style="width: 250px; border-radius: 999px 0 0 999px; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; background: #a33a2b; color: #f8ecdd; padding: 7px 12px; font-size: 15px;">
                <span style="font-weight: 700;">Total</span>
                <span style="font-weight: 700;">{{ number_format($invoice->total, 0) }}</span>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 24px;">
        <div>
            <p style="margin: 0 0 5px; font-size: 26px; font-style: italic; color: #7a3428; font-weight: 700;">Directed to</p>
            <p style="margin: 0; font-size: 12px;">{{ $invoice->order?->customer?->name ?? 'Customer' }}</p>
            @if ($invoice->order?->customer?->phone)
                <p style="margin: 2px 0 0; font-size: 12px;">{{ $invoice->order->customer->phone }}</p>
            @endif
            @if ($invoice->order?->customer?->address)
                <p style="margin: 2px 0 0; font-size: 12px;">{{ $invoice->order->customer->address }}</p>
            @endif
        </div>

        <div>
            <p style="margin: 0 0 5px; font-size: 26px; font-style: italic; color: #7a3428; font-weight: 700;">Payment info</p>
            @if ($paymentMethods->isNotEmpty())
                @foreach ($paymentMethods as $paymentMethod)
                    <p style="margin: 0 0 3px; font-size: 12px;">
                        <strong>{{ $paymentMethod->name }}</strong>
                        @if ($paymentMethod->account_number)
                            - {{ $paymentMethod->account_number }}
                        @endif
                        @if ($paymentMethod->account_holder_name)
                            - {{ $paymentMethod->account_holder_name }}
                        @endif
                    </p>
                @endforeach
            @else
                <p style="margin: 0; font-size: 12px;">{{ __('No payment method configured.') }}</p>
            @endif
        </div>
    </div>

    @if ($invoice->notes)
        <div style="margin-top: 10px; font-size: 11px; color: #5f4838; white-space: pre-wrap;">{{ $invoice->notes }}</div>
    @endif
</div>