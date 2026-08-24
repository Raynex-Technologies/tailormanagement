<div
    class="relative z-[60]"
    x-data="{
        open: @entangle('isOpen'),
        browserPermission: 'checking',
        syncBrowserPermission() {
            this.browserPermission = typeof window.Notification === 'undefined'
                ? 'unsupported'
                : window.Notification.permission;
        },
        async requestBrowserPermission() {
            if (typeof window.Notification === 'undefined') {
                this.browserPermission = 'unsupported';
                return;
            }

            this.browserPermission = await window.Notification.requestPermission();
        },
    }"
    x-init="syncBrowserPermission()"
    @visibilitychange.window="syncBrowserPermission()"
    data-notification-bell
>
    {{-- Notification Bell Button --}}
    <button
        wire:click="toggle"
        class="relative flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/50 dark:hover:text-zinc-200"
        aria-label="Notifications"
        aria-haspopup="dialog"
        :aria-expanded="open.toString()"
    >
        <i class="fa-duotone fa-bell text-lg"></i>

        {{-- Unread Badge --}}
        @if($this->unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-xs font-semibold text-white">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Notification Dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="$wire.close()"
        class="absolute right-0 z-[70] mt-2 w-[min(24rem,calc(100vw-1.5rem))] origin-top-right rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
        style="display: none;"
        role="dialog"
        aria-label="{{ __('Recent notifications') }}"
        data-notification-panel
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
            <h3 class="font-semibold text-zinc-900 dark:text-white">Notifications</h3>
            @if($this->unreadCount > 0)
                <button
                    wire:click="markAllAsRead"
                    class="text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Mark all as read
                </button>
            @endif
        </div>

        {{-- Notifications List --}}
        <div class="max-h-96 overflow-y-auto">
            @forelse($this->notifications as $notification)
                <div
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex cursor-pointer items-start gap-3 border-b border-zinc-50 px-4 py-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700/50 dark:hover:bg-zinc-700/30 {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    {{-- Icon --}}
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-zinc-100 dark:bg-zinc-700' : 'bg-indigo-100 dark:bg-indigo-900/50' }}">
                        <i class="fa-duotone fa-circle-info text-lg {{ $notification->read_at ? 'text-zinc-500' : 'text-indigo-600 dark:text-indigo-400' }}"></i>
                    </div>

                    {{-- Content --}}
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $notification->data['title'] ?? 'Notification' }}
                        </p>
                        <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $notification->data['message'] ?? '' }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Unread Indicator --}}
                    @unless($notification->read_at)
                        <div class="h-2 w-2 shrink-0 rounded-full bg-indigo-500"></div>
                    @endunless
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <i class="fa-duotone fa-bell mx-auto text-4xl text-zinc-300 dark:text-zinc-600"></i>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">No notifications yet</p>
                </div>
            @endforelse
        </div>

        <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-700" data-browser-notification-permission>
            <template x-if="browserPermission === 'default'">
                <button
                    type="button"
                    @click="requestBrowserPermission()"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-lime-400 px-3 py-2 text-sm font-semibold text-navy-900 transition hover:bg-lime-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 focus-visible:ring-offset-2 dark:ring-offset-zinc-800"
                >
                    <i class="fa-duotone fa-bell-on" aria-hidden="true"></i>
                    {{ __('Enable browser notifications') }}
                </button>
            </template>

            <p x-cloak x-show="browserPermission === 'granted'" class="text-sm text-emerald-700 dark:text-emerald-300" role="status">
                <i class="fa-duotone fa-circle-check mr-1" aria-hidden="true"></i>
                {{ __('Browser notifications enabled') }}
            </p>
            <p x-cloak x-show="browserPermission === 'denied'" class="text-sm text-amber-700 dark:text-amber-300" role="status">
                {{ __('Browser notifications are blocked. Enable them from your browser/site settings.') }}
            </p>
            <p x-cloak x-show="browserPermission === 'unsupported'" class="text-sm text-zinc-500 dark:text-zinc-400" role="status">
                {{ __('This browser does not support browser notifications.') }}
            </p>
        </div>

        {{-- Compact dropdown only; there is currently no dedicated notification index. --}}
        @if($this->notifications->count() > 0)
            <div class="border-t border-zinc-100 px-4 py-2.5 text-center text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('Showing the 8 most recent notifications') }}
            </div>
        @endif
    </div>
</div>
