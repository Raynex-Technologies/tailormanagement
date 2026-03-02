<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="min-h-screen bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 dark:bg-red-900/30 mb-6">
                <i class="fa-duotone fa-ban w-12 h-12 text-red-600 dark:text-red-400"></i>
            </div>
            <h1 class="text-6xl font-bold text-zinc-900 dark:text-white mb-2">403</h1>
            <h2 class="text-2xl font-semibold text-zinc-700 dark:text-zinc-300 mb-4">Access Denied</h2>
            <p class="text-zinc-600 dark:text-zinc-400 mb-8">
                You don't have permission to access this resource. This may be because:
            </p>
            <ul class="text-left text-zinc-600 dark:text-zinc-400 mb-8 space-y-2">
                <li class="flex items-start">
                    <i class="fa-duotone fa-circle-xmark w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    You're trying to access a resource from another branch
                </li>
                <li class="flex items-start">
                    <i class="fa-duotone fa-circle-xmark w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    Your role doesn't have the required permissions
                </li>
                <li class="flex items-start">
                    <i class="fa-duotone fa-circle-xmark w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    The resource has been restricted
                </li>
            </ul>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ url()->previous() }}" class="inline-flex items-center justify-center px-6 py-3 border border-zinc-300 dark:border-zinc-600 rounded-lg text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-800 transition-colors">
                <i class="fa-duotone fa-arrow-left w-5 h-5 mr-2"></i>
                Go Back
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                <i class="fa-duotone fa-house w-5 h-5 mr-2"></i>
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
