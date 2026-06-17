<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-hidden bg-zinc-100 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <main class="h-screen overflow-hidden">
            {{ $slot }}
        </main>

        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            aria-label="{{ __('Back to dashboard') }}"
            title="{{ __('Back to dashboard') }}"
            class="fixed bottom-5 left-5 z-50 flex size-14 items-center justify-center rounded-full bg-zinc-950 text-white shadow-2xl ring-1 ring-white/20 transition hover:scale-105 hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-400 dark:bg-lime-400 dark:text-zinc-950 dark:hover:bg-lime-300"
        >
            <i class="fa-duotone fa-gauge-high text-xl"></i>
        </a>

        @fluxScripts
    </body>
</html>
