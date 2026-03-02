<div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Progress statistics') }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->progressData['period_label'] }}</p>
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="range" size="sm">
                @foreach ($this->rangeOptions as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="mt-3 flex items-end gap-3">
        <p class="text-5xl leading-none font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->progressData['progress_percent'] }}%</p>
        <div class="pb-1 text-xs text-zinc-500 dark:text-zinc-400">
            <p>{{ __('Total') }}</p>
            <p>{{ __('activity') }}</p>
        </div>
    </div>

    <div class="mt-4">
        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700 flex gap-0.5">
            @if ($this->progressData['in_progress_width'] > 0)
                <span class="h-full rounded-full" style="width: {{ $this->progressData['in_progress_width'] }}%; background-color: #7C3AED;"></span>
            @endif
            @if ($this->progressData['completed_width'] > 0)
                <span class="h-full rounded-full" style="width: {{ $this->progressData['completed_width'] }}%; background-color: #10B981;"></span>
            @endif
            @if ($this->progressData['upcoming_width'] > 0)
                <span class="h-full rounded-full" style="width: {{ $this->progressData['upcoming_width'] }}%; background-color: #F59E0B;"></span>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-violet-200/60 dark:border-violet-800/30">
            <div class="flex items-center justify-center size-9 rounded-xl mb-3 bg-violet-100 dark:bg-violet-900/40">
                <i class="fa-duotone fa-clock size-4 text-violet-600 dark:text-violet-400"></i>
            </div>
            <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->progressData['in_progress_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('In progress') }}</p>
        </div>

        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-emerald-200/60 dark:border-emerald-800/30">
            <div class="flex items-center justify-center size-9 rounded-xl mb-3 bg-emerald-100 dark:bg-emerald-900/40">
                <i class="fa-duotone fa-circle-check size-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->progressData['completed_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
        </div>

        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-amber-200/60 dark:border-amber-800/30">
            <div class="flex items-center justify-center size-9 rounded-xl mb-3 bg-amber-100 dark:bg-amber-900/40">
                <i class="fa-duotone fa-layer-group size-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->progressData['upcoming_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Upcoming') }}</p>
        </div>
    </div>

    @if ($this->progressData['requires_branch_selection'])
        <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">{{ __('Select a branch to see progress statistics.') }}</p>
    @endif
</div>
