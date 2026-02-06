<div class="relative" x-data="{ open: @entangle('isOpen') }">
    {{-- Notification Bell Button --}}
    <button
        wire:click="toggle"
        class="relative flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/50 dark:hover:text-zinc-200"
        aria-label="Notifications"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

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
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800 sm:w-96"
        style="display: none;"
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
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 {{ $notification->read_at ? 'text-zinc-500' : 'text-indigo-600 dark:text-indigo-400' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
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
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">No notifications yet</p>
                </div>
            @endforelse
        </div>

        {{-- Footer (optional - for future link to all notifications) --}}
        @if($this->notifications->count() > 0)
            <div class="border-t border-zinc-100 p-2 dark:border-zinc-700">
                <button class="w-full rounded-lg py-2 text-center text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-700/50 dark:hover:text-white">
                    View all notifications
                </button>
            </div>
        @endif
    </div>
</div>
