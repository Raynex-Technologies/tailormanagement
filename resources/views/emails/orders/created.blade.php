<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Order Confirmation') }}</title>
</head>
<body style="margin: 0; padding: 24px; background: #f4f4f5; color: #18181b; font-family: Arial, Helvetica, sans-serif; line-height: 1.5;">
    <div style="max-width: 640px; margin: 0 auto; overflow: hidden; border: 1px solid #e4e4e7; border-radius: 14px; background: #ffffff;">
        <div style="padding: 24px; background: #172033; color: #ffffff;">
            <div style="font-size: 13px; color: #bef264;">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</div>
            <h1 style="margin: 6px 0 0; font-size: 24px;">{{ __('Your order is confirmed') }}</h1>
        </div>

        <div style="padding: 24px;">
            <p style="margin: 0 0 18px;">
                {{ __('Hello :name, we have received your order and will keep you updated as work progresses.', ['name' => $order->customer?->name ?: __('Customer')]) }}
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Order') }}</td>
                    <td style="padding: 7px 0; text-align: right; font-weight: 700;">{{ $order->order_no }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Order date') }}</td>
                    <td style="padding: 7px 0; text-align: right;">{{ optional($order->order_date)->format('M d, Y') ?: __('Not set') }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Due date') }}</td>
                    <td style="padding: 7px 0; text-align: right;">{{ optional($order->due_date)->format('M d, Y') ?: __('To be confirmed') }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Branch') }}</td>
                    <td style="padding: 7px 0; text-align: right;">{{ $order->branch?->name ?: __('Main business') }}</td>
                </tr>
            </table>

            <h2 style="margin: 0 0 8px; font-size: 16px;">{{ __('Order items') }}</h2>
            <div style="border-top: 1px solid #e4e4e7; margin-bottom: 20px;">
                @foreach ($order->lines as $line)
                    <div style="display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #e4e4e7;">
                        <span>{{ $line->item_name }} <small style="color: #71717a;">x {{ (float) $line->qty }}</small></span>
                        <strong>{{ strtoupper((string) ($order->currency ?: 'TZS')) }} {{ number_format((float) $line->line_total, 2) }}</strong>
                    </div>
                @endforeach
            </div>

            <table style="width: 100%; border-collapse: collapse; border-radius: 10px; background: #f7fee7;">
                <tr>
                    <td style="padding: 12px; color: #3f6212;">{{ __('Total') }}</td>
                    <td style="padding: 12px; text-align: right; font-weight: 700;">{{ strtoupper((string) ($order->currency ?: 'TZS')) }} {{ number_format($financialSummary['total'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 0 12px 12px; color: #3f6212;">{{ __('Paid / Balance') }}</td>
                    <td style="padding: 0 12px 12px; text-align: right;">{{ number_format($financialSummary['paid'], 2) }} / {{ number_format($financialSummary['balance'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 0 12px 12px; color: #3f6212;">{{ __('Payment status') }}</td>
                    <td style="padding: 0 12px 12px; text-align: right;">{{ $financialSummary['status']->label() }}</td>
                </tr>
            </table>

            <p style="margin: 20px 0 0; color: #52525b;">
                {{ __('Your current invoice is attached as a PDF.') }}
                @if ($settings->phone)
                    {{ __('Questions? Call :phone.', ['phone' => $settings->phone]) }}
                @endif
            </p>
        </div>
    </div>
</body>
</html>
