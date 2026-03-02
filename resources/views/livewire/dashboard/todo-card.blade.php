<div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
    {{-- Header --}}
    <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('My Tasks') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Personal to-do list') }}</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Sort Dropdown --}}
                <flux:dropdown position="bottom" align="end">
                    <button class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fa-duotone fa-arrow-down-arrow-up size-3.5"></i>
                        {{ $sortBy === 'priority' ? __('Priority') : ($sortBy === 'time' ? __('Due Time') : __('Recent')) }}
                    </button>
                    <flux:menu class="w-36">
                        <flux:menu.item wire:click="setSortBy('created')" class="{{ $sortBy === 'created' ? 'bg-lime-50 dark:bg-lime-900/20' : '' }}">
                            {{ __('Recent') }}
                        </flux:menu.item>
                        <flux:menu.item wire:click="setSortBy('priority')" class="{{ $sortBy === 'priority' ? 'bg-lime-50 dark:bg-lime-900/20' : '' }}">
                            {{ __('Priority') }}
                        </flux:menu.item>
                        <flux:menu.item wire:click="setSortBy('time')" class="{{ $sortBy === 'time' ? 'bg-lime-50 dark:bg-lime-900/20' : '' }}">
                            {{ __('Due Time') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                {{-- Add Task Button --}}
                <button
                    wire:click="openModal"
                    class="flex items-center justify-center size-9 rounded-xl transition-all hover:shadow-lg"
                    style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;"
                >
                    <i class="fa-duotone fa-plus size-5"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="p-4">
        {{-- Task List --}}
        <div class="space-y-2 custom-scrollbar-light max-h-96 overflow-y-auto pr-1" wire:poll.30s>
            @forelse ($todos as $todo)
                @php
                    $colors = $todo->getPriorityColors();
                    $remaining = $todo->getRemainingTime();
                    $remainingSeconds = $todo->getRemainingSeconds();
                    $isOverdue = $todo->isOverdue();
                @endphp
                <div
                    class="group flex items-start gap-3 rounded-xl px-3 py-3 transition-all border {{ $todo->is_done ? 'opacity-50 border-transparent' : ($isOverdue ? 'border-red-200 dark:border-red-800/50 bg-red-50/50 dark:bg-red-900/10' : 'border-transparent hover:bg-zinc-50 dark:hover:bg-zinc-700/30') }}"
                    wire:key="todo-{{ $todo->id }}"
                >
                    {{-- Checkbox --}}
                    <button
                        wire:click="toggleDone({{ $todo->id }})"
                        class="flex items-center justify-center size-5 shrink-0 rounded-md border-2 transition-all mt-1
                            {{ $todo->is_done
                                ? 'border-lime-500 bg-lime-500 text-white shadow-sm'
                                : $colors['border'].' hover:border-lime-400' }}"
                        style="{{ $todo->is_done ? 'box-shadow: 0 2px 8px rgba(163, 230, 53, 0.3);' : '' }}"
                    >
                        @if ($todo->is_done)
                            <i class="fa-duotone fa-check size-3"></i>
                        @endif
                    </button>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Title (Bold, at top) --}}
                        <p class="text-sm font-bold {{ $todo->is_done ? 'text-zinc-400 line-through dark:text-zinc-500' : 'text-zinc-900 dark:text-white' }}">
                            {{ $todo->title }}
                        </p>

                        {{-- Countdown / Due Time (below title) --}}
                        @if ($remaining && !$todo->is_done)
                            <div class="mt-1 flex items-center gap-1.5">
                                <i class="fa-duotone fa-clock size-3.5 {{ $isOverdue ? 'text-red-500' : 'text-zinc-400' }}"></i>
                                @if(!$isOverdue && $remainingSeconds && $remainingSeconds < 3600)
                                    {{-- Live countdown for tasks under 1 hour --}}
                                    <span
                                        class="text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                        x-data="{
                                            remaining: {{ $remainingSeconds }},
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
                                    ></span>
                                @else
                                    {{-- Static time display --}}
                                    <span class="text-xs font-medium {{ $isOverdue ? 'text-red-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                                        {{ $remaining }}
                                    </span>
                                @endif
                            </div>
                        @endif

                    </div>

                    {{-- Priority Badge (right side) --}}
                    <div class="shrink-0 flex flex-col items-end gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wide {{ $colors['bg'] }} {{ $colors['text'] }}">
                            <span class="size-1.5 rounded-full {{ $colors['dot'] }}"></span>
                            {{ $todo->priority->label() }}
                        </span>

                        {{-- Actions --}}
                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                wire:click="editTask({{ $todo->id }})"
                                class="flex items-center justify-center size-6 rounded-lg text-zinc-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                            >
                                <i class="fa-duotone fa-pen-to-square size-3.5"></i>
                            </button>
                            <button
                                wire:click="deleteTask({{ $todo->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this task?') }}"
                                class="flex items-center justify-center size-6 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all"
                            >
                                <i class="fa-duotone fa-xmark size-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-10 text-center">
                    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3" style="background: rgba(163, 230, 53, 0.1);">
                        <i class="fa-duotone fa-circle-check size-7" style="color: #84CC16;"></i>
                    </div>
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('All caught up!') }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Click + to add a task') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Task count footer --}}
        @if ($todos->count() > 0)
            <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-700/50 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ $todos->where('is_done', false)->count() }} {{ __('remaining') }}</span>
                <span>{{ $todos->where('is_done', true)->count() }} {{ __('completed') }}</span>
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
                            + {{ __('New Category') }}
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
                    <flux:textarea wire:model="note" placeholder="{{ __('Additional notes...') }}" rows="2" />
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

    {{-- Add Category Modal --}}
    <flux:modal wire:model="showCategoryModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New Category') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a category to organize your tasks.') }}</flux:text>
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

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeCategoryModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Create Category') }}
                    </flux:button>
                </div>
            </form>

            {{-- Existing Categories --}}
            @if ($this->categories->count() > 0)
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Your Categories') }}</p>
                    <div class="space-y-1">
                        @foreach ($this->categories as $cat)
                            @php $catColors = $cat->getColorClasses(); @endphp
                            <div class="flex items-center justify-between px-2 py-1.5 rounded-lg {{ $catColors['bg'] }}">
                                <span class="flex items-center gap-2 text-sm {{ $catColors['text'] }}">
                                    <span class="size-2 rounded-full {{ $catColors['dot'] }}"></span>
                                    {{ $cat->name }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="deleteCategory({{ $cat->id }})"
                                    wire:confirm="{{ __('Delete this category?') }}"
                                    class="text-zinc-400 hover:text-red-500"
                                >
                                    <i class="fa-duotone fa-xmark size-4"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
