<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            :root {
                {!! \App\Support\SystemUiSettings::cssVariables() !!}
            }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <main>
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
