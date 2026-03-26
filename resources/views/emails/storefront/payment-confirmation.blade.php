<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payment Confirmation') }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f3f4f6;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
        @if ($settings->logo_url)
            <img
                src="{{ $settings->logo_url }}"
                alt="{{ __('Business Logo') }}"
                style="display: block; max-height: 64px; max-width: 200px; width: auto; height: auto; margin: 0 0 12px;"
            >
        @endif

        <h2 style="margin: 0 0 8px; font-size: 22px;">{{ __('Payment Confirmation') }}</h2>
        <p style="margin: 0 0 20px; color: #6b7280;">
            {{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}
        </p>

        <div style="margin: 0 0 18px; white-space: pre-line;">{!! nl2br(e($bodyContent)) !!}</div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">{{ __('Order No') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $order->order_no }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">{{ __('Amount Paid') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ strtoupper((string) ($transaction->currency ?: $order->currency ?: 'TZS')) }} {{ number_format((float) $transaction->amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">{{ __('Payment Method') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $transaction->paymentMethod?->name ?: strtoupper((string) $transaction->gateway) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">{{ __('Reference') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $transaction->merchant_reference ?: $transaction->gateway_reference ?: 'N/A' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

