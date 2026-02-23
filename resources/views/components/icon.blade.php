@props([
    'name' => '',
])

@php
    $attrsClass = $attributes->get('class', '');
    $sizeMap = [
        'size-3' => 'text-sm',
        'size-4' => 'text-base',
        'size-5' => 'text-lg',
        'size-6' => 'text-xl',
        'size-8' => 'text-2xl',
        'size-10' => 'text-3xl',
        'size-12' => 'text-4xl',
    ];
    $sizeClass = 'text-lg';
    foreach ($sizeMap as $tw => $text) {
        if (str_contains($attrsClass, $tw)) {
            $sizeClass = $text;
            break;
        }
    }
@endphp
<span
    {{ $attributes->merge(['class' => 'material-symbols-outlined shrink-0 inline-block ' . $sizeClass])->except('name') }}
    aria-hidden="true"
>{{ $name ?: $slot }}</span>
