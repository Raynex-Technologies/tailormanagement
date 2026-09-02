@props([
    'title',
    'subtitle' => null,
])

<section
    {{ $attributes->class(['mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6']) }}
    style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);"
    data-theme-hero data-orders-workspace-header
    data-workspace-breadcrumb-accent="secondary"
>
    @isset($breadcrumbs)
        <div class="mb-5 text-white/70">
            {{ $breadcrumbs }}
        </div>
    @endisset

    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-1 max-w-3xl text-sm text-white/70">{{ $subtitle }}</p>
            @endif

            @isset($meta)
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-white/75">
                    {{ $meta }}
                </div>
            @endisset
        </div>

        @isset($actions)
            <div class="grid w-full gap-2 sm:w-auto sm:grid-flow-col sm:auto-cols-max sm:items-center">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
