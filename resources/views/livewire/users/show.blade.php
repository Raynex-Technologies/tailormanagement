<flux:main class="p-0">
    <section class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);" data-theme-hero data-user-detail-header>
        <flux:breadcrumbs class="mb-5 text-white/70">
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
            <flux:breadcrumbs.item :href="route('users.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item class="!text-white">{{ $user->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-white/15 text-lg font-bold text-white ring-1 ring-white/20">{{ $user->initials() }}</span>
                <div class="min-w-0"><flux:heading size="xl" class="!text-white">{{ $user->name }}</flux:heading><p class="mt-1 truncate text-sm text-white/70">{{ $user->email }}</p></div>
            </div>
            @if ($canEdit)<flux:button variant="primary" icon="pencil-square" :href="route('users.edit', $user)" wire:navigate>{{ __('Edit User') }}</flux:button>@endif
        </div>
    </section>

    @if (session('success'))<flux:callout class="mb-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif

    <section class="mb-6" aria-labelledby="account-summary-heading">
        <h2 id="account-summary-heading" class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Account summary') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card><p class="text-sm text-zinc-500">{{ __('Email verification') }}</p><p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $user->email_verified_at ? __('Verified') : __('Not verified') }}</p></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Role') }}</p><p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ str($user->roles->first()?->name ?? 'Unassigned')->replace('_', ' ')->title() }}</p></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Access Scope') }}</p><p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $user->branch?->name ?? __('Global access') }}</p></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Member Since') }}</p><p class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $user->created_at->format('M d, Y') }}</p></flux:card>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">{{ __('Account information') }}</flux:heading>
            <dl class="mt-5 divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="grid gap-1 py-3 first:pt-0 sm:grid-cols-3"><dt class="text-sm text-zinc-500">{{ __('Full Name') }}</dt><dd class="break-words text-sm font-medium text-zinc-900 sm:col-span-2 dark:text-white">{{ $user->name }}</dd></div>
                <div class="grid gap-1 py-3 sm:grid-cols-3"><dt class="text-sm text-zinc-500">{{ __('Email') }}</dt><dd class="break-all text-sm font-medium text-zinc-900 sm:col-span-2 dark:text-white">{{ $user->email }}</dd></div>
                <div class="grid gap-1 py-3 last:pb-0 sm:grid-cols-3"><dt class="text-sm text-zinc-500">{{ __('Email verification') }}</dt><dd class="text-sm font-medium sm:col-span-2"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->email_verified_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $user->email_verified_at ? __('Verified') : __('Not verified') }}</span></dd></div>
            </dl>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ __('Activity summary') }}</flux:heading>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Canonical records associated with this account.') }}</p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                @foreach ([['orders_created', __('Orders Created')], ['orders_assigned', __('Orders Assigned')], ['payments_received', __('Payments Received')], ['expenses_created', __('Expenses Created')]] as [$key, $label])
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><p class="text-2xl font-bold text-zinc-950 dark:text-white">{{ number_format($activity[$key]) }}</p><p class="mt-1 text-xs text-zinc-500">{{ $label }}</p></div>
                @endforeach
            </div>
        </flux:card>
    </div>
</flux:main>
