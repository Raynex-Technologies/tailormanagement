@props([
    'scale' => 2,
])

<flux:input
    type="text"
    inputmode="decimal"
    autocomplete="off"
    data-money-input
    data-money-scale="{{ $scale }}"
    {{ $attributes }}
/>
