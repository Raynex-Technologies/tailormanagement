@props(['active' => 'items'])
<nav aria-label="{{ __('Order Catalog navigation') }}" class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-white p-1 dark:border-white/10 dark:bg-zinc-900">
    @foreach ([
        ['items', 'Catalog Items', 'order-catalog.index', ['tab' => 'items'], 'order_catalog.view'],
        ['packages', 'Packages', 'order-catalog.index', ['tab' => 'packages'], 'order_catalog.view'],
        ['garment-types', 'Garment Types', 'order-catalog.garment-types.index', [], 'garment-options.view'],
        ['measurements', 'Measurements', 'order-catalog.index', ['tab' => 'measurements'], 'order_catalog.view'],
        ['customization', 'Customization Options', 'admin.garment-options.index', [], 'garment-options.view'],
    ] as [$key, $label, $route, $parameters, $permission])
        @can($permission)
                <a href="{{ route($route, $parameters) }}" wire:navigate @if($active === $key) aria-current="page" @endif class="rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tm-accent)] {{ $active === $key ? 'tm-active' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' }}">{{ __($label) }}</a>
        @endcan
    @endforeach
</nav>
