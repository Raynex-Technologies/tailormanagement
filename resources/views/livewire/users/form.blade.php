<flux:main class="p-0">
    <section class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);" data-theme-hero data-user-form-header>
        <flux:breadcrumbs class="mb-5 text-white/70">
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
            <flux:breadcrumbs.item :href="route('users.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item class="!text-white">{{ $isEdit ? __('Edit') : __('New') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" class="!text-white">{{ $isEdit ? __('Edit User') : __('New User') }}</flux:heading>
        <p class="mt-1 text-sm text-white/70">
            {{ $isEdit ? $name : __('Create a staff account and assign its system access.') }}
        </p>
        @if ($isEdit)<p class="mt-0.5 text-sm text-white/60">{{ $email }}</p>@endif
    </section>

    @if (session('error'))<flux:callout class="mb-4" variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>@endif

    <form wire:submit="save" class="mx-auto max-w-5xl space-y-6">
        <flux:card>
            <div class="mb-5">
                <flux:heading size="lg">{{ $isEdit ? __('Profile') : __('Account information') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('The details used to identify this staff account.') }}</flux:text>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="name" id="name" label="{{ __('Full Name') }}" placeholder="{{ __('Full name') }}" required />
                <flux:input wire:model="email" id="email" type="email" label="{{ __('Email') }}" placeholder="email@example.com" required />
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Access') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('The assigned business role is the source of module access.') }}</flux:text>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select id="role" wire:model.live="role" label="{{ __('Role') }}" required>
                    <flux:select.option value="">{{ __('Select a role') }}</flux:select.option>
                    @foreach ($assignableRoles as $r)<flux:select.option value="{{ $r }}">{{ str($r)->replace('_', ' ')->title() }}</flux:select.option>@endforeach
                </flux:select>
                @if ($showBranchSelector)
                    <div>
                        <flux:select id="branch_id" wire:model="branch_id" label="{{ __('Branch / Access Scope') }}" :required="$roleRequiresBranch">
                            <flux:select.option value="">{{ $roleRequiresBranch ? __('Select a branch') : __('Global access (no branch)') }}</flux:select.option>
                            @foreach ($branches as $branch)<flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>@endforeach
                        </flux:select>
                        <p class="mt-1.5 text-xs text-zinc-500">{{ $roleRequiresBranch ? __('This role operates within the selected branch.') : __('Global administration roles may operate without a branch.') }}</p>
                    </div>
                @else
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Branch access') }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ auth()->user()->branch?->name ?? __('Your assigned branch') }}</p>
                    </div>
                @endif
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-5">
                <flux:heading size="lg">{{ __('Security') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ $isEdit ? __('Leave password changes off to keep the current password.') : __('Set a secure password for the new account.') }}</flux:text>
            </div>
            @if ($isEdit)
                <flux:checkbox wire:model.live="resetPassword" label="{{ __('Change password') }}" description="{{ __('Enable only when this user needs a new password.') }}" />
            @endif
            @if (! $isEdit || $resetPassword)
                <div class="{{ $isEdit ? 'mt-5 ' : '' }}grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="password" id="password" type="password" viewable label="{{ __('Password') }}" description="{{ __('At least 8 characters with mixed case and numbers.') }}" required />
                    <flux:input wire:model="password_confirmation" id="password_confirmation" type="password" viewable label="{{ __('Confirm Password') }}" required />
                </div>
            @endif
        </flux:card>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button type="button" variant="ghost" :href="$isEdit ? route('users.show', $user) : route('users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $isEdit ? __('Update User') : __('Create User') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
            </flux:button>
        </div>
    </form>
</flux:main>
