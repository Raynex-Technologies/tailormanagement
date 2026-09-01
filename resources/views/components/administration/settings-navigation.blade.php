@props([
    'active' => 'business',
    'wireTabs' => false,
])

@php
    $settingsTabs = [
        ['key' => 'business', 'label' => __('Business Settings'), 'href' => route('administration.settings', ['tab' => 'business'])],
        ['key' => 'email', 'label' => __('Email Settings'), 'href' => route('administration.email-setup')],
        ['key' => 'orders', 'label' => __('Order Settings'), 'href' => route('administration.settings', ['tab' => 'orders'])],
        ['key' => 'payment_methods', 'label' => __('Payment Methods'), 'href' => route('administration.settings', ['tab' => 'payment_methods'])],
        ['key' => 'tax', 'label' => __('Tax Settings'), 'href' => route('administration.settings', ['tab' => 'tax'])],
        ['key' => 'invoice_templates', 'label' => __('Invoice Templates'), 'href' => route('administration.settings', ['tab' => 'invoice_templates'])],
    ];

    if (auth()->user()->can('settings.system-ui.view')) {
        $settingsTabs[] = ['key' => 'system_ui', 'label' => __('System UI Settings'), 'href' => route('administration.settings', ['tab' => 'system_ui'])];
    }
@endphp

<style>
    .settings-tabs-scroller {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .settings-tabs-scroller::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
</style>

<nav
    aria-label="{{ __('Settings sections') }}"
    class="relative mt-6 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-1.5 dark:border-zinc-700 dark:bg-zinc-900/60"
    x-data="{
        atStart: true,
        atEnd: false,
        updateScrollState() {
            const scroller = this.$refs.scroller;
            this.atStart = scroller.scrollLeft <= 4;
            this.atEnd = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 4;
        },
        revealActiveTab() {
            this.$nextTick(() => {
                this.$el.querySelector('[aria-current=page]')?.scrollIntoView({
                    block: 'nearest',
                    inline: 'center',
                });
                this.updateScrollState();
            });
        },
    }"
    x-init="revealActiveTab()"
    x-on:resize.window="updateScrollState()"
    data-settings-navigation
>
    <div
        x-ref="scroller"
        x-on:scroll.passive="updateScrollState()"
        class="settings-tabs-scroller touch-pan-x overflow-x-auto overscroll-x-contain scroll-smooth"
        style="-webkit-overflow-scrolling: touch;"
        data-settings-tabs-scroller
    >
        <div class="flex w-max min-w-full items-center gap-2 px-1">
            @foreach ($settingsTabs as $settingsTab)
                @php($isActiveSettingsTab = $active === $settingsTab['key'])

                @if ($wireTabs && $settingsTab['key'] !== 'email')
                    <button
                        type="button"
                        wire:key="settings-tab-{{ $settingsTab['key'] }}"
                        wire:click="$set('tab', '{{ $settingsTab['key'] }}')"
                        x-on:click="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }))"
                        data-settings-tab="{{ $settingsTab['key'] }}"
                        @if ($isActiveSettingsTab) aria-current="page" @endif
                        @class([
                            'min-h-10 shrink-0 whitespace-nowrap rounded-xl px-4 py-2 text-center text-sm font-semibold transition duration-150 first:ml-auto last:mr-auto',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-50 dark:focus-visible:ring-offset-zinc-900',
                            'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.18)]' => $isActiveSettingsTab,
                            'text-zinc-600 hover:bg-lime-50 hover:text-lime-900 dark:text-zinc-300 dark:hover:bg-lime-400/10 dark:hover:text-lime-200' => ! $isActiveSettingsTab,
                        ])
                    >
                        {{ $settingsTab['label'] }}
                    </button>
                @else
                    <a
                        wire:key="settings-tab-{{ $settingsTab['key'] }}"
                        href="{{ $settingsTab['href'] }}"
                        wire:navigate
                        x-on:click="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }))"
                        data-settings-tab="{{ $settingsTab['key'] }}"
                        @if ($isActiveSettingsTab) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-xl px-4 py-2 text-center text-sm font-semibold transition duration-150 first:ml-auto last:mr-auto',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-50 dark:focus-visible:ring-offset-zinc-900',
                            'bg-lime-400 text-navy-900 shadow-[0_4px_12px_rgba(191,255,0,0.18)]' => $isActiveSettingsTab,
                            'text-zinc-600 hover:bg-lime-50 hover:text-lime-900 dark:text-zinc-300 dark:hover:bg-lime-400/10 dark:hover:text-lime-200' => ! $isActiveSettingsTab,
                        ])
                    >
                        {{ $settingsTab['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    <button
        type="button"
        x-cloak
        x-show="! atStart"
        x-on:click="$refs.scroller.scrollBy({ left: -240, behavior: 'smooth' })"
        class="absolute inset-y-1.5 left-1.5 flex w-9 items-center justify-center rounded-l-xl bg-gradient-to-r from-zinc-50 via-zinc-50/95 to-transparent text-zinc-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tailorpro-primary)] dark:from-zinc-900 dark:via-zinc-900/95 dark:text-zinc-300"
        aria-label="{{ __('Scroll settings tabs left') }}"
    >
        <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
    </button>

    <button
        type="button"
        x-cloak
        x-show="! atEnd"
        x-on:click="$refs.scroller.scrollBy({ left: 240, behavior: 'smooth' })"
        class="absolute inset-y-1.5 right-1.5 flex w-9 items-center justify-center rounded-r-xl bg-gradient-to-l from-zinc-50 via-zinc-50/95 to-transparent text-zinc-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tailorpro-primary)] dark:from-zinc-900 dark:via-zinc-900/95 dark:text-zinc-300"
        aria-label="{{ __('Scroll settings tabs right') }}"
    >
        <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
    </button>
</nav>
