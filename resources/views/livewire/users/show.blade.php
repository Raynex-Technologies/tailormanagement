<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('users.index') }}" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-xl font-bold text-white">
                    {{ $user->initials() }}
                </div>
                <div>
                    <flux:heading size="xl">{{ $user->name }}</flux:heading>
                    <flux:text>{{ $user->email }}</flux:text>
                </div>
            </div>
            @if ($canEdit)
                <flux:button variant="primary" :href="route('users.edit', $user)" wire:navigate>
                    <flux:icon name="pencil" class="mr-1 size-4" />
                    {{ __('Edit User') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- User Details --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('User Details') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt>
                    <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $user->email }}</dd>
                </div>

                <div class="flex justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Role') }}</dt>
                    <dd>
                        @foreach ($user->roles as $role)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                @if(in_array($role->name, ['superadmin', 'admin'])) bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400
                                @elseif($role->name === 'branch_manager') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400
                                @else bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                            </span>
                        @endforeach
                    </dd>
                </div>

                <div class="flex justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</dt>
                    <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $user->branch?->name ?? __('Global (No Branch)') }}
                    </dd>
                </div>

                <div class="flex justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                    <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $user->created_at->format('M d, Y \a\t H:i') }}
                    </dd>
                </div>

                <div class="flex justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Email Verified') }}</dt>
                    <dd class="text-sm font-medium">
                        @if ($user->email_verified_at)
                            <span class="text-emerald-600 dark:text-emerald-400">
                                {{ $user->email_verified_at->format('M d, Y') }}
                            </span>
                        @else
                            <span class="text-amber-600 dark:text-amber-400">{{ __('Not Verified') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </flux:card>

        {{-- Activity Summary --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Activity Summary') }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($activity['orders_created']) }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Orders Created') }}</div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($activity['orders_assigned']) }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Orders Assigned') }}</div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($activity['payments_received']) }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Payments Received') }}</div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($activity['expenses_created']) }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expenses Created') }}</div>
                </div>
            </div>
        </flux:card>
    </div>
</flux:main>
