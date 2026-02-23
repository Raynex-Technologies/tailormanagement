<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('users.index') }}" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $isEdit ? __('Edit') : __('New') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    <form wire:submit="save">
        <flux:card class="mb-6">
            <flux:heading size="xl" class="mb-6">
                {{ $isEdit ? __('Edit User') : __('New User') }}
            </flux:heading>

            <div class="grid gap-6 sm:grid-cols-2">
                {{-- Name --}}
                <div>
                    <flux:label for="name">{{ __('Name') }} *</flux:label>
                    <flux:input id="name" wire:model="name" placeholder="Full name..." />
                    @error('name')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <flux:label for="email">{{ __('Email') }} *</flux:label>
                    <flux:input type="email" id="email" wire:model="email" placeholder="email@example.com" />
                    @error('email')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Role --}}
                <div>
                    <flux:label for="role">{{ __('Role') }} *</flux:label>
                    <flux:select id="role" wire:model.blur="role">
                        <flux:select.option value="">{{ __('-- Select Role --') }}</flux:select.option>
                        @foreach ($assignableRoles as $r)
                            <flux:select.option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('role')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Branch (Admin only) --}}
                @if ($showBranchSelector)
                    <div>
                        <flux:label for="branch_id">
                            {{ __('Branch') }}
                            @if ($roleRequiresBranch) * @endif
                        </flux:label>
                        <flux:select id="branch_id" wire:model="branch_id">
                            @if (!$roleRequiresBranch)
                                <flux:select.option value="">{{ __('-- No Branch (Global) --') }}</flux:select.option>
                            @else
                                <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                            @endif
                            @foreach ($branches as $branch)
                                <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if (!$roleRequiresBranch)
                            <flux:text class="mt-1 text-xs text-zinc-500">
                                {{ __('Global admin roles can operate without a specific branch.') }}
                            </flux:text>
                        @endif
                        @error('branch_id')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>
                @endif
            </div>

            {{-- Password Section --}}
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                @if ($isEdit)
                    <div class="mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.blur="resetPassword" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Reset Password') }}</span>
                        </label>
                    </div>
                @endif

                @if (!$isEdit || $resetPassword)
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <flux:label for="password">{{ __('Password') }} *</flux:label>
                            <flux:input type="password" id="password" wire:model="password" placeholder="••••••••" />
                            <flux:text class="mt-1 text-xs text-zinc-500">
                                {{ __('Min 8 characters, mixed case, and numbers.') }}
                            </flux:text>
                            @error('password')
                                <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                            @enderror
                        </div>

                        <div>
                            <flux:label for="password_confirmation">{{ __('Confirm Password') }} *</flux:label>
                            <flux:input type="password" id="password_confirmation" wire:model="password_confirmation" placeholder="••••••••" />
                            @error('password_confirmation')
                                <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                            @enderror
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <flux:button type="button" variant="ghost" :href="route('users.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                <x-icon name="check" class="mr-1 size-4" />
                {{ $isEdit ? __('Update User') : __('Create User') }}
            </flux:button>
        </div>
    </form>
</flux:main>
