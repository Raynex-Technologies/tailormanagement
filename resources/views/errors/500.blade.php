<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="min-h-screen bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 dark:bg-red-900/30 mb-6">
                <svg class="w-12 h-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h1 class="text-6xl font-bold text-zinc-900 dark:text-white mb-2">500</h1>
            <h2 class="text-2xl font-semibold text-zinc-700 dark:text-zinc-300 mb-4">Server Error</h2>
            <p class="text-zinc-600 dark:text-zinc-400 mb-8">
                Something went wrong on our end. Our team has been notified and is working on a fix. 
                Please try again later or contact support if the problem persists.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ url()->previous() }}" class="inline-flex items-center justify-center px-6 py-3 border border-zinc-300 dark:border-zinc-600 rounded-lg text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-800 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Try Again
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
