<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('capital.index') }}" wire:navigate>{{ __('Capital') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('New Allocation') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    {{-- Branch Selection Banner for Global Admins --}}
    @if ($showBranchSelector && !$branchId)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Select a branch below to create this capital allocation.') }}
        </flux:callout>
    @endif

    <flux:card class="max-w-2xl">
        <flux:heading size="xl" class="mb-6">{{ __('Create Capital Allocation') }}</flux:heading>

        <form wire:submit="save" class="space-y-6">
            {{-- Branch Selector for Global Admins --}}
            @if ($showBranchSelector)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <flux:label for="branchId">{{ __('Branch') }} *</flux:label>
                    <flux:select id="branchId" wire:model.blur="branchId">
                        <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                        @foreach ($branches as $branch)
                            <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                        {{ __('The allocation will be assigned to this branch. Accountants from this branch will be available below.') }}
                    </flux:text>
                    @error('branchId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            @endif

            {{-- Accountant --}}
            <div>
                <flux:label for="accountantId">{{ __('Accountant') }} *</flux:label>
                <flux:select id="accountantId" wire:model="accountantId">
                    <flux:select.option value="">{{ __('-- Select Accountant --') }}</flux:select.option>
                    @foreach ($accountants as $accountant)
                        <flux:select.option value="{{ $accountant->id }}">
                            {{ $accountant->name }} ({{ $accountant->email }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                @if ($showBranchSelector && !$branchId)
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Select a branch first to see available accountants.') }}</flux:text>
                @endif
                @error('accountantId')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            {{-- Period --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:label for="startsOn">{{ __('Start Date') }} *</flux:label>
                    <flux:input type="date" id="startsOn" wire:model="startsOn" />
                    @error('startsOn')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div>
                    <flux:label for="endsOn">{{ __('End Date') }}</flux:label>
                    <flux:input type="date" id="endsOn" wire:model="endsOn" />
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Leave empty for ongoing allocation') }}</flux:text>
                    @error('endsOn')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            {{-- Amount --}}
            <div>
                <flux:label for="initialAmount">{{ __('Initial Amount (TZS)') }} *</flux:label>
                <flux:input type="number" id="initialAmount" wire:model="initialAmount" step="1" min="1" placeholder="0" />
                @error('initialAmount')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            {{-- Note --}}
            <div>
                <flux:label for="note">{{ __('Note') }}</flux:label>
                <flux:textarea id="note" wire:model="note" rows="3" placeholder="Optional notes..." />
                @error('note')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" :href="route('capital.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    <x-icon name="check" class="mr-1 size-4" />
                    {{ __('Create Allocation') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</flux:main>
