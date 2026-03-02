@can('messages.use')
<a
    href="{{ route('messages.index') }}"
    wire:navigate
    wire:poll.10s
    class="relative flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/50 dark:hover:text-zinc-200"
    title="Messages"
    aria-label="Messages"
>
    <i class="fa-duotone fa-message-dots text-lg"></i>

    @if($this->unreadCount > 0)
        <span class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white ring-2 ring-white dark:ring-zinc-800">
            {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
        </span>
    @endif
</a>
@endcan
