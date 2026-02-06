@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name', 'Tailor Pro') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
            <x-app-logo-icon class="size-5" style="color: #1E1F2E;" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name', 'Tailor Pro') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
            <x-app-logo-icon class="size-5" style="color: #1E1F2E;" />
        </x-slot>
    </flux:brand>
@endif
