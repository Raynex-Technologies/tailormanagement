@can('messages.use')
<a
    href="{{ route('messages.index') }}"
    wire:navigate
    wire:poll.10s
    class="relative inline-flex items-center justify-center rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200 transition-colors"
    title="Messages"
>
    <flux:icon name="chat-bubble-left-right" class="size-5" />

    @if($this->unreadCount > 0)
        <span class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white ring-2 ring-white dark:ring-zinc-800">
            {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
        </span>
    @endif
</a>
@endcan
