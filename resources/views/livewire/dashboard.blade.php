<div class="space-y-6">
    @if ($canViewDashboard)
        {{-- Branch Selection Banner (for global admins without branch context) --}}
        @if ($stats['needs_branch_selection'])
            <div class="rounded-2xl p-4 border" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%); border-color: rgba(251, 191, 36, 0.3);">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(251, 191, 36, 0.2);">
                        <svg class="size-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-amber-700 dark:text-amber-300">{{ __('No branch selected') }}</p>
                        <p class="text-sm text-amber-600/80 dark:text-amber-400/80">{{ __('Select a branch from the sidebar to view dashboard statistics.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Header Section --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Dashboard') }}</h1>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __("Here's what's happening with your business today.") }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($currentBranch)
                    <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-medium" style="background: rgba(163, 230, 53, 0.15); color: #65A30D;">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                        </svg>
                        {{ $currentBranch->name }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ now()->format('M j, Y') }}
                </span>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: KPI Stats --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Primary Stats Grid --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- New Orders --}}
                    <div class="group rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 transition-all hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-600">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                                <svg class="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                            <span class="text-xs font-medium text-blue-500 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-lg">{{ __('New') }}</span>
                        </div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['orders']['new_orders_count']) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('New Orders') }}</p>
                    </div>

                    {{-- In Progress --}}
                    <div class="group rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 transition-all hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-600">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30">
                                <svg class="size-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <span class="text-xs font-medium text-amber-500 bg-amber-50 dark:bg-amber-900/30 px-2 py-1 rounded-lg">{{ __('Active') }}</span>
                        </div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['orders']['in_progress_orders_count']) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('In Progress') }}</p>
                    </div>

                    {{-- Completed --}}
                    <div class="group rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 transition-all hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-600">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center size-11 rounded-xl" style="background: rgba(163, 230, 53, 0.15);">
                                <svg class="size-5" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <span class="text-xs font-medium px-2 py-1 rounded-lg" style="background: rgba(163, 230, 53, 0.15); color: #65A30D;">{{ __('Done') }}</span>
                        </div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['orders']['completed_orders_count']) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Completed') }}</p>
                    </div>

                    {{-- Today's Payments --}}
                    <div class="group rounded-2xl p-5 shadow-sm border transition-all hover:shadow-md" style="background: linear-gradient(135deg, rgba(163, 230, 53, 0.1) 0%, rgba(132, 204, 22, 0.15) 100%); border-color: rgba(163, 230, 53, 0.3);">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center size-11 rounded-xl" style="background: rgba(163, 230, 53, 0.25);">
                                <svg class="size-5" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['sales']['payments_today_sum'], 0) }}</p>
                        <p class="text-sm mt-1" style="color: #65A30D;">{{ __("Today's Payments") }} <span class="text-zinc-400 text-xs">TZS</span></p>
                    </div>
                </div>

                {{-- Secondary Stats Row --}}
                <div class="grid gap-4 sm:grid-cols-3">
                    {{-- Monthly Revenue --}}
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                                <svg class="size-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('This Month') }}</p>
                                <p class="text-xl font-bold text-zinc-900 dark:text-white truncate">{{ number_format($stats['sales']['payments_month_sum'], 0) }} <span class="text-xs font-normal text-zinc-400">TZS</span></p>
                            </div>
                        </div>
                    </div>

                    {{-- Expenses --}}
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-red-50 dark:bg-red-900/30">
                                <svg class="size-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Expenses') }}</p>
                                <p class="text-xl font-bold text-zinc-900 dark:text-white truncate">{{ number_format($stats['expenses']['expenses_month_sum'], 0) }} <span class="text-xs font-normal text-zinc-400">TZS</span></p>
                            </div>
                        </div>
                    </div>

                    {{-- Capital --}}
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-violet-50 dark:bg-violet-900/30">
                                <svg class="size-6 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Capital') }}</p>
                                <p class="text-xl font-bold text-zinc-900 dark:text-white truncate">{{ number_format($stats['capital']['total_remaining_capital_sum'], 0) }} <span class="text-xs font-normal text-zinc-400">TZS</span></p>
                                <p class="text-xs text-zinc-400">{{ $stats['capital']['open_allocations_count'] }} {{ __('allocations') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Alerts Row --}}
                @if ($stats['inventory']['low_stock_count'] > 0 || $stats['procurement']['pending_purchase_requests_count'] > 0)
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Low Stock Alert --}}
                    @if ($stats['inventory']['low_stock_count'] > 0)
                        <div class="rounded-2xl p-4 border" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.08) 0%, rgba(245, 158, 11, 0.08) 100%); border-color: rgba(251, 191, 36, 0.25);">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(251, 191, 36, 0.2);">
                                    <svg class="size-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-amber-700 dark:text-amber-300">{{ __('Low Stock') }}</p>
                                    <p class="text-sm text-amber-600/80 dark:text-amber-400/80">{{ $stats['inventory']['low_stock_count'] }} {{ __('items need restocking') }}</p>
                                </div>
                                @can('inventory.view')
                                    <a href="{{ route('inventory.stock') }}" wire:navigate class="flex items-center justify-center size-8 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 transition-colors">
                                        <svg class="size-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    {{-- Pending Purchase Requests --}}
                    @if ($stats['procurement']['pending_purchase_requests_count'] > 0)
                        <div class="rounded-2xl p-4 border" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(37, 99, 235, 0.08) 100%); border-color: rgba(59, 130, 246, 0.25);">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(59, 130, 246, 0.2);">
                                    <svg class="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-blue-700 dark:text-blue-300">{{ __('Pending Requests') }}</p>
                                    <p class="text-sm text-blue-600/80 dark:text-blue-400/80">{{ $stats['procurement']['pending_purchase_requests_count'] }} {{ __('awaiting review') }}</p>
                                </div>
                                @can('procurement.view')
                                    <a href="{{ route('procurement.requests.index') }}" wire:navigate class="flex items-center justify-center size-8 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 transition-colors">
                                        <svg class="size-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
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
                            <a href="{{ route('orders.create') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-lime-400 hover:shadow-md" style="hover:background: rgba(163, 230, 53, 0.05);">
                                <div class="flex items-center justify-center size-10 rounded-xl transition-colors" style="background: rgba(163, 230, 53, 0.15);">
                                    <svg class="size-5" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('New Order') }}</span>
                            </a>
                        @endcan

                        @can('orders.view')
                            <a href="{{ route('orders.board') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-blue-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                                    <svg class="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                    </svg>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Order Board') }}</span>
                            </a>
                        @endcan

                        @can('inventory.view')
                            <a href="{{ route('inventory.stock') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-emerald-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                                    <svg class="size-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Inventory') }}</span>
                            </a>
                        @endcan

                        @can('reports.view')
                            <a href="{{ route('reports.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all hover:border-violet-400 hover:shadow-md">
                                <div class="flex items-center justify-center size-10 rounded-xl bg-violet-50 dark:bg-violet-900/30">
                                    <svg class="size-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                </div>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white">{{ __('Reports') }}</span>
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
                        <svg class="size-8" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
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
                                    <svg class="size-5" style="color: #65A30D;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
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
                                    <svg class="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                    </svg>
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
                                    <svg class="size-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
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
                                    <svg class="size-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
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
                                    <svg class="size-5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
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
                                    <svg class="size-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
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
                                    <svg class="size-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>
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
                                    <svg class="size-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
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
