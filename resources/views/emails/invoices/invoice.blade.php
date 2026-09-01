<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_no }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f3f4f6;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
        @if ($settings->logo_url)
            <img
                src="{{ $settings->logo_url }}"
                alt="Business Logo"
                style="display: block; max-height: 64px; max-width: 200px; width: auto; height: auto; margin: 0 0 12px;"
            >
        @endif

        <h2 style="margin: 0 0 8px; font-size: 22px;">Invoice {{ $invoice->invoice_no }}</h2>
        <p style="margin: 0 0 20px; color: #6b7280;">
            {{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}
            @if ($settings->tin_number)
                <br>TIN: {{ $settings->tin_number }}
            @endif
        </p>

        @if (filled($emailBody ?? null))
            <div style="margin: 0 0 16px; white-space: pre-line;">{!! nl2br(e($emailBody)) !!}</div>
        @else
            <p style="margin: 0 0 12px;">Dear {{ $invoice->order?->customer?->name ?? 'Customer' }},</p>
            <p style="margin: 0 0 16px;">
                Please find your invoice details below. A PDF copy of this invoice is attached.
            </p>
        @endif

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Order No</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $invoice->order?->order_no ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Issue Date</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ optional($invoice->issue_date)->format('M d, Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Due Date</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">
                    {{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Total</td>
                <td style="padding: 8px 0; text-align: right; font-size: 20px; font-weight: 700;">{{ number_format($financialSummary['total'], 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Paid Amount</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ number_format($financialSummary['paid'], 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Balance Due</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ number_format($financialSummary['balance'], 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Payment Status</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $financialSummary['status']?->label() ?? 'N/A' }}</td>
            </tr>
        </table>

        @if ($settings->phone || $settings->email)
            <p style="margin: 16px 0 0; color: #6b7280;">
                Contact:
                @if ($settings->phone)
                    {{ $settings->phone }}
                @endif
                @if ($settings->phone && $settings->email)
                    |
                @endif
                @if ($settings->email)
                    {{ $settings->email }}
                @endif
            </p>
        @endif
    </div>
</body>
</html>
