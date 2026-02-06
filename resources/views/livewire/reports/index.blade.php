<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item>Reports</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <flux:card class="mb-6">
        <flux:heading size="xl">Reports Dashboard</flux:heading>
        <flux:text class="mt-1">Select a report to view detailed analytics and insights.</flux:text>
    </flux:card>

    {{-- Report Cards Grid --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($reports as $report)
            <a
                href="{{ route($report['route']) }}"
                wire:navigate
                class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:border-{{ $report['color'] }}-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-{{ $report['color'] }}-600"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-12 rounded-xl bg-{{ $report['color'] }}-100 text-{{ $report['color'] }}-600 dark:bg-{{ $report['color'] }}-900/30 dark:text-{{ $report['color'] }}-400">
                        <flux:icon :name="$report['icon']" class="size-6" />
                    </div>
                    <flux:icon name="arrow-right" class="size-5 text-zinc-400 transition-transform group-hover:translate-x-1" />
                </div>

                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ $report['name'] }}
                </h3>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $report['description'] }}
                </p>
            </a>
        @endforeach
    </div>
</flux:main>
