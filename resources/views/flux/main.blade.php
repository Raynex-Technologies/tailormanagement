@blaze

@props([
    'container' => null,
])

@php
$classes = Flux::classes('[grid-area:main]')
    // Global app layout already provides page padding.
    ->add('p-0')
    ->add('[[data-flux-container]_&]:px-0')
    ->add($container ? 'mx-auto w-full [:where(&)]:max-w-7xl' : '')
    ;
@endphp

<div {{ $attributes->class($classes) }} data-flux-main>
    {{ $slot }}
</div>
