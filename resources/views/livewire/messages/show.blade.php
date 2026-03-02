<div
    class="flex flex-col h-[calc(100vh-4rem)]"
    x-data="{
        init() {
            this.scrollToBottom();
            Livewire.on('message-sent', () => {
                this.$nextTick(() => this.scrollToBottom());
            });
        },
        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
    }"
    wire:poll.5s="markAsRead"
>
    {{-- Header --}}
    <div class="flex-shrink-0 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-3">
        <div class="mb-3">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('messages.index')" wire:navigate>{{ __('Messages') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $conversation->getDisplayNameFor(auth()->user()) }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('messages.index') }}" wire:navigate class="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    <x-icon name="arrow_back" class="size-5" />
                </a>

                @if($conversation->isGroup())
                    <div class="size-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                        <x-icon name="group" class="size-5" />
                    </div>
                @else
                    @php
                        $otherUser = $this->participants->where('id', '!=', auth()->id())->first();
                    @endphp
                    <flux:avatar
                        :name="$otherUser?->name ?? 'Unknown'"
                        :initials="$otherUser ? Str::of($otherUser->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') : '?'"
                    />
                @endif

                <div>
                    <h1 class="font-semibold text-zinc-900 dark:text-white">
                        {{ $conversation->getDisplayNameFor(auth()->user()) }}
                    </h1>
                    @if($conversation->isGroup())
                        <button
                            type="button"
                            wire:click="$set('showParticipantsModal', true)"
                            class="text-sm text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                        >
                            {{ $this->participants->count() }} participants
                        </button>
                    @endif
                </div>
            </div>

            @if($conversation->isGroup())
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="$set('showParticipantsModal', true)">
                        <x-icon name="group" class="size-4" />
                    </flux:button>
                    @can('addParticipants', $conversation)
                        <flux:button size="sm" variant="ghost" wire:click="openAddParticipantsModal">
                            <x-icon name="person_add" class="size-4" />
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="flex-shrink-0 px-4 py-2">
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        </div>
    @endif

    {{-- Messages Container --}}
    <div
        x-ref="messagesContainer"
        class="flex-1 overflow-y-auto p-4 space-y-4 bg-zinc-50 dark:bg-zinc-900"
    >
        {{-- Load More Button --}}
        @if($this->hasMoreMessages)
            <div class="text-center py-2">
                <flux:button size="sm" variant="ghost" wire:click="loadMoreMessages">
                    <x-icon name="arrow_upward" class="size-4 mr-1" />
                    Load older messages
                </flux:button>
            </div>
        @endif

        {{-- Messages --}}
        @forelse($this->messages as $message)
            @php
                $isOwn = $message->sender_id === auth()->id();
            @endphp

            <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
                <div class="flex items-end gap-2 max-w-[75%] {{ $isOwn ? 'flex-row-reverse' : '' }}">
                    {{-- Avatar (for others) --}}
                    @if(!$isOwn)
                        <flux:avatar
                            :name="$message->sender->name"
                            :initials="Str::of($message->sender->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')"
                            size="sm"
                            class="flex-shrink-0"
                        />
                    @endif

                    <div>
                        {{-- Sender Name (for group conversations) --}}
                        @if(!$isOwn && $conversation->isGroup())
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 ml-1 mb-0.5 block">
                                {{ $message->sender->name }}
                            </span>
                        @endif

                        {{-- Message Bubble --}}
                        <div class="rounded-2xl px-4 py-2 {{ $isOwn ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white rounded-bl-sm shadow-sm' }}">
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                        </div>

                        {{-- Timestamp --}}
                        <span class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 block {{ $isOwn ? 'text-right' : 'text-left' }}">
                            {{ $message->created_at->format('g:i A') }}
                            @if(!$message->created_at->isToday())
                                · {{ $message->created_at->format('M j') }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center h-full text-center py-12">
                <div class="size-16 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center mb-4">
                    <x-icon name="chat" class="size-8 text-zinc-400" />
                </div>
                <h3 class="font-medium text-zinc-900 dark:text-white mb-1">No messages yet</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Send a message to start the conversation
                </p>
            </div>
        @endforelse
    </div>

    {{-- Compose Box --}}
    <div class="flex-shrink-0 border-t border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
        <form wire:submit="sendMessage" class="flex items-end gap-3">
            <div class="flex-1">
                <textarea
                    wire:model="messageBody"
                    placeholder="Type a message..."
                    rows="1"
                    class="w-full resize-none rounded-xl border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-indigo-500 focus:ring-indigo-500"
                    x-data="{
                        resize() {
                            $el.style.height = 'auto';
                            $el.style.height = Math.min($el.scrollHeight, 150) + 'px';
                        }
                    }"
                    x-on:input="resize()"
                    x-on:keydown.enter.prevent="
                        if (!$event.shiftKey) {
                            $wire.sendMessage();
                        } else {
                            $el.value += '\n';
                            resize();
                        }
                    "
                ></textarea>
                @error('messageBody')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            <flux:button type="submit" variant="primary" class="flex-shrink-0">
                <x-icon name="send" class="size-5" />
            </flux:button>
        </form>
    </div>

    {{-- Participants Modal --}}
    <flux:modal wire:model="showParticipantsModal" class="max-w-md">
        <flux:card>
            <flux:heading size="lg" class="mb-4">Participants</flux:heading>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700 max-h-96 overflow-y-auto">
                @foreach($this->participants as $participant)
                    <div class="flex items-center gap-3 py-3">
                        <flux:avatar
                            :name="$participant->name"
                            :initials="Str::of($participant->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')"
                            size="sm"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-white truncate">
                                {{ $participant->name }}
                                @if($participant->id === auth()->id())
                                    <span class="text-zinc-500 dark:text-zinc-400">(You)</span>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400 truncate">
                                {{ $participant->email }}
                            </div>
                        </div>
                        @if($participant->id === $conversation->created_by)
                            <span class="text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2 py-1 rounded-full">
                                Creator
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end mt-4">
                <flux:button variant="ghost" wire:click="$set('showParticipantsModal', false)">
                    Close
                </flux:button>
            </div>
        </flux:card>
    </flux:modal>

    {{-- Add Participants Modal --}}
    <flux:modal wire:model="showAddParticipantsModal" class="max-w-md">
        <flux:card>
            <flux:heading size="lg" class="mb-4">Add Participants</flux:heading>

            @if($this->availableUsersToAdd->isEmpty())
                <p class="text-zinc-500 dark:text-zinc-400 text-center py-4">
                    All branch users are already in this conversation.
                </p>
            @else
                <div class="max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->availableUsersToAdd as $user)
                        <label class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:click="toggleUserSelection({{ $user->id }})"
                                @checked(in_array($user->id, $selectedUserIds))
                                class="rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <flux:avatar
                                :name="$user->name"
                                :initials="Str::of($user->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')"
                                size="sm"
                            />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('selectedUserIds')
                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                @enderror

                @if(count($selectedUserIds) > 0)
                    <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                        {{ count($selectedUserIds) }} user(s) selected
                    </div>
                @endif
            @endif

            <div class="flex justify-end gap-2 mt-4">
                <flux:button variant="ghost" wire:click="$set('showAddParticipantsModal', false)">
                    Cancel
                </flux:button>
                @if($this->availableUsersToAdd->isNotEmpty())
                    <flux:button variant="primary" wire:click="addParticipants">
                        Add Selected
                    </flux:button>
                @endif
            </div>
        </flux:card>
    </flux:modal>
</div>
