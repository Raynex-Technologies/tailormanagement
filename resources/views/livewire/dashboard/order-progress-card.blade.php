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
        <p class="text-5xl leading-none font-bold text-zinc-900 dark:text-white">{{ $this->progressData['progress_percent'] }}%</p>
        <div class="pb-1 text-xs text-zinc-500 dark:text-zinc-400">
            <p>{{ __('Total') }}</p>
            <p>{{ __('activity') }}</p>
        </div>
    </div>

    <div class="mt-4">
        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700 flex">
            @if ($this->progressData['in_progress_width'] > 0)
                <span class="h-full" style="width: {{ $this->progressData['in_progress_width'] }}%; background-color: #7C3AED;"></span>
            @endif
            @if ($this->progressData['completed_width'] > 0)
                <span class="h-full" style="width: {{ $this->progressData['completed_width'] }}%; background-color: #10B981;"></span>
            @endif
            @if ($this->progressData['upcoming_width'] > 0)
                <span class="h-full" style="width: {{ $this->progressData['upcoming_width'] }}%; background-color: #F59E0B;"></span>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-zinc-200/60 dark:border-zinc-700/60">
            <div class="flex items-center justify-center size-8 rounded-full mb-3" style="background-color: rgba(124, 58, 237, 0.16);">
                <svg class="size-4" style="color: #7C3AED;" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->progressData['in_progress_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('In progress') }}</p>
        </div>

        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-zinc-200/60 dark:border-zinc-700/60">
            <div class="flex items-center justify-center size-8 rounded-full mb-3" style="background-color: rgba(16, 185, 129, 0.16);">
                <svg class="size-4" style="color: #10B981;" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->progressData['completed_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
        </div>

        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-zinc-200/60 dark:border-zinc-700/60">
            <div class="flex items-center justify-center size-8 rounded-full mb-3" style="background-color: rgba(245, 158, 11, 0.16);">
                <svg class="size-4" style="color: #F59E0B;" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </div>
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->progressData['upcoming_count']) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Upcoming') }}</p>
        </div>
    </div>

    @if ($this->progressData['requires_branch_selection'])
        <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">{{ __('Select a branch to see progress statistics.') }}</p>
    @endif
</div>
