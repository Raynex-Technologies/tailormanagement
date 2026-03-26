@blaze

@props([
    'container' => null,
])

@php
$classes = Flux::classes('w-full')
    // Global app layout already provides page padding.
    ->add('p-0')
    ->add($container ? 'mx-auto w-full [:where(&)]:max-w-7xl' : '')
    ;
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
