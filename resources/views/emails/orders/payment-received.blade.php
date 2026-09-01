<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payment Received') }}</title>
</head>
<body style="margin: 0; padding: 24px; background: #f4f4f5; color: #18181b; font-family: Arial, Helvetica, sans-serif; line-height: 1.5;">
    <div style="max-width: 640px; margin: 0 auto; overflow: hidden; border: 1px solid #e4e4e7; border-radius: 14px; background: #ffffff;">
        <div style="padding: 24px; background: #172033; color: #ffffff;">
            <div style="font-size: 13px; color: #bef264;">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</div>
            <h1 style="margin: 6px 0 0; font-size: 24px;">{{ __('Payment received') }}</h1>
        </div>

        <div style="padding: 24px;">
            <p style="margin: 0 0 18px;">
                {{ __('Hello :name, your payment for order :order has been recorded successfully.', [
                    'name' => $order->customer?->name ?: __('Customer'),
                    'order' => $order->order_no,
                ]) }}
            </p>

            <div style="margin-bottom: 20px; border: 1px solid #d9f99d; border-radius: 12px; background: #f7fee7; padding: 18px;">
                <div style="font-size: 13px; color: #4d7c0f;">{{ __('Payment amount') }}</div>
                <div style="margin-top: 4px; font-size: 25px; font-weight: 700; color: #166534;">
                    {{ strtoupper((string) ($order->currency ?: 'TZS')) }} {{ number_format((float) $payment->amount, 2) }}
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Payment method') }}</td>
                    <td style="padding: 7px 0; text-align: right; font-weight: 600;">{{ $payment->paymentMethod?->name ?: ($payment->gateway ?: __('Not specified')) }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Payment date') }}</td>
                    <td style="padding: 7px 0; text-align: right;">{{ optional($payment->paid_at)->format('M d, Y H:i') ?: optional($payment->created_at)->format('M d, Y H:i') }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Reference') }}</td>
                    <td style="padding: 7px 0; text-align: right;">{{ $payment->reference ?: $payment->gateway_reference ?: __('N/A') }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Cumulative paid') }}</td>
                    <td style="padding: 7px 0; text-align: right; font-weight: 600;">{{ number_format($financialSummary['paid'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #71717a;">{{ __('Balance remaining') }}</td>
                    <td style="padding: 7px 0; text-align: right; font-weight: 600;">{{ number_format($financialSummary['balance'], 2) }}</td>
                </tr>
            </table>

            <p style="margin: 0; color: #52525b;">
                {{ __('An updated invoice reflecting this payment is attached as a PDF.') }}
                @if ($order->branch?->name)
                    {{ __('Served by :branch.', ['branch' => $order->branch->name]) }}
                @endif
            </p>
        </div>
    </div>
</body>
</html>
