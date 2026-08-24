@php($lineId = data_get($line, 'id'))
@php($lineContext = $lineId !== null ? data_get($invoicePresentation ?? [], 'line_context.'.$lineId, []) : [])

@if ($lineContext['starts_package'] ?? false)
    @php($documentPackage = $lineContext['package'])
    <tr style="page-break-inside: avoid;">
        <td colspan="{{ $columns ?? 4 }}" style="padding: 10px 8px 6px; border: 0; background: transparent;">
            <strong style="font-size: 12px; color: #4c1d95;">{{ $documentPackage['name'] }}</strong>
            <span style="margin-left: 8px; font-size: 10px; color: #6b7280;">
                {{ __('Configured package value: :amount', ['amount' => money_currency($documentPackage['configured_total'], config('app.currency', 'TZS'))]) }}
            </span>
        </td>
    </tr>
@elseif ($lineContext['starts_ordinary'] ?? false)
    <tr style="page-break-inside: avoid;">
        <td colspan="{{ $columns ?? 4 }}" style="padding: 10px 8px 6px; border: 0; background: transparent; font-size: 11px; font-weight: 700; color: #52525b; text-transform: uppercase; letter-spacing: .06em;">
            {{ __('Additional Items') }}
        </td>
    </tr>
@endif
