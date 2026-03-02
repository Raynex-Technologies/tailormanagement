<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item>{{ __('Reports') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Page Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex items-start gap-4">
            <div class="flex items-center justify-center size-12 rounded-xl bg-violet-100 dark:bg-violet-900/30">
                <i class="fa-duotone fa-chart-mixed size-6 text-violet-600 dark:text-violet-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Reports Dashboard') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Select a report to view detailed analytics and insights.') }}</p>
            </div>
        </div>
    </div>

    {{-- Report Cards Grid --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($reports as $report)
            <a
                href="{{ route($report['route']) }}"
                wire:navigate
                class="group relative overflow-hidden rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 transition-all hover:shadow-md hover:border-{{ $report['color'] }}-300 dark:hover:border-{{ $report['color'] }}-700/50"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-12 rounded-xl bg-{{ $report['color'] }}-100 dark:bg-{{ $report['color'] }}-900/30">
                        <x-icon :name="$report['icon']" class="size-6 text-{{ $report['color'] }}-600 dark:text-{{ $report['color'] }}-400" />
                    </div>
                    <i class="fa-duotone fa-arrow-right size-4 text-zinc-300 dark:text-zinc-600 transition-transform group-hover:translate-x-1 group-hover:text-{{ $report['color'] }}-500"></i>
                </div>

                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white group-hover:text-{{ $report['color'] }}-600 dark:group-hover:text-{{ $report['color'] }}-400 transition-colors">
                    {{ $report['name'] }}
                </h3>
                <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $report['description'] }}
                </p>
            </a>
        @endforeach
    </div>
</flux:main>
