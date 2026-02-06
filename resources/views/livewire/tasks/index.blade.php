<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('My Tasks') }}</h1>
            <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Manage your personal to-do list and categories.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button
                wire:click="openCategoryModal"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                </svg>
                {{ __('Categories') }}
            </button>
            <button
                wire:click="openModal"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all hover:shadow-lg"
                style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add Task') }}
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Tasks') }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                    <svg class="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['pending'] }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(163, 230, 53, 0.15);">
                    <svg class="size-5" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['completed'] }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-red-50 dark:bg-red-900/30">
                    <svg class="size-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['overdue'] }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Overdue') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Search --}}
            <div class="relative flex-1 max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search tasks...') }}"
                    class="block w-full rounded-xl border-0 bg-zinc-100 dark:bg-zinc-800 py-2.5 pl-10 pr-3 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-zinc-700 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:bg-white dark:focus:bg-zinc-700 focus:ring-2 focus:ring-lime-400"
                />
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap items-center gap-3">
                {{-- Status Filter --}}
                <select
                    wire:model.live="filterStatus"
                    class="rounded-xl border-0 bg-zinc-100 dark:bg-zinc-800 py-2 pl-3 pr-8 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-zinc-700 focus:ring-2 focus:ring-lime-400"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="completed">{{ __('Completed') }}</option>
                </select>

                {{-- Category Filter --}}
                <select
                    wire:model.live="filterCategory"
                    class="rounded-xl border-0 bg-zinc-100 dark:bg-zinc-800 py-2 pl-3 pr-8 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-zinc-700 focus:ring-2 focus:ring-lime-400"
                >
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>

                {{-- Priority Filter --}}
                <select
                    wire:model.live="filterPriority"
                    class="rounded-xl border-0 bg-zinc-100 dark:bg-zinc-800 py-2 pl-3 pr-8 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-zinc-700 focus:ring-2 focus:ring-lime-400"
                >
                    <option value="">{{ __('All Priorities') }}</option>
                    @foreach ($this->priorities as $p)
                        <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>

                {{-- Sort --}}
                <select
                    wire:model.live="sortBy"
                    class="rounded-xl border-0 bg-zinc-100 dark:bg-zinc-800 py-2 pl-3 pr-8 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-zinc-700 focus:ring-2 focus:ring-lime-400"
                >
                    <option value="created">{{ __('Sort: Recent') }}</option>
                    <option value="priority">{{ __('Sort: Priority') }}</option>
                    <option value="time">{{ __('Sort: Due Time') }}</option>
                </select>

                @if ($this->stats['completed'] > 0)
                    <button
                        wire:click="clearCompleted"
                        wire:confirm="{{ __('Delete all completed tasks?') }}"
                        class="text-sm text-red-500 hover:text-red-600 font-medium"
                    >
                        {{ __('Clear Completed') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Tasks List --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
        <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
            @forelse ($todos as $todo)
                @php
                    $colors = $todo->getPriorityColors();
                    $remaining = $todo->getRemainingTime();
                    $isOverdue = $todo->isOverdue();
                @endphp
                <div
                    class="group flex items-start gap-4 p-4 transition-all {{ $todo->is_done ? 'opacity-50 bg-zinc-50 dark:bg-zinc-800/30' : ($isOverdue ? 'bg-red-50/50 dark:bg-red-900/10' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50') }}"
                    wire:key="task-{{ $todo->id }}"
                >
                    {{-- Checkbox --}}
                    <button
                        wire:click="toggleDone({{ $todo->id }})"
                        class="flex items-center justify-center size-6 shrink-0 rounded-lg border-2 transition-all mt-0.5
                            {{ $todo->is_done
                                ? 'border-lime-500 bg-lime-500 text-white'
                                : $colors['border'].' hover:border-lime-400' }}"
                    >
                        @if ($todo->is_done)
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @endif
                    </button>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            {{-- Priority Badge --}}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold {{ $colors['bg'] }} {{ $colors['text'] }}">
                                <span class="size-1.5 rounded-full {{ $colors['dot'] }}"></span>
                                {{ $todo->priority->label() }}
                            </span>

                            {{-- Category Badge --}}
                            @if ($todo->category)
                                @php $catColors = $todo->category->getColorClasses(); @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium {{ $catColors['bg'] }} {{ $catColors['text'] }}">
                                    <span class="size-1.5 rounded-full {{ $catColors['dot'] }}"></span>
                                    {{ $todo->category->name }}
                                </span>
                            @endif

                            {{-- Due Time / Countdown --}}
                            @if ($remaining && !$todo->is_done)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium {{ $isOverdue ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}"
                                      @if(!$isOverdue && $todo->getRemainingSeconds() && $todo->getRemainingSeconds() < 3600)
                                          x-data="{ 
                                              remaining: {{ $todo->getRemainingSeconds() }},
                                              formatTime(seconds) {
                                                  if (seconds <= 0) return 'Overdue';
                                                  const h = Math.floor(seconds / 3600);
                                                  const m = Math.floor((seconds % 3600) / 60);
                                                  const s = seconds % 60;
                                                  if (h > 0) return h + 'h ' + m + 'm';
                                                  if (m > 0) return m + 'm ' + s + 's';
                                                  return s + 's';
                                              }
                                          }"
                                          x-init="setInterval(() => { remaining = Math.max(0, remaining - 1) }, 1000)"
                                          x-text="formatTime(remaining)"
                                      @endif
                                >
                                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ $remaining }}
                                </span>
                            @endif
                        </div>

                        {{-- Title --}}
                        <h3 class="text-base font-medium {{ $todo->is_done ? 'text-zinc-400 line-through dark:text-zinc-500' : 'text-zinc-900 dark:text-white' }}">
                            {{ $todo->title }}
                        </h3>

                        {{-- Note --}}
                        @if ($todo->note)
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 {{ $todo->is_done ? 'line-through' : '' }}">
                                {{ $todo->note }}
                            </p>
                        @endif

                        {{-- Meta --}}
                        <div class="mt-2 flex items-center gap-3 text-xs text-zinc-400">
                            <span>{{ __('Created') }} {{ $todo->created_at->diffForHumans() }}</span>
                            @if ($todo->done_at)
                                <span>• {{ __('Completed') }} {{ $todo->done_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            wire:click="editTask({{ $todo->id }})"
                            class="flex items-center justify-center size-8 rounded-lg text-zinc-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <button
                            wire:click="deleteTask({{ $todo->id }})"
                            wire:confirm="{{ __('Delete this task?') }}"
                            class="flex items-center justify-center size-8 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="flex items-center justify-center size-16 rounded-2xl mx-auto mb-4" style="background: rgba(163, 230, 53, 0.1);">
                        <svg class="size-8" style="color: #84CC16;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('No tasks found') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        @if ($search || $filterCategory || $filterPriority || $filterStatus !== 'all')
                            {{ __('Try adjusting your filters or search.') }}
                        @else
                            {{ __('Click "Add Task" to create your first task.') }}
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($todos->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                {{ $todos->links() }}
            </div>
        @endif
    </div>

    {{-- Add/Edit Task Modal --}}
    <flux:modal wire:model="showModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTaskId ? __('Edit Task') : __('Add New Task') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a task with priority and due time.') }}</flux:text>
            </div>

            <form wire:submit="saveTask" class="space-y-4">
                {{-- Task Title --}}
                <flux:field>
                    <flux:label>{{ __('Task Name') }} *</flux:label>
                    <flux:input wire:model="title" placeholder="{{ __('Enter task name...') }}" maxlength="140" />
                    <flux:error name="title" />
                </flux:field>

                {{-- Category --}}
                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Category') }}</flux:label>
                        <button type="button" wire:click="openCategoryModal" class="text-xs font-medium text-lime-600 hover:text-lime-700">
                            + {{ __('New') }}
                        </button>
                    </div>
                    <flux:select wire:model="categoryId">
                        <flux:select.option value="">{{ __('No category') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                {{-- Priority --}}
                <flux:field>
                    <flux:label>{{ __('Priority') }}</flux:label>
                    <flux:select wire:model="priority">
                        @foreach ($this->priorities as $p)
                            <flux:select.option value="{{ $p['value'] }}">{{ $p['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                {{-- Due Date & Time --}}
                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>{{ __('Due Date') }}</flux:label>
                        <flux:input type="date" wire:model="dueDate" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Time') }}</flux:label>
                        <flux:input type="time" wire:model="dueTime" />
                    </flux:field>
                </div>

                {{-- Note --}}
                <flux:field>
                    <flux:label>{{ __('Note') }}</flux:label>
                    <flux:textarea wire:model="note" placeholder="{{ __('Additional notes...') }}" rows="3" />
                    <flux:error name="note" />
                </flux:field>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingTaskId ? __('Update Task') : __('Add Task') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Categories Modal --}}
    <flux:modal wire:model="showCategoryModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Manage Categories') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create and manage your task categories.') }}</flux:text>
            </div>

            <form wire:submit="saveCategory" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Category Name') }} *</flux:label>
                    <flux:input wire:model="categoryName" placeholder="{{ __('e.g., Work, Personal...') }}" maxlength="50" />
                    <flux:error name="categoryName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Color') }}</flux:label>
                    <div class="grid grid-cols-6 gap-2 mt-2">
                        @foreach ($this->categoryColors as $colorKey => $colorName)
                            <button
                                type="button"
                                wire:click="$set('categoryColor', '{{ $colorKey }}')"
                                class="size-8 rounded-lg transition-all {{ $categoryColor === $colorKey ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-zinc-800' : 'hover:scale-110' }}"
                                style="background-color: var(--color-{{ $colorKey }}-500, #71717a);"
                                title="{{ $colorName }}"
                            ></button>
                        @endforeach
                    </div>
                </flux:field>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm">
                        {{ __('Add Category') }}
                    </flux:button>
                </div>
            </form>

            {{-- Existing Categories --}}
            @if ($this->categories->count() > 0)
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-3">{{ __('Your Categories') }}</p>
                    <div class="space-y-2">
                        @foreach ($this->categories as $cat)
                            @php $catColors = $cat->getColorClasses(); @endphp
                            <div class="flex items-center justify-between px-3 py-2 rounded-xl {{ $catColors['bg'] }}">
                                <span class="flex items-center gap-2 text-sm font-medium {{ $catColors['text'] }}">
                                    <span class="size-2.5 rounded-full {{ $catColors['dot'] }}"></span>
                                    {{ $cat->name }}
                                    <span class="text-xs opacity-60">({{ $cat->todos_count }})</span>
                                </span>
                                <button
                                    type="button"
                                    wire:click="deleteCategory({{ $cat->id }})"
                                    wire:confirm="{{ __('Delete this category? Tasks will be uncategorized.') }}"
                                    class="text-zinc-400 hover:text-red-500 transition-colors"
                                >
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button type="button" variant="ghost" wire:click="closeCategoryModal">
                    {{ __('Done') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
