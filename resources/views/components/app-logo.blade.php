@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name', 'Tailor Pro') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl" style="background: linear-gradient(135deg, var(--tm-accent) 0%, var(--tm-accent-hover) 100%);">
            <x-app-logo-icon class="size-5" style="color: var(--tm-accent-foreground);" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name', 'Tailor Pro') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl" style="background: linear-gradient(135deg, var(--tm-accent) 0%, var(--tm-accent-hover) 100%);">
            <x-app-logo-icon class="size-5" style="color: var(--tm-accent-foreground);" />
        </x-slot>
    </flux:brand>
@endif
