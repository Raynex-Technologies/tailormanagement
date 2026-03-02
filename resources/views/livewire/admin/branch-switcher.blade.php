<div class="px-3 py-3">
    <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-white/45">
            <i class="fa-duotone fa-code-branch size-4"></i>
        </div>

        <select
            wire:model.live="selectedBranchId"
            @disabled($branches->isEmpty())
            aria-label="{{ __('Active branch') }}"
            class="w-full appearance-none rounded-xl border-0 py-2.5 pl-10 pr-10 text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-lime-400 disabled:cursor-not-allowed disabled:opacity-70"
            style="background: rgba(255, 255, 255, 0.06); color: white;"
        >
            @forelse ($branches as $branch)
                <option value="{{ $branch->id }}" style="background: #1E1F2E;">
                    {{ $branch->name }}@if ($branch->code) ({{ $branch->code }}) @endif
                </option>
            @empty
                <option value="" style="background: #1E1F2E;">{{ __('No active branches available') }}</option>
            @endforelse
        </select>

        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-white/45">
            <i class="fa-duotone fa-chevron-down text-xs"></i>
        </div>
    </div>
</div>
