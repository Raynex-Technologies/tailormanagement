<div class="px-3 py-3">
    <div class="rounded-xl p-3" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.06);">
        <div class="mb-2 flex items-center justify-between">
            <label class="text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">
                {{ __('Active Branch') }}
            </label>
            @if ($selectedBranchId)
                <button
                    type="button"
                    wire:click="clearSelection"
                    class="text-white/40 hover:text-white/70 transition-colors"
                    title="{{ __('Clear selection') }}"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <select
            wire:model.live="selectedBranchId"
            class="w-full rounded-lg border-0 px-3 py-2 text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-lime-400"
            style="background: rgba(255, 255, 255, 0.06); color: white;"
        >
            <option value="" style="background: #1E1F2E;">{{ __('— Select Branch —') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" style="background: #1E1F2E;">
                    {{ $branch->name }}
                    @if ($branch->code)
                        ({{ $branch->code }})
                    @endif
                </option>
            @endforeach
        </select>

        @if (!$selectedBranchId)
            <p class="mt-2 flex items-center gap-1.5 text-xs text-amber-400/80">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                {{ __('Select a branch to work with data') }}
            </p>
        @else
            <p class="mt-2 flex items-center gap-1.5 text-xs" style="color: #A3E635;">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ __('Working in:') }} <span class="font-semibold">{{ $currentBranchName }}</span>
            </p>
        @endif
    </div>
</div>
