<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Dashboard') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($canViewDashboard)
        {{-- Branch Selection Banner (for global admins without branch context) --}}
        @if ($stats['needs_branch_selection'])
            <div class="rounded-2xl p-4 border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-amber-100 dark:bg-amber-900/50 shrink-0">
                        <i class="fa-duotone fa-triangle-exclamation size-5 text-amber-500"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-amber-700 dark:text-amber-300">{{ __('No branch selected') }}</p>
                        <p class="text-sm text-amber-600 dark:text-amber-400">{{ __('Select a branch from the sidebar to view dashboard statistics.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Header Section --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12
                        ? __('Good morning')
                        : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
                    $firstName = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();
                @endphp
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __("Here's what's happening with your business today.") }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($currentBranch)
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-lime-100 dark:bg-lime-900/30 px-3 py-1.5 text-sm font-medium text-lime-700 dark:text-lime-400">
                        <i class="fa-duotone fa-building size-4"></i>
                        {{ $currentBranch->name }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                    <i class="fa-duotone fa-calendar size-4"></i>
                    {{ now()->format('M j, Y') }}
                </span>
            </div>
        </div>

        {{-- KPI Overview Cards --}}
        @if (!$stats['needs_branch_selection'])
        @php
            $monthRevenue  = $stats['sales']['payments_month_sum'];
            $monthExpenses = $stats['expenses']['expenses_month_sum'];
            $fmt = fn($v) => $v >= 1_000_000
                ? number_format($v / 1_000_000, 1) . 'M'
                : ($v >= 1_000 ? number_format($v / 1_000, 0) . 'K' : number_format($v, 0));
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Total Orders --}}
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30">
                        <i class="fa-duotone fa-bag-shopping size-5 text-indigo-500"></i>
                    </div>
                    @if ($stats['orders']['new_orders_count'] > 0)
                        <span class="inline-flex items-center rounded-lg bg-lime-100 dark:bg-lime-900/30 px-2 py-0.5 text-xs font-semibold text-lime-700 dark:text-lime-400">
                            +{{ $stats['orders']['new_orders_count'] }} {{ __('new') }}
                        </span>
                    @endif
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($stats['orders']['total_orders_count']) }}</p>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Orders') }}</p>
                </div>
            </div>

            {{-- Active Orders --}}
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-violet-50 dark:bg-violet-900/30">
                        <i class="fa-duotone fa-clock size-5 text-violet-500"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($stats['orders']['in_progress_orders_count']) }}</p>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active Orders') }}</p>
                </div>
            </div>

            {{-- Revenue This Month --}}
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                        <i class="fa-duotone fa-arrow-trend-up size-5 text-emerald-500"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $fmt($monthRevenue) }} <span class="text-sm font-normal text-zinc-400 dark:text-zinc-500">TZS</span>
                    </p>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Revenue This Month') }}</p>
                </div>
            </div>

            {{-- Expenses This Month --}}
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex items-start justify-between">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-rose-50 dark:bg-rose-900/30">
                        <i class="fa-duotone fa-receipt size-5 text-rose-500"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $fmt($monthExpenses) }} <span class="text-sm font-normal text-zinc-400 dark:text-zinc-500">TZS</span>
                    </p>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expenses This Month') }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Main Content Grid --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: KPI Stats --}}
            <div class="space-y-6 lg:col-span-2 min-w-0">
                <livewire:dashboard.order-progress-card />

                <livewire:dashboard.income-expenses-chart-card />

                {{-- Alerts Row --}}
                @if ($stats['inventory']['low_stock_count'] > 0 || $stats['procurement']['pending_purchase_requests_count'] > 0)
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Low Stock Alert --}}
                    @if ($stats['inventory']['low_stock_count'] > 0)
                        <div class="rounded-2xl p-4 border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-amber-100 dark:bg-amber-900/50 shrink-0">
                                    <i class="fa-duotone fa-triangle-exclamation size-5 text-amber-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-amber-700 dark:text-amber-300">{{ __('Low Stock') }}</p>
                                    <p class="text-sm text-amber-600 dark:text-amber-400">{{ $stats['inventory']['low_stock_count'] }} {{ __('items need restocking') }}</p>
                                </div>
                                @can('inventory.view')
                                    <a href="{{ route('inventory.stock') }}" wire:navigate class="flex items-center justify-center size-8 rounded-lg bg-amber-100 dark:bg-amber-900/50 hover:bg-amber-200 dark:hover:bg-amber-800/50 transition-colors shrink-0">
                                        <i class="fa-duotone fa-arrow-right size-4 text-amber-600 dark:text-amber-400"></i>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    {{-- Pending Purchase Requests --}}
                    @if ($stats['procurement']['pending_purchase_requests_count'] > 0)
                        <div class="rounded-2xl p-4 border border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-900/20">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-100 dark:bg-blue-900/50 shrink-0">
                                    <i class="fa-duotone fa-clipboard-list size-5 text-blue-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-blue-700 dark:text-blue-300">{{ __('Pending Requests') }}</p>
                                    <p class="text-sm text-blue-600 dark:text-blue-400">{{ $stats['procurement']['pending_purchase_requests_count'] }} {{ __('awaiting review') }}</p>
                                </div>
                                @can('procurement.view')
                                    <a href="{{ route('procurement.requests.index') }}" wire:navigate class="flex items-center justify-center size-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 hover:bg-blue-200 dark:hover:bg-blue-800/50 transition-colors shrink-0">
                                        <i class="fa-duotone fa-arrow-right size-4 text-blue-600 dark:text-blue-400"></i>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endif
                </div>
                @endif

                {{-- Quick Actions --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">{{ __('Quick Actions') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @can('orders.create')
                            <a href="{{ route('orders.create') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-lime-400 hover:bg-lime-50/50 dark:hover:bg-lime-900/10 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl transition-colors" style="background: rgba(163, 230, 53, 0.15);">
                                    <i class="fa-duotone fa-plus size-5" style="color: #65A30D;"></i>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('New Order') }}</span>
                            </a>
                        @endcan

                        @can('orders.view')
                            <a href="{{ route('orders.board') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                                    <i class="fa-duotone fa-table-columns size-5 text-blue-500"></i>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Order Board') }}</span>
                            </a>
                        @endcan

                        @can('inventory.view')
                            <a href="{{ route('inventory.stock') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-emerald-400 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                                    <i class="fa-duotone fa-boxes-stacked size-5 text-emerald-500"></i>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Inventory') }}</span>
                            </a>
                        @endcan

                        @can('reports.view')
                            <a href="{{ route('reports.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-violet-400 hover:bg-violet-50/50 dark:hover:bg-violet-900/10 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-violet-50 dark:bg-violet-900/30">
                                    <i class="fa-duotone fa-chart-column size-5 text-violet-500"></i>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Reports') }}</span>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Right: Todo + Payments Cards --}}
            <div class="lg:col-span-1 min-w-0">
                <div class="space-y-6">
                    <livewire:dashboard.todo-card />
                    <livewire:dashboard.payment-method-distribution-card />

                    {{-- Top Customers — header outside card --}}
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Top Customers') }}</h2>
                        @can('users.view')
                            <a href="{{ route('customers.index') }}" wire:navigate class="flex items-center justify-center size-8 rounded-full bg-lime-100 dark:bg-lime-900/30 text-lime-600 dark:text-lime-400 hover:bg-lime-200 dark:hover:bg-lime-800/40 transition-colors">
                                <i class="fa-duotone fa-plus size-3.5"></i>
                            </a>
                        @endcan
                    </div>

                    @php
                        $customerColors = ['violet', 'amber', 'emerald', 'blue', 'rose'];
                        $customerIcons = ['fa-crown', 'fa-medal', 'fa-award', 'fa-star', 'fa-gem'];
                    @endphp

                    @forelse ($stats['customers']['top_by_orders'] as $customer)
                        @php
                            $color = $customerColors[$loop->index % count($customerColors)];
                            $icon = $customerIcons[$loop->index % count($customerIcons)];
                        @endphp
                        <a
                            @can('users.view') href="{{ route('customers.show', $customer) }}" wire:navigate @endcan
                            class="flex items-center gap-3 rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 group hover:border-lime-300 dark:hover:border-lime-700/50 transition-colors"
                        >
                            <div class="flex items-center justify-center size-11 rounded-xl bg-{{ $color }}-100 dark:bg-{{ $color }}-900/30 shrink-0">
                                <i class="fa-duotone {{ $icon }} size-5 text-{{ $color }}-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white group-hover:text-lime-700 dark:group-hover:text-lime-400 transition-colors">{{ $customer->name }}</p>
                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $customer->phone ?: __('No phone saved') }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium shrink-0
                                {{ $loop->first
                                    ? 'border-lime-200 dark:border-lime-800/50 bg-lime-50 dark:bg-lime-900/20 text-lime-700 dark:text-lime-400'
                                    : 'border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/70 text-zinc-600 dark:text-zinc-400' }}">
                                {{ number_format($customer->orders_count) }} {{ __('orders') }}
                            </span>
                        </a>
                    @empty
                        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                            <div class="py-10 text-center">
                                <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-users size-7 text-zinc-400 dark:text-zinc-500"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No customers yet') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $stats['needs_branch_selection'] ? __('Select a branch to view customer rankings.') : __('No customer orders yet.') }}
                                </p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    @else
        {{-- Welcome View for users without dashboard.view permission --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Dashboard') }}</h1>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ now()->format('l, M j, Y') }}</p>
            </div>
        </div>

        {{-- Welcome Card --}}
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="p-8 sm:p-10" style="background: linear-gradient(135deg, rgba(163, 230, 53, 0.08) 0%, rgba(132, 204, 22, 0.04) 100%);">
                <div class="flex flex-col items-center text-center max-w-lg mx-auto">
                    <div class="flex items-center justify-center size-16 rounded-2xl mb-5" style="background: rgba(163, 230, 53, 0.2);">
                        <i class="fa-duotone fa-face-smile size-8" style="color: #65A30D;"></i>
                    </div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">
                        {{ __('Welcome back, :name!', ['name' => $user->name]) }}
                    </h2>
                    <p class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Use the quick links below to navigate to the areas you have access to.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Quick Links Grid --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">{{ __('Quick Links') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @can('orders.create')
                            <a href="{{ route('orders.create') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-lime-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl transition-colors" style="background: rgba(163, 230, 53, 0.15);">
                                    <i class="fa-duotone fa-plus size-5" style="color: #65A30D;"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('New Order') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Create a new customer order') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('orders.view')
                            <a href="{{ route('orders.board') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-blue-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                                    <i class="fa-duotone fa-table-columns size-5 text-blue-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Order Board') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('View and manage order progress') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('orders.view')
                            <a href="{{ route('orders.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-indigo-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30">
                                    <i class="fa-duotone fa-clipboard-list size-5 text-indigo-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('All Orders') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Browse and search all orders') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('inventory.view')
                            <a href="{{ route('inventory.stock') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-emerald-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                                    <i class="fa-duotone fa-boxes-stacked size-5 text-emerald-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Inventory') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Check stock levels and items') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('messages.use')
                            <a href="{{ route('messages.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-pink-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-pink-50 dark:bg-pink-900/30">
                                    <i class="fa-duotone fa-comments size-5 text-pink-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Messages') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Chat with your team') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('todos.use')
                            <a href="{{ route('tasks.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-amber-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-amber-50 dark:bg-amber-900/30">
                                    <i class="fa-duotone fa-circle-check size-5 text-amber-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('My Tasks') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('View and manage your tasks') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('expenses.view')
                            <a href="{{ route('expenses.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-red-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-red-50 dark:bg-red-900/30">
                                    <i class="fa-duotone fa-receipt size-5 text-red-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Expenses') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Track and manage expenses') }}</p>
                                </div>
                            </a>
                        @endcan

                        @can('reports.view')
                            <a href="{{ route('reports.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-violet-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-violet-50 dark:bg-violet-900/30">
                                    <i class="fa-duotone fa-chart-column size-5 text-violet-500"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Reports') }}</span>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('View business reports') }}</p>
                                </div>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Right: Todo Card --}}
            <div class="lg:col-span-1">
                <livewire:dashboard.todo-card />
            </div>
        </div>
    @endif
</div>
