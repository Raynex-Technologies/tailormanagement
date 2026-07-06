<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="app-layout min-h-screen overflow-x-hidden"
        x-data="{ desktopSidebarCollapsed: false }"
        x-init="desktopSidebarCollapsed = JSON.parse(window.localStorage.getItem('desktopSidebarCollapsed') ?? 'false'); $watch('desktopSidebarCollapsed', value => window.localStorage.setItem('desktopSidebarCollapsed', JSON.stringify(value)))"
    >
        @php
            $businessName = $businessName ?? 'Tailex';
            $businessLogoUrl = $businessLogoUrl ?? null;
            $urgentOpenOrdersCount = (int) ($urgentOpenOrdersCount ?? 0);
            $calendarNavUrl = \Illuminate\Support\Facades\Route::has('calendar.index')
                ? route('calendar.index')
                : url('/calendar');
            $systemUiCssVariables = $systemUiCssVariables ?? \App\Support\SystemUiSettings::cssVariables();
            $storefrontModuleEnabled = module_enabled('storefront');
            $bookingsModuleEnabled = module_enabled('bookings');
            $ordersModuleEnabled = module_enabled('orders');
            $inventoryModuleEnabled = module_enabled('inventory');
            $installmentsModuleEnabled = module_enabled('installments');
        @endphp

        <style>
            :root {
                {!! $systemUiCssVariables !!}
            }

            .app-layout a:not(.sidebar-header-link):not([class*="text-white"]):not([class*="text-zinc"]):hover {
                color: var(--tailorpro-secondary);
            }

            .desktop-sidebar,
            .app-mobile-sidebar {
                background: linear-gradient(180deg, var(--tailorpro-primary) 0%, color-mix(in srgb, var(--tailorpro-primary) 88%, #ffffff 12%) 100%) !important;
                color: var(--tailorpro-primary-foreground);
            }

            .desktop-sidebar-nav a.bg-lime-400,
            .app-mobile-sidebar a.bg-lime-400 {
                background: var(--tailorpro-secondary) !important;
                color: var(--tailorpro-secondary-foreground) !important;
                box-shadow: 0 4px 12px color-mix(in srgb, var(--tailorpro-secondary) 35%, transparent) !important;
            }

            .desktop-sidebar .sidebar-header-link > div,
            .app-mobile-sidebar a[href="{{ route('dashboard') }}"] > div,
            .app-ui-accent {
                background: var(--tailorpro-secondary-2) !important;
                color: var(--tailorpro-secondary-2-foreground) !important;
            }

            .app-layout button[data-flux-button][data-variant="primary"],
            .app-layout a[data-flux-button][data-variant="primary"] {
                background: var(--tailorpro-secondary) !important;
                color: var(--tailorpro-secondary-foreground) !important;
                border-color: var(--tailorpro-secondary) !important;
            }

            .app-layout button[data-flux-button][data-variant="primary"]:hover,
            .app-layout a[data-flux-button][data-variant="primary"]:hover {
                opacity: .9;
            }

            .app-layout .border-lime-500 {
                border-color: var(--tailorpro-secondary) !important;
            }

            .app-layout .bg-lime-50,
            .app-layout .bg-lime-100 {
                background-color: color-mix(in srgb, var(--tailorpro-secondary) 14%, #ffffff 86%) !important;
            }

            .app-layout .text-lime-600,
            .app-layout .text-lime-700,
            .app-layout .hover\:text-lime-600:hover {
                color: var(--tailorpro-secondary) !important;
            }

            .app-layout .focus\:ring-lime-500:focus,
            .app-layout .focus-visible\:ring-lime-400\/70:focus-visible {
                --tw-ring-color: var(--tailorpro-secondary) !important;
            }

            .sidebar-nav-groups .nav-group + .nav-group::before {
                content: '';
                display: block;
                height: 1px;
                margin: 0 0 1.25rem;
                background: linear-gradient(90deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.12) 18%, rgba(255, 255, 255, 0.12) 82%, rgba(255, 255, 255, 0.02) 100%);
            }

            .top-frosted-nav {
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, 0.58);
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.52) 0%, rgba(246, 247, 249, 0.4) 52%, rgba(230, 232, 237, 0.34) 100%);
                box-shadow: 0 12px 34px rgba(15, 23, 42, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.56);
                backdrop-filter: blur(24px) saturate(150%);
                -webkit-backdrop-filter: blur(24px) saturate(150%);
            }

            .top-frosted-nav::before {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                background:
                    radial-gradient(circle at 14% 18%, rgba(255, 255, 255, 0.44) 0%, rgba(255, 255, 255, 0) 38%),
                    radial-gradient(circle at 84% 28%, rgba(255, 255, 255, 0.34) 0%, rgba(255, 255, 255, 0) 32%);
            }

            .top-frosted-nav::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                opacity: 0.52;
                background:
                    repeating-linear-gradient(
                        120deg,
                        rgba(255, 255, 255, 0.11) 0,
                        rgba(255, 255, 255, 0.11) 1px,
                        rgba(255, 255, 255, 0) 1px,
                        rgba(255, 255, 255, 0) 7px
                    );
            }

            .top-frosted-nav > * {
                position: relative;
                z-index: 1;
            }

            .dark .top-frosted-nav {
                border-color: rgba(255, 255, 255, 0.14);
                background: linear-gradient(135deg, rgba(39, 39, 42, 0.62) 0%, rgba(24, 24, 27, 0.56) 55%, rgba(15, 23, 42, 0.44) 100%);
                box-shadow: 0 14px 34px rgba(0, 0, 0, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.14);
            }

            .dark .top-frosted-nav::before {
                background:
                    radial-gradient(circle at 14% 18%, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0) 38%),
                    radial-gradient(circle at 84% 28%, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 32%);
            }

            .dark .top-frosted-nav::after {
                opacity: 0.22;
                background:
                    repeating-linear-gradient(
                        120deg,
                        rgba(255, 255, 255, 0.14) 0,
                        rgba(255, 255, 255, 0.14) 1px,
                        rgba(255, 255, 255, 0) 1px,
                        rgba(255, 255, 255, 0) 8px
                    );
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

                .desktop-sidebar .orders-indicator-dot {
                    display: none;
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

                .desktop-sidebar.is-collapsed .orders-management-label,
                .desktop-sidebar.is-collapsed .orders-indicator-badge {
                    display: none;
                }

                .desktop-sidebar.is-collapsed .orders-indicator-dot {
                    display: flex;
                }

            .desktop-sidebar .nav-group.nav-group-active > h3 {
                    color: var(--tailorpro-secondary-2);
                    text-shadow: 0 0 12px color-mix(in srgb, var(--tailorpro-secondary-2) 35%, transparent);
                }
            }
        </style>

        {{-- Floating Sidebar --}}
        <aside 
            class="desktop-sidebar fixed left-4 top-4 bottom-4 z-50 hidden lg:flex flex-col overflow-hidden transition-[width] duration-300 ease-out"
            :class="desktopSidebarCollapsed ? 'is-collapsed w-20' : 'w-64'"
            style="border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);"
        >
            {{-- Sidebar Header with Logo --}}
            <div class="relative p-5 border-b border-white/5">
                <button
                    type="button"
                    @click="desktopSidebarCollapsed = ! desktopSidebarCollapsed"
                    :title="desktopSidebarCollapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))"
                    class="sidebar-toggle-icon absolute right-4 top-4 flex size-9 items-center justify-center rounded-xl text-white/60 transition-all hover:bg-white/10 hover:text-white"
                >
                    <i class="fa-duotone text-sm" :class="desktopSidebarCollapsed ? 'fa-angles-right' : 'fa-angles-left'"></i>
                </button>

                <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-header-link flex items-center gap-3 pr-10 transition-all duration-300">
                    @if ($businessLogoUrl)
                        <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}" class="size-10 rounded-xl bg-white object-contain p-1 shadow-sm">
                    @else
                        <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                            <x-app-logo-icon class="size-5 text-navy-900" />
                        </div>
                    @endif
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
                    @can('todos.use')
                        <a
                            href="{{ route('tasks.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                        >
                            <i class="fa-duotone fa-list-check size-5"></i>
                            {{ __('My Tasks') }}
                        </a>
                    @endcan
                </div>

                {{-- Orders Group (Permission-based) --}}
                @if ($ordersModuleEnabled)
                @canany(['orders.view', 'users.view'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Orders') }}</h3>

                    @can('orders.view')
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
                        class="orders-management-link relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ (request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.create') || request()->routeIs('orders.edit')) ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-box-dollar size-5"></i>
                        <span class="orders-management-label flex-1">{{ __('Orders Management') }}</span>
                        @if ($urgentOpenOrdersCount > 0)
                            <span class="orders-indicator-badge inline-flex min-w-6 items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                                {{ $urgentOpenOrdersCount }}
                            </span>
                            <span class="orders-indicator-dot pointer-events-none absolute right-3 top-2 size-2.5 items-center justify-center" aria-hidden="true">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-orange-400 opacity-90"></span>
                                <span class="relative inline-flex size-2.5 rounded-full bg-orange-500 ring-1 ring-white/90 dark:ring-zinc-900"></span>
                            </span>
                        @endif
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
                    @endcan

                    @can('users.view')
                    <a
                        href="{{ route('customers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('customers.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-user size-5"></i>
                        {{ __('Customers') }}
                    </a>
                    @endcan
                </div>
                @endcanany
                @endif

                @if ($bookingsModuleEnabled)
                @canany(['online-bookings.view', 'appointments.view', 'availability.view', 'garment-options.view'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Bookings') }}</h3>
                    @can('online-bookings.view')
                    <a href="{{ route('admin.online-bookings.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.online-bookings.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <i class="fa-duotone fa-calendar-plus size-5"></i>
                        {{ __('Online Bookings') }}
                    </a>
                    @endcan
                    @can('appointments.view')
                    <a href="{{ route('admin.appointments.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.appointments.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <i class="fa-duotone fa-calendar-check size-5"></i>
                        {{ __('Appointments') }}
                    </a>
                    @endcan
                    @can('availability.view')
                    <a href="{{ route('admin.availability.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.availability.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <i class="fa-duotone fa-clock size-5"></i>
                        {{ __('Availability') }}
                    </a>
                    @endcan
                    @can('garment-options.view')
                    <a href="{{ route('admin.garment-options.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.garment-options.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <i class="fa-duotone fa-shirt size-5"></i>
                        {{ __('Garment Options') }}
                    </a>
                    @endcan
                </div>
                @endcanany
                @endif

                {{-- Storefront Group --}}
                @if ($storefrontModuleEnabled)
                @canany(['storefront.settings.manage', 'storefront.catalog.manage', 'storefront.cms.manage', 'storefront.shipping.manage', 'storefront.orders.manage', 'storefront.payments.manage'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Storefront') }}</h3>

                    @can('storefront.settings.manage')
                    <a
                        href="{{ route('administration.storefront.settings') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.settings') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-store size-5"></i>
                        {{ __('Storefront Settings') }}
                    </a>
                    @endcan

                    @can('storefront.catalog.manage')
                    <a
                        href="{{ route('administration.storefront.products') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.products*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-bag-shopping size-5"></i>
                        {{ __('Products') }}
                    </a>

                    @if (Route::has('administration.storefront.categories'))
                    <a
                        href="{{ route('administration.storefront.categories') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.categories') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-layer-group size-5"></i>
                        {{ __('Product Categories') }}
                    </a>
                    @endif
                    @endcan

                    @can('storefront.cms.manage')
                    <a
                        href="{{ route('administration.storefront.cms') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.cms') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-file-lines size-5"></i>
                        {{ __('Storefront CMS') }}
                    </a>
                    @endcan

                    @can('storefront.shipping.manage')
                    <a
                        href="{{ route('administration.storefront.shipping') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.shipping') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-truck size-5"></i>
                        {{ __('Storefront Shipping') }}
                    </a>
                    @endcan
                </div>
                @endcanany
                @endif

                {{-- Inventory Group (Permission-based) --}}
                @if ($inventoryModuleEnabled)
                @canany(['inventory.view', 'pos.view'])
                <div class="nav-group">
                    <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Inventory') }}</h3>

                    @can('pos.view')
                    <a
                        href="{{ route('pos.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('pos.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-cash-register size-5"></i>
                        {{ __('POS') }}
                    </a>
                    @endcan

                    @can('inventory.view')
                    
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
                        href="{{ route('inventory.suppliers.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.suppliers.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-truck size-5"></i>
                        {{ __('Suppliers') }}
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
                    @endcan
                </div>
                @endcanany
                @endif

                {{-- Installments Group --}}
                @if ($installmentsModuleEnabled)
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
                @endif

                {{-- Store Group (for Storekeeper) --}}
                @if ($ordersModuleEnabled)
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
                @endif

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

                {{-- Administration Group --}}
                @canany(['branches.view', 'users.view', 'roles.manage', 'sms.logs.view', 'sms-settings.view'])
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

                    @can('sms-settings.view')
                    <a
                        href="{{ route('beem-configurations.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('beem-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-sliders size-5"></i>
                        {{ __('SMS Settings') }}
                    </a>
                    <a
                        href="{{ route('whatsapp-configurations.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('whatsapp-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-brands fa-whatsapp size-5"></i>
                        {{ __('WhatsApp Settings') }}
                    </a>
                    @can('roles.manage')
                    <a
                        href="{{ route('administration.email-setup') }}"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.email-setup') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <i class="fa-duotone fa-envelope-open-text size-5"></i>
                        {{ __('Email Setup') }}
                    </a>
                    @endcan
                    @endcan
                </div>
                @endcanany
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
                class="app-mobile-sidebar fixed inset-y-3 left-3 right-3 z-50 flex flex-col overflow-hidden sm:inset-y-4 sm:left-4 sm:right-auto sm:w-64"
                style="border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);"
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
                        @if ($businessLogoUrl)
                            <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}" class="size-10 rounded-xl bg-white object-contain p-1 shadow-sm">
                        @else
                            <div class="flex items-center justify-center size-10 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                                <x-app-logo-icon class="size-5 text-navy-900" />
                            </div>
                        @endif
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
                        @can('todos.use')
                            <a href="{{ route('tasks.index') }}" wire:navigate @click="sidebarOpen = false"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('tasks.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                                <i class="fa-duotone fa-list-check size-5"></i>
                                {{ __('My Tasks') }}
                            </a>
                        @endcan
                    </div>

                    @if ($ordersModuleEnabled)
                    @canany(['orders.view', 'users.view'])
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Orders') }}</h3>

                        @can('orders.view')
                        <a href="{{ route('orders.board') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('orders.board') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-chart-kanban size-5"></i>
                            {{ __('Order Board') }}
                        </a>
                        @canany(['orders.create', 'orders.update'])
                        <a href="{{ route('orders.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ (request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.create') || request()->routeIs('orders.edit')) ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-box-dollar size-5"></i>
                            <span class="flex-1">{{ __('Orders Management') }}</span>
                            @if ($urgentOpenOrdersCount > 0)
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ $urgentOpenOrdersCount }}
                                </span>
                            @endif
                        </a>
                        @endcanany
                        <a href="{{ route('invoices.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('invoices.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-file-invoice size-5"></i>
                            {{ __('Invoices') }}
                        </a>
                        @can('payments.view')
                        <a href="{{ route('payments.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('payments.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-credit-card size-5"></i>
                            {{ __('Payments') }}
                        </a>
                        @endcan
                        @endcan

                        @can('users.view')
                        <a href="{{ route('customers.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('customers.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-user size-5"></i>
                            {{ __('Customers') }}
                        </a>
                        @endcan
                    </div>
                    @endcanany
                    @endif

                    @if ($bookingsModuleEnabled)
                    @canany(['online-bookings.view', 'appointments.view', 'availability.view', 'garment-options.view'])
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Bookings') }}</h3>
                        @can('online-bookings.view')
                        <a href="{{ route('admin.online-bookings.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.online-bookings.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-calendar-plus size-5"></i>
                            {{ __('Online Bookings') }}
                        </a>
                        @endcan
                        @can('appointments.view')
                        <a href="{{ route('admin.appointments.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.appointments.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-calendar-check size-5"></i>
                            {{ __('Appointments') }}
                        </a>
                        @endcan
                        @can('availability.view')
                        <a href="{{ route('admin.availability.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.availability.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-clock size-5"></i>
                            {{ __('Availability') }}
                        </a>
                        @endcan
                        @can('garment-options.view')
                        <a href="{{ route('admin.garment-options.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('admin.garment-options.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-shirt size-5"></i>
                            {{ __('Garment Options') }}
                        </a>
                        @endcan
                    </div>
                    @endcanany
                    @endif

                    @if ($storefrontModuleEnabled)
                    @canany(['storefront.settings.manage', 'storefront.catalog.manage', 'storefront.cms.manage', 'storefront.shipping.manage', 'storefront.orders.manage', 'storefront.payments.manage'])
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Storefront') }}</h3>

                        @can('storefront.settings.manage')
                        <a href="{{ route('administration.storefront.settings') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.settings') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-store size-5"></i>
                            {{ __('Storefront Settings') }}
                        </a>
                        @endcan

                        @can('storefront.catalog.manage')
                        <a href="{{ route('administration.storefront.products') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.products*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-bag-shopping size-5"></i>
                            {{ __('Products') }}
                        </a>

                        @if (Route::has('administration.storefront.categories'))
                        <a href="{{ route('administration.storefront.categories') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.categories') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-layer-group size-5"></i>
                            {{ __('Product Categories') }}
                        </a>
                        @endif
                        @endcan

                        @can('storefront.cms.manage')
                        <a href="{{ route('administration.storefront.cms') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.cms') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-file-lines size-5"></i>
                            {{ __('Storefront CMS') }}
                        </a>
                        @endcan

                        @can('storefront.shipping.manage')
                        <a href="{{ route('administration.storefront.shipping') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.storefront.shipping') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-truck size-5"></i>
                            {{ __('Storefront Shipping') }}
                        </a>
                        @endcan
                    </div>
                    @endcanany
                    @endif

                    @if ($inventoryModuleEnabled)
                    @canany(['inventory.view', 'pos.view'])
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Inventory') }}</h3>

                        @can('pos.view')
                        <a href="{{ route('pos.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('pos.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-cash-register size-5"></i>
                            {{ __('POS') }}
                        </a>
                        @endcan

                        @can('inventory.view')

                        <a href="{{ route('inventory.stock') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.stock') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-shelves size-5"></i>
                            {{ __('Stock Overview') }}
                        </a>
                        <a href="{{ route('inventory.items.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.items.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-boxes-stacked size-5"></i>
                            {{ __('Items') }}
                        </a>
                        <a href="{{ route('inventory.suppliers.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.suppliers.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-truck size-5"></i>
                            {{ __('Suppliers') }}
                        </a>
                        <a href="{{ route('inventory.categories.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.categories.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-layer-group size-5"></i>
                            {{ __('Categories') }}
                        </a>
                        <a href="{{ route('inventory.units.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.units.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-ruler size-5"></i>
                            {{ __('Units') }}
                        </a>
                        <a href="{{ route('inventory.transactions.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('inventory.transactions.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-cart-flatbed-boxes size-5"></i>
                            {{ __('Transactions') }}
                        </a>
                        @endcan
                    </div>
                    @endcanany
                    @endif

                    @if ($installmentsModuleEnabled)
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
                    @endif

                    @can('roles.manage')
                    <div class="nav-group">
                        <h3 class="px-3 mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-white/35">{{ __('Administration') }}</h3>

                        <a href="{{ route('administration.settings') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.settings') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-gear size-5"></i>
                            {{ __('Settings') }}
                        </a>
                        @can('sms-settings.view')
                        <a href="{{ route('beem-configurations.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('beem-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-sliders size-5"></i>
                            {{ __('SMS Settings') }}
                        </a>
                        <a href="{{ route('whatsapp-configurations.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('whatsapp-configurations.*') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-brands fa-whatsapp size-5"></i>
                            {{ __('WhatsApp Settings') }}
                        </a>
                        <a href="{{ route('administration.email-setup') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ request()->routeIs('administration.email-setup') ? 'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.25)] font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-duotone fa-envelope-open-text size-5"></i>
                            {{ __('Email Setup') }}
                        </a>
                        @endcan
                    </div>
                    @endcan
                </nav>
            </aside>
        </div>

        {{-- Mobile Header --}}
        <header class="top-frosted-nav lg:hidden sticky top-3 z-30 h-16 mx-3 mt-3 rounded-2xl flex items-center justify-between px-3 sm:top-4 sm:mx-4 sm:mt-4 sm:px-4">
            <div class="relative flex items-center gap-3">
                <button 
                    @click="$dispatch('toggle-sidebar')"
                    class="p-2 rounded-xl bg-white/65 dark:bg-white/10 border border-white/65 dark:border-white/15 backdrop-blur-xl shadow-sm"
                >
                    <i class="fa-duotone fa-bars size-5 text-navy-900 dark:text-white"></i>
                </button>
                <span class="app-mobile-brand max-w-[9.5rem] truncate text-lg font-semibold text-navy-900 dark:text-white sm:max-w-none">{{ $businessName }}</span>
            </div>

            <div class="relative flex items-center gap-2">
                <a
                    href="{{ $calendarNavUrl }}"
                    wire:navigate
                    class="relative flex items-center justify-center size-9 text-navy-900 dark:text-white transition-colors hover:text-lime-600 dark:hover:text-lime-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-400/70 rounded-lg"
                    aria-label="{{ __('Calendar') }}"
                    title="{{ __('Calendar') }}"
                >
                    <i class="fa-duotone fa-calendar-days size-5"></i>
                    @if ($urgentOpenOrdersCount > 0)
                        <span class="pointer-events-none absolute -top-0.5 -right-0.5 flex size-2">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-orange-400 opacity-90"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-orange-500 ring-1 ring-white dark:ring-zinc-900"></span>
                        </span>
                    @endif
                </a>
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
        <main class="app-main-shell pt-0 min-h-screen overflow-x-hidden transition-[margin] duration-300 ease-out" :class="desktopSidebarCollapsed ? 'lg:ml-28' : 'lg:ml-72'">
            {{-- Desktop Header --}}
            <header class="top-frosted-nav hidden lg:flex sticky top-4 z-40 h-16 items-center justify-end gap-x-4 px-6 mx-4 mt-4 rounded-2xl">
                {{-- Right side actions --}}
                <div class="flex items-center gap-x-3">
                    <a
                        href="{{ $calendarNavUrl }}"
                        wire:navigate
                        class="relative flex items-center justify-center size-10 text-zinc-700 dark:text-zinc-200 transition-colors hover:text-lime-600 dark:hover:text-lime-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-400/70 rounded-lg"
                        aria-label="{{ __('Calendar') }}"
                        title="{{ __('Calendar') }}"
                    >
                        <i class="fa-duotone fa-calendar-days size-5"></i>
                        @if ($urgentOpenOrdersCount > 0)
                            <span class="pointer-events-none absolute top-1.5 right-1.5 flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-orange-400 opacity-90"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-orange-500 ring-1 ring-white dark:ring-zinc-900"></span>
                            </span>
                        @endif
                    </a>
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
            <div class="app-page-content p-3 sm:p-4 lg:p-6">
                {{ $slot }}
            </div>
        </main>

        <script>
            (() => {
                const syncActiveSidebarState = () => {
                    const nav = document.querySelector('.desktop-sidebar-nav');
                    if (!nav) {
                        return;
                    }

                    const activeLink = nav.querySelector('a[class*="bg-lime-400"]');
                    if (!activeLink) {
                        return;
                    }

                    nav.querySelectorAll('.nav-group.nav-group-active').forEach((group) => {
                        group.classList.remove('nav-group-active');
                    });

                    const activeGroup = activeLink.closest('.nav-group');
                    if (activeGroup) {
                        activeGroup.classList.add('nav-group-active');
                    }

                    const navRect = nav.getBoundingClientRect();
                    const activeRect = activeLink.getBoundingClientRect();
                    const isVisible = activeRect.top >= navRect.top + 8 && activeRect.bottom <= navRect.bottom - 8;

                    if (!isVisible) {
                        activeLink.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
                    }
                };

                document.addEventListener('DOMContentLoaded', syncActiveSidebarState);
                document.addEventListener('livewire:navigated', syncActiveSidebarState);
            })();
        </script>

        @fluxScripts
    </body>
</html>
