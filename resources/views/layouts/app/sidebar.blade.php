<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="app-layout min-h-screen"
        x-data="{ desktopSidebarCollapsed: false }"
        x-init="desktopSidebarCollapsed = JSON.parse(window.localStorage.getItem('desktopSidebarCollapsed') ?? 'false'); $watch('desktopSidebarCollapsed', value => window.localStorage.setItem('desktopSidebarCollapsed', JSON.stringify(value)))"
    >
        @php($businessName = \App\Models\BusinessSetting::query()->value('business_name') ?: 'Tailex')

        <style>
            .sidebar-nav-groups .nav-group + .nav-group::before {
                content: '';
                display: block;
                height: 1px;
                margin: 0 0 1.25rem;
                background: linear-gradient(90deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.12) 18%, rgba(255, 255, 255, 0.12) 82%, rgba(255, 255, 255, 0.02) 100%);
            }

            @media (min-width: 1024px) {
                .desktop-sidebar {
                    transition: width 300ms ease;
                }

                .desktop-sidebar .sidebar-brand-name {
                    max-width: 12rem;
                    overflow: hidden;
                    transition: opacity 200ms ease, max-width 240ms ease, margin 240ms ease;
                }

                .desktop-sidebar .desktop-branch-switcher,
                .desktop-sidebar .nav-group > h3 {
                    max-height: 5rem;
                    overflow: hidden;
                    transition: opacity 200ms ease, max-height 240ms ease, margin 240ms ease;
                }

                .desktop-sidebar .sidebar-toggle-icon {
                    transition: opacity 200ms ease, max-height 240ms ease, margin 240ms ease;
                }

                .desktop-sidebar .desktop-sidebar-nav a {
                    overflow: hidden;
                    white-space: nowrap;
                    transition: padding 220ms ease, gap 220ms ease, font-size 220ms ease;
                }

                .desktop-sidebar .desktop-sidebar-nav a i {
                    width: 1.25rem;
                    flex-shrink: 0;
                    text-align: center;
                    font-size: 1rem;
                }

                .desktop-sidebar.is-collapsed .sidebar-header-link {
                    justify-content: center;
                    padding-right: 0;
                }

                .desktop-sidebar.is-collapsed .sidebar-brand-name,
                .desktop-sidebar.is-collapsed .desktop-branch-switcher,
                .desktop-sidebar.is-collapsed .nav-group > h3 {
                    opacity: 0;
                    max-width: 0;
                    max-height: 0;
                    margin: 0;
                    overflow: hidden;
                    pointer-events: none;
                }

                .desktop-sidebar.is-collapsed .desktop-sidebar-nav a {
                    justify-content: center;
                    gap: 0;
                    padding-left: 0;
                    padding-right: 0;
                    font-size: 0;
                }
            }
        </style>

        {{-- Floating Sidebar --}}
        <aside 
            class="desktop-sidebar fixed left-4 top-4 bottom-4 z-50 hidden lg:flex flex-col overflow-hidden transition-[width] duration-300 ease-out"
            :class="desktopSidebarCollapsed ? 'is-collapsed w-20' : 'w-64'"
            style="background: linear-gradient(180deg, #1E1F2E 0%, #252637 100%); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);"
        >
            {{-- Sidebar Header with Logo --}}
            <div class="relative p-5 border-b border-white/5">
                <button
                    type="button"
                    @click="desktopSidebarCollapsed = ! desktopSidebarCollapsed"
                    :title='desktopSidebarCollapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))'
                    class="sidebar-toggle-icon absolute right-4 top-4 flex size-9 items-center justify-center rounded-xl text-white/60 transition-all hover:bg-white/10 hover:text-white"
                >
                    <i class="fa-duotone text-sm" :class="desktopSidebarCollapsed ? 'fa-angles-right' : 'fa-angles-left'"></i>
                </button>

                <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-header-link flex items-center gap-3 pr-10 transition-all duration-300">
                    <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                        <x-app-logo-icon class="size-5 text-navy-900" />
                    </div>
                    <span class="sidebar-brand-name text-lg font-semibold text-white whitespace-nowrap">{{ $businessName }}</span>
                </a>
            </div>

            {{-- Branch Switcher for Global Admins --}}
            @auth
                @if (auth()->user()->isGlobalAdmin())
                    <div class="desktop-branch-switcher overflow-hidden">
                        <livewire:admin.branch-switcher :key="'desktop-branch-switcher'" />
                    </div>
                @endif
            @endauth

            {{-- Navigation with Modern Scrollbar --}}
            <nav class="sidebar-nav-groups desktop-sidebar-nav flex-1 overflow-y-auto px-3 py-4 custom-scrollbar transition-all duration-300" :class="desktopSidebarCollapsed ? 'space-y-4' : 'space-y-6'">
                {{-- Main Navigation Group --}}
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Main') }}</h3>
                    <a 
                        href="{{ route('dashboard') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-house size-5"></i>
                        {{ __('Dashboard') }}
                    </a>
                    <a
                        href="{{ route('tasks.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-list-check size-5"></i>
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
                        <i class="fa-duotone fa-chart-kanban size-5"></i>
                        {{ __('Order Board') }}
                    </a>

                    @canany(['orders.create', 'orders.update'])
                    <a 
                        href="{{ route('orders.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ (request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.create') || request()->routeIs('orders.edit')) ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-box-dollar size-5"></i>
                        {{ __('Orders Management') }}
                    </a>
                    @endcanany

                    <a
                        href="{{ route('invoices.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('invoices.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-file-invoice size-5"></i>
                        {{ __('Invoices') }}
                    </a>

                    @can('payments.view')
                    <a
                        href="{{ route('payments.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('payments.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-credit-card size-5"></i>
                        {{ __('Payments') }}
                    </a>
                    @endcan
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
                        <i class="fa-duotone fa-shelves size-5"></i>
                        {{ __('Stock Overview') }}
                    </a>

                    <a 
                        href="{{ route('inventory.items.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.items.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-boxes-stacked size-5"></i>
                        {{ __('Items') }}
                    </a>

                    <a 
                        href="{{ route('inventory.categories.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.categories.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-layer-group size-5"></i>
                        {{ __('Categories') }}
                    </a>

                    <a
                        href="{{ route('inventory.units.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.units.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-ruler size-5"></i>
                        {{ __('Units') }}
                    </a>

                    <a 
                        href="{{ route('inventory.transactions.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.transactions.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-cart-flatbed-boxes size-5"></i>
                        {{ __('Transactions') }}
                    </a>
                </div>
                @endcan

                {{-- Installments Group --}}
                @can('installments.view')
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Installments') }}</h3>

                    <a
                        href="{{ route('installments.dashboard') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-money-check-dollar-pen size-5"></i>
                        {{ __('Dashboard') }}
                    </a>

                    <a
                        href="{{ route('installments.plans.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.plans.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-file-invoice-dollar size-5"></i>
                        {{ __('Plans') }}
                    </a>

                    @can('installments.packages.manage')
                    <a
                        href="{{ route('installments.packages.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.packages.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-box-open-full size-5"></i>
                        {{ __('Packages') }}
                    </a>
                    @endcan

                    @can('installments.analytics.view')
                    <a
                        href="{{ route('installments.analytics') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.analytics') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-chart-line-up size-5"></i>
                        {{ __('Analytics') }}
                    </a>
                    @endcan
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
                        <i class="fa-duotone fa-warehouse size-5"></i>
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
                        <i class="fa-duotone fa-money-bills size-5"></i>
                        {{ __('Capital') }}
                    </a>
                    @endcan

                    <a 
                        href="{{ route('procurement.requests.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.requests.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-clipboard-check size-5"></i>
                        {{ __('Purchase Requests') }}
                    </a>

                    @can('procurement.po.manage')
                    <a 
                        href="{{ route('procurement.pos.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.pos.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-cart-shopping size-5"></i>
                        {{ __('Purchase Orders') }}
                    </a>
                    @endcan

                    @can('procurement.receive')
                    <a 
                        href="{{ route('procurement.receiving.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('procurement.receiving.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-truck size-5"></i>
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
                        <i class="fa-duotone fa-receipt size-5"></i>
                        {{ __('Expenses') }}
                    </a>

                    @can('expenses.categories.manage')
                    <a 
                        href="{{ route('expenses.categories.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('expenses.categories.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-layer-group size-5"></i>
                        {{ __('Expense Categories') }}
                    </a>
                    @endcan
                </div>
                @endcan

                {{-- Administration Group --}}
                @canany(['branches.view', 'users.view', 'roles.manage', 'sms.logs.view', 'sms.templates.manage'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Administration') }}</h3>

                    @can('branches.view')
                    <a
                        href="{{ route('branches.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('branches.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-code-branch size-5"></i>
                        {{ __('Branches') }}
                    </a>
                    @endcan
                    
                    @can('users.view')
                    <a 
                        href="{{ route('users.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('users.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-users size-5"></i>
                        {{ __('Users') }}
                    </a>

                    <a
                        href="{{ route('customers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('customers.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-user size-5"></i>
                        {{ __('Customers') }}
                    </a>
                    @endcan

                    @can('roles.manage')
                    <a 
                        href="{{ route('access-control.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('access-control.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-shield-halved size-5"></i>
                        {{ __('Roles & Permissions') }}
                    </a>

                    <a
                        href="{{ route('administration.settings') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.settings') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-gear size-5"></i>
                        {{ __('Settings') }}
                    </a>
                    @endcan

                    @can('sms.logs.view')
                    <a 
                        href="{{ route('sms.logs.index') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('sms.logs.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-comment-sms size-5"></i>
                        {{ __('SMS Logs') }}
                    </a>
                    @endcan

                    @can('sms.templates.manage')
                    <a
                        href="{{ route('beem-configurations.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('beem-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-sliders size-5"></i>
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
                        <i class="fa-duotone fa-chart-column size-5"></i>
                        {{ __('Dashboard') }}
                    </a>

                    <a 
                        href="{{ route('reports.sales') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.sales') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-money-bills size-5"></i>
                        {{ __('Sales') }}
                    </a>

                    <a 
                        href="{{ route('reports.orders') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.orders') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-file-lines size-5"></i>
                        {{ __('Orders') }}
                    </a>

                    <a 
                        href="{{ route('reports.expenses') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.expenses') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-receipt size-5"></i>
                        {{ __('Expenses') }}
                    </a>

                    <a 
                        href="{{ route('reports.inventory') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.inventory') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-boxes-stacked size-5"></i>
                        {{ __('Inventory') }}
                    </a>

                    <a 
                        href="{{ route('reports.capital') }}" 
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('reports.capital') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-building-columns size-5"></i>
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
                    <i class="fa-duotone fa-xmark size-5"></i>
                </button>

                {{-- Mobile Sidebar Content --}}
                <div class="p-5 border-b border-white/5">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                            <x-app-logo-icon class="size-5 text-navy-900" />
                        </div>
                        <span class="text-lg font-semibold text-white">{{ $businessName }}</span>
                    </a>
                </div>

                {{-- Branch Switcher for Mobile --}}
                @auth
                    @if (auth()->user()->isGlobalAdmin())
                        <livewire:admin.branch-switcher :key="'mobile-branch-switcher'" />
                    @endif
                @endauth

                {{-- Mobile Navigation --}}
                <nav class="sidebar-nav-groups flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Main') }}</h3>
                        <a href="{{ route('dashboard') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-house size-5"></i>
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('tasks.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-list-check size-5"></i>
                            {{ __('My Tasks') }}
                        </a>
                    </div>

                    @can('installments.view')
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Installments') }}</h3>
                        <a href="{{ route('installments.dashboard') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.dashboard') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-money-check-dollar-pen size-5"></i>
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('installments.plans.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.plans.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-file-invoice-dollar size-5"></i>
                            {{ __('Plans') }}
                        </a>
                        @can('installments.packages.manage')
                        <a href="{{ route('installments.packages.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.packages.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-box-open-full size-5"></i>
                            {{ __('Packages') }}
                        </a>
                        @endcan
                        @can('installments.analytics.view')
                        <a href="{{ route('installments.analytics') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('installments.analytics') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-chart-line-up size-5"></i>
                            {{ __('Analytics') }}
                        </a>
                        @endcan
                    </div>
                    @endcan
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
                    <i class="fa-duotone fa-bars size-5 text-navy-900 dark:text-white"></i>
                </button>
                <span class="text-lg font-semibold text-navy-900 dark:text-white">{{ $businessName }}</span>
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
                                <i class="fa-duotone fa-user text-sm text-zinc-500"></i>
                                {{ __('Profile') }}
                            </flux:menu.item>

                            <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3">
                                <i class="fa-duotone fa-gear text-sm text-zinc-500"></i>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </div>

                        <flux:menu.separator />

                        {{-- Logout --}}
                        <div class="py-1">
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" class="w-full gap-3 text-red-600 dark:text-red-400">
                                    <i class="fa-duotone fa-right-from-bracket text-sm"></i>
                                    {{ __('Log Out') }}
                                </flux:menu.item>
                            </form>
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        {{-- Main Content Area --}}
        <main class="pt-16 lg:pt-0 min-h-screen transition-[margin] duration-300 ease-out" :class="desktopSidebarCollapsed ? 'lg:ml-28' : 'lg:ml-72'">
            {{-- Desktop Header --}}
            <header class="hidden lg:flex sticky top-0 z-40 h-16 items-center justify-end gap-x-4 px-6 mx-4 mt-4 rounded-2xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl shadow-sm border border-black/5 dark:border-white/5">
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
                                    <i class="fa-duotone fa-user text-lg text-zinc-400"></i>
                                    {{ __('Profile') }}
                                </flux:menu.item>

                                <flux:menu.item :href="route('profile.edit')" wire:navigate class="gap-3 px-4 py-2.5">
                                    <i class="fa-duotone fa-gear text-lg text-zinc-400"></i>
                                    {{ __('Settings') }}
                                </flux:menu.item>
                            </div>

                            <flux:menu.separator />

                            {{-- Logout --}}
                            <div class="py-2">
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" class="w-full gap-3 px-4 py-2.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <i class="fa-duotone fa-right-from-bracket text-lg"></i>
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
