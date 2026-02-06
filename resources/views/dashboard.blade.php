<x-layouts::app :title="__('Dashboard')">
    <flux:main class="space-y-6 p-4 lg:p-6">
        {{-- Welcome Header --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
                    </h1>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __("Here's what's happening with your tailoring business today.") }}
                    </p>
                </div>
                <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ now()->format('l, F j, Y') }}
                </div>
            </div>
        </div>

        {{-- Quick Stats Grid --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Stat Card: Orders --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-indigo-600 dark:text-indigo-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">
                        Coming Soon
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Orders') }}</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">—</p>
                </div>
            </div>

            {{-- Stat Card: Pending --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-amber-600 dark:text-amber-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">
                        Coming Soon
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending Orders') }}</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">—</p>
                </div>
            </div>

            {{-- Stat Card: Completed --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-emerald-600 dark:text-emerald-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">
                        Coming Soon
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">—</p>
                </div>
            </div>

            {{-- Stat Card: Revenue --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-purple-600 dark:text-purple-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">
                        Coming Soon
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Revenue (This Month)') }}</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">—</p>
                </div>
            </div>
        </div>

        {{-- Main Content Area --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Recent Activity / Orders --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Recent Orders') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Latest customer orders at a glance') }}</p>
                    </div>
                    <div class="flex min-h-64 items-center justify-center p-6">
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="mx-auto h-16 w-16 text-zinc-300 dark:text-zinc-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <h3 class="mt-4 text-sm font-medium text-zinc-900 dark:text-white">{{ __('No orders yet') }}</h3>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Orders module coming in Phase 2') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Actions / Info Panel --}}
            <div class="space-y-6">
                {{-- User Info Card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Your Account') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-sm font-semibold text-white">
                                {{ auth()->user()->initials() }}
                            </div>
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-700/50">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-indigo-600 dark:text-indigo-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span class="text-zinc-600 dark:text-zinc-300">
                                {{ __('Role:') }}
                                <span class="font-medium capitalize">{{ auth()->user()->roles->first()?->name ?? 'N/A' }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- System Status --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('System Status') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Phase') }}</span>
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400">
                                Phase 1 - Foundation
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Laravel') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ app()->version() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('PHP') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ PHP_VERSION }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Timezone') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ config('app.timezone') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </flux:main>
</x-layouts::app>
