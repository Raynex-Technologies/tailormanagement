<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item>Messages</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-6">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="mb-6">
            {{ session('error') }}
        </flux:callout>
    @endif

    <flux:card>
        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <flux:heading size="lg">Messages</flux:heading>

            <div class="flex items-center gap-2">
                @can('createGroup', App\Models\Conversation::class)
                    <flux:button wire:click="openNewGroupModal" variant="primary" size="sm">
                        <x-icon name="group" class="mr-1 size-4" />
                        New Group
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Search Users to Start Chat --}}
        <div class="mb-6 relative">
            <flux:input
                wire:model.blur="userSearch"
                placeholder="Search users to start a conversation..."
                icon="magnifying-glass"
            />

            {{-- User Search Results Dropdown --}}
            @if($this->searchableUsers->isNotEmpty())
                <div class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 max-h-64 overflow-y-auto">
                    @foreach($this->searchableUsers as $user)
                        <button
                            type="button"
                            wire:click="startDirectChat({{ $user->id }})"
                            class="w-full px-4 py-3 text-left hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center gap-3 transition-colors"
                        >
                            <flux:avatar :name="$user->name" :initials="Str::of($user->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')" size="sm" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif(strlen($userSearch) >= 2)
                <div class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-4 text-center text-zinc-500 dark:text-zinc-400">
                    No users found matching "{{ $userSearch }}"
                </div>
            @endif
        </div>

        {{-- Search Conversations --}}
        <div class="mb-6">
            <flux:input
                wire:model.blur="search"
                placeholder="Search conversations..."
                icon="magnifying-glass"
            />
        </div>

        {{-- Conversations List --}}
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($this->conversations as $conversation)
                @php
                    $unreadCount = $this->getUnreadCountFor($conversation);
                    $displayName = $conversation->getDisplayNameFor(auth()->user());
                    $latestMessage = $conversation->latestMessage;
                @endphp

                <a
                    href="{{ route('messages.show', $conversation) }}"
                    wire:navigate
                    class="flex items-center gap-4 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $unreadCount > 0 ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                >
                    {{-- Avatar --}}
                    <div class="relative flex-shrink-0">
                        @if($conversation->isGroup())
                            <div class="size-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                                <x-icon name="group" class="size-6" />
                            </div>
                        @else
                            @php
                                $otherUser = $conversation->users->where('id', '!=', auth()->id())->first();
                            @endphp
                            <flux:avatar
                                :name="$otherUser?->name ?? 'Unknown'"
                                :initials="$otherUser ? Str::of($otherUser->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') : '?'"
                                size="lg"
                            />
                        @endif

                        {{-- Unread Badge --}}
                        @if($unreadCount > 0)
                            <span class="absolute -top-1 -right-1 size-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-zinc-900 dark:text-white truncate {{ $unreadCount > 0 ? 'font-bold' : '' }}">
                                {{ $displayName }}
                            </h3>
                            @if($latestMessage)
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                    {{ $latestMessage->created_at->shortRelativeDiffForHumans() }}
                                </span>
                            @elseif($conversation->created_at)
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                    {{ $conversation->created_at->shortRelativeDiffForHumans() }}
                                </span>
                            @endif
                        </div>
                        @if($latestMessage)
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 truncate mt-0.5 {{ $unreadCount > 0 ? 'font-medium' : '' }}">
                                @if($latestMessage->sender_id === auth()->id())
                                    <span class="text-zinc-400 dark:text-zinc-500">You: </span>
                                @elseif($conversation->isGroup())
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $latestMessage->sender->name }}: </span>
                                @endif
                                {{ Str::limit($latestMessage->body, 50) }}
                            </p>
                        @else
                            <p class="text-sm text-zinc-400 dark:text-zinc-500 italic">No messages yet</p>
                        @endif
                    </div>

                    {{-- Arrow --}}
                    <x-icon name="chevron_right" class="size-5 text-zinc-400" />
                </a>
            @empty
                <div class="p-12 text-center">
                    <div class="mx-auto size-16 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
                        <x-icon name="chat" class="size-8 text-zinc-400" />
                    </div>
                    <h3 class="font-medium text-zinc-900 dark:text-white mb-1">No conversations yet</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Search for a user above to start messaging
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($this->conversations->hasPages())
            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                {{ $this->conversations->links() }}
            </div>
        @endif
    </flux:card>

    {{-- New Group Modal --}}
    <flux:modal wire:model="showNewGroupModal" class="max-w-lg">
        <flux:card>
            <flux:heading size="lg" class="mb-4">Create Group Chat</flux:heading>

            <form wire:submit="createGroup" class="space-y-4">
                <flux:input
                    wire:model="groupTitle"
                    label="Group Name"
                    placeholder="Enter group name..."
                    required
                />
                @error('groupTitle') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                <div>
                    <flux:label class="mb-2">Select Participants</flux:label>
                    <div class="max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($this->branchUsers as $user)
                            <label class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    wire:click="toggleUserSelection({{ $user->id }})"
                                    @checked(in_array($user->id, $selectedUserIds))
                                    class="rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 focus:ring-indigo-500"
                                />
                                <flux:avatar :name="$user->name" :initials="Str::of($user->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')" size="sm" />
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedUserIds') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                @if(count($selectedUserIds) > 0)
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ count($selectedUserIds) }} participant(s) selected
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="$set('showNewGroupModal', false)">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Create Group
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </flux:modal>
</flux:main>
