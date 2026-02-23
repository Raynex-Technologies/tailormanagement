<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-layout min-h-screen">
        {{-- Floating Sidebar --}}
        <aside 
            class="fixed left-4 top-4 bottom-4 w-64 z-50 hidden lg:flex flex-col overflow-hidden"
            style="background: linear-gradient(180deg, #1E1F2E 0%, #252637 100%); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);"
        >
            {{-- Sidebar Header with Logo --}}
            <div class="p-5 border-b border-white/5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                        <x-app-logo-icon class="size-5 text-navy-900" />
                    </div>
                    <span class="text-lg font-semibold text-white">{{ config('app.name', 'Tailor Pro') }}</span>
                </a>
            </div>

            {{-- Branch Switcher for Global Admins --}}
            @auth
                @if (auth()->user()->isGlobalAdmin())
                    <livewire:admin.branch-switcher :key="'desktop-branch-switcher'" />
                @endif
            @endauth

            {{-- Navigation with Modern Scrollbar --}}
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">
                {{-- Main Navigation Group --}}
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Main') }}</h3>
                    <a 
                        href="{{ route('dashboard') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        {{ __('Dashboard') }}
                    </a>
                    <a 
                        href="{{ route('tasks.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        {{ __('My Tasks') }}
                    </a>
                </div>

                {{-- Orders Group (Permission-based) --}}
                @can('orders.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Orders') }}</h3>
                    
                    <a 
                        href="{{ route('orders.board') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('orders.board') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        {{ __('Order Board') }}
                    </a>

                    @canany(['orders.create', 'orders.update'])
                    <a 
                        href="{{ route('orders.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ (request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.create') || request()->routeIs('orders.edit')) ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        {{ __('Orders Management') }}
                    </a>
                    @endcanany

                    <a
                        href="{{ route('invoices.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('invoices.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v2.25A2.25 2.25 0 0 1 17.25 18.75H6.75A2.25 2.25 0 0 1 4.5 16.5V7.5A2.25 2.25 0 0 1 6.75 5.25h7.5L19.5 10.5v3.75Zm-10.5-3h6m-6 3h6m-6 3h4.5" />
                        </svg>
                        {{ __('Invoices') }}
                    </a>
                </div>
                @endcan

                {{-- Inventory Group (Permission-based) --}}
                @can('inventory.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Inventory') }}</h3>
                    
                    <a 
                        href="{{ route('inventory.stock') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.stock') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        {{ __('Stock Overview') }}
                    </a>

                    <a 
                        href="{{ route('inventory.items.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.items.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                        {{ __('Items') }}
                    </a>

                    <a 
                        href="{{ route('inventory.categories.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.categories.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        {{ __('Categories') }}
                    </a>

                    <a 
                        href="{{ route('inventory.transactions.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.transactions.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        {{ __('Transactions') }}
                    </a>
                </div>
                @endcan

                {{-- Store Group (for Storekeeper) --}}
                @canany(['stock_requests.review', 'stock_requests.fulfill'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Store') }}</h3>
                    
                    <a 
                        href="{{ route('store.stock-requests.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('store.stock-requests.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.875 14.25l1.214 1.942a2.25 2.25 0 0 0 1.908 1.058h2.006c.776 0 1.497-.4 1.908-1.058l1.214-1.942M2.41 9h4.636a2.25 2.25 0 0 1 1.872 1.002l.164.246a2.25 2.25 0 0 0 1.872 1.002h2.092a2.25 2.25 0 0 0 1.872-1.002l.164-.246A2.25 2.25 0 0 1 16.954 9h4.636M2.41 9a2.25 2.25 0 0 0-.16.832V12a2.25 2.25 0 0 0 2.25 2.25h15a2.25 2.25 0 0 0 2.25-2.25V9.832c0-.287-.055-.57-.16-.832M2.41 9a2.25 2.25 0 0 1 .382-.632l3.285-3.832a2.25 2.25 0 0 1 1.708-.786h8.43c.657 0 1.281.287 1.709.786l3.284 3.832c.163.19.291.404.382.632M4.5 20.25h15A2.25 2.25 0 0 0 21.75 18v-2.625c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125V18a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        {{ __('Stock Requests') }}
                    </a>
                </div>
                @endcanany

                {{-- Procurement Group --}}
                @can('procurement.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Procurement') }}</h3>
                    
                    @can('capital.view')
                    <a 
                        href="{{ route('capital.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('capital.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                        {{ __('Capital') }}
                    </a>
                    @endcan

                    <a 
                        href="{{ route('procurement.requests.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.requests.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                        </svg>
                        {{ __('Purchase Requests') }}
                    </a>

                    @can('procurement.po.manage')
                    <a 
                        href="{{ route('procurement.pos.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.pos.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        {{ __('Purchase Orders') }}
                    </a>
                    @endcan

                    @can('procurement.receive')
                    <a 
                        href="{{ route('procurement.receiving.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.receiving.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                        {{ __('Receiving') }}
                    </a>
                    @endcan
                </div>
                @endcan

                {{-- Finance Group --}}
                @can('expenses.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Finance') }}</h3>
                    
                    <a 
                        href="{{ route('expenses.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ (request()->routeIs('expenses.index') || request()->routeIs('expenses.show') || request()->routeIs('expenses.create') || request()->routeIs('expenses.edit')) ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        {{ __('Expenses') }}
                    </a>

                    @can('expenses.categories.manage')
                    <a 
                        href="{{ route('expenses.categories.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('expenses.categories.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        {{ __('Expense Categories') }}
                    </a>
                    @endcan
                </div>
                @endcan

                {{-- Administration Group --}}
                @canany(['users.view', 'roles.manage', 'sms.logs.view', 'sms.templates.manage'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Administration') }}</h3>
                    
                    @can('users.view')
                    <a 
                        href="{{ route('users.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('users.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        {{ __('Users') }}
                    </a>
                    @endcan

                    @can('roles.manage')
                    <a 
                        href="{{ route('access-control.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('access-control.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        {{ __('Roles & Permissions') }}
                    </a>

                    <a
                        href="{{ route('administration.settings') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.settings') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 0 0-3 0M3.75 6H7.5m6 6h6.75m-6.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 0 0-3 0H3.75m9.75 6h6.75m-6.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 0 0-3 0H3.75" />
                        </svg>
                        {{ __('Settings') }}
                    </a>
                    @endcan

                    @can('sms.logs.view')
                    <a 
                        href="{{ route('sms.logs.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('sms.logs.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                        {{ __('SMS Logs') }}
                    </a>
                    @endcan

                    @can('sms.templates.manage')
                    <a
                        href="{{ route('beem-configurations.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('beem-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 0 0-3 0M3.75 6H7.5m3-.75-3.75 3.75m0 0 3.75 3.75M7.5 6v12m0-9v9" />
                        </svg>
                        {{ __('Beem Configurations') }}
                    </a>
                    @endcan
                </div>
                @endcanany

                {{-- Reports Group --}}
                @can('reports.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Reports') }}</h3>
                    
                    <a 
                        href="{{ route('reports.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.index') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        {{ __('Dashboard') }}
                    </a>

                    <a 
                        href="{{ route('reports.sales') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.sales') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                        {{ __('Sales') }}
                    </a>

                    <a 
                        href="{{ route('reports.orders') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.orders') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        {{ __('Orders') }}
                    </a>

                    <a 
                        href="{{ route('reports.expenses') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.expenses') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        {{ __('Expenses') }}
                    </a>

                    <a 
                        href="{{ route('reports.inventory') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.inventory') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        {{ __('Inventory') }}
                    </a>

                    <a 
                        href="{{ route('reports.capital') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.capital') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                        </svg>
                        {{ __('Capital Audit') }}
                    </a>
                </div>
                @endcan
            </nav>
        </aside>

        {{-- Mobile Sidebar Overlay --}}
        <div 
            x-data="{ sidebarOpen: false }"
            @toggle-sidebar.window="sidebarOpen = !sidebarOpen"
            class="lg:hidden"
        >
            {{-- Backdrop --}}
            <div 
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40"
            ></div>

            {{-- Mobile Sidebar --}}
            <aside 
                x-show="sidebarOpen"
                x-transition:enter="transition ease-in-out duration-300 transform"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in-out duration-300 transform"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="fixed left-4 top-4 bottom-4 w-64 z-50 flex flex-col overflow-hidden"
                style="background: linear-gradient(180deg, #1E1F2E 0%, #252637 100%); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);"
            >
                {{-- Close button --}}
                <button 
                    @click="sidebarOpen = false"
                    class="absolute top-4 right-4 p-2 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                >
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                {{-- Mobile Sidebar Content --}}
                <div class="p-5 border-b border-white/5">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                            <x-app-logo-icon class="size-5 text-navy-900" />
                        </div>
                        <span class="text-lg font-semibold text-white">{{ config('app.name', 'Tailor Pro') }}</span>
                    </a>
                </div>

                {{-- Branch Switcher for Mobile --}}
                @auth
                    @if (auth()->user()->isGlobalAdmin())
                        <livewire:admin.branch-switcher :key="'mobile-branch-switcher'" />
                    @endif
                @endauth

                {{-- Mobile Navigation --}}
                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Main') }}</h3>
                        <a href="{{ route('dashboard') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('tasks.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            {{ __('My Tasks') }}
                        </a>
                    </div>
                </nav>
            </aside>
        </div>

        {{-- Mobile Header --}}
        <header class="lg:hidden fixed top-0 left-0 right-0 z-30 h-16 flex items-center justify-between px-4" style="background: linear-gradient(135deg, #F5F5F7 0%, #EBEBED 50%, #E8E8EC 100%);">
            <div class="dark:hidden absolute inset-0" style="background: linear-gradient(135deg, #F5F5F7 0%, #EBEBED 50%, #E8E8EC 100%);"></div>
            <div class="hidden dark:block absolute inset-0" style="background: linear-gradient(135deg, #111113 0%, #18181B 50%, #1C1C1F 100%);"></div>
            
            <div class="relative flex items-center gap-3">
                <button 
                    @click="$dispatch('toggle-sidebar')"
                    class="p-2 rounded-xl bg-white/80 dark:bg-white/10 shadow-sm"
                >
                    <svg class="size-5 text-navy-900 dark:text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <span class="text-lg font-semibold text-navy-900 dark:text-white">{{ config('app.name', 'Tailor Pro') }}</span>
            </div>

            <div class="relative flex items-center gap-2">
                <livewire:messages.unread-badge />
                <livewire:notifications.notification-bell />
                
                {{-- Mobile Profile Dropdown --}}
                <flux:dropdown position="bottom" align="end">
                    <button class="flex items-center justify-center size-9 rounded-xl font-semibold text-sm shadow-lg transition-transform hover:scale-105" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;">
                        {{ auth()->user()->initials() }}
                    </button>

                    <flux:menu class="w-56">
                        {{-- User Info Header --}}
                        <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-xl font-semibold text-sm" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;">
                                    {{ auth()->user()->initials() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()->email }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Menu Items --}}
                        <div class="py-1">
                            <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3">
                                <svg class="size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                {{ __('Profile') }}
                            </flux:menu.item>

                            <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3">
                                <svg class="size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </div>

                        <flux:menu.separator />

                        {{-- Logout --}}
                        <div class="py-1">
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" class="w-full gap-3 text-red-600 dark:text-red-400">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                    </svg>
                                    {{ __('Log Out') }}
                                </flux:menu.item>
                            </form>
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        {{-- Main Content Area --}}
        <main class="lg:ml-72 pt-16 lg:pt-0 min-h-screen">
            {{-- Desktop Header --}}
            <header class="hidden lg:flex sticky top-0 z-40 h-16 items-center justify-end gap-x-4 px-6 mx-4 mt-4 rounded-2xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl shadow-sm border border-black/5 dark:border-white/5">
                {{-- Search --}}
                <div class="relative w-full max-w-xs mr-auto">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input
                        type="search"
                        placeholder="{{ __('Search...') }}"
                        class="block w-full rounded-xl border-0 bg-zinc-100 dark:bg-white/5 py-2 pl-10 pr-3 text-sm text-zinc-900 dark:text-white ring-1 ring-inset ring-zinc-200 dark:ring-white/10 placeholder:text-zinc-400 dark:placeholder:text-white/40 focus:bg-white dark:focus:bg-white/10 focus:ring-2 focus:ring-lime-400"
                    />
                </div>

                {{-- Right side actions --}}
                <div class="flex items-center gap-x-3">
                    <livewire:messages.unread-badge />
                    <livewire:notifications.notification-bell />
                    
                    {{-- Desktop Profile Dropdown --}}
                    <flux:dropdown position="bottom" align="end">
                        <button class="flex items-center justify-center size-10 rounded-xl font-semibold text-sm shadow-lg transition-all hover:scale-105 hover:shadow-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;">
                            {{ auth()->user()->initials() }}
                        </button>

                        <flux:menu class="w-64">
                            {{-- User Info Header --}}
                            <div class="px-4 py-4 border-b border-zinc-200 dark:border-zinc-700">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-12 rounded-xl font-bold text-base" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%); color: #1E1F2E;">
                                        {{ auth()->user()->initials() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()->email }}</div>
                                        <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400">
                                            {{ auth()->user()->roles->first()?->name ?? 'User' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Menu Items --}}
                            <div class="py-2">
                                <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3 px-4 py-2.5">
                                    <svg class="size-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    {{ __('Profile') }}
                                </flux:menu.item>

                                <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3 px-4 py-2.5">
                                    <svg class="size-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    {{ __('Settings') }}
                                </flux:menu.item>
                            </div>

                            <flux:menu.separator />

                            {{-- Logout --}}
                            <div class="py-2">
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" class="w-full gap-3 px-4 py-2.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                        </svg>
                                        {{ __('Log Out') }}
                                    </flux:menu.item>
                                </form>
                            </div>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </header>

            {{-- Branch Required Banner for Global Admins --}}
            <x-branch-required-banner />

            {{-- Page Content --}}
            <div class="p-4 lg:p-6">
                {{ $slot }}
            </div>
        </main>

        @fluxScripts
    </body>
</html>
