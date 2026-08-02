<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-800 flex items-center justify-center min-h-screen">
    <div class="text-center px-6">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 mb-8">
            <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h1 class="text-6xl font-bold text-gray-900 dark:text-white mb-4">403</h1>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-slate-200 mb-4">Akses Ditolak (Tidak Sah)</h2>
        <p class="text-gray-600 dark:text-slate-400 max-w-md mx-auto mb-8">
            {{ $exception->getMessage() ?: 'Anda tidak memiliki wewenang untuk mengakses halaman ini atau melakukan tindakan tersebut.' }}
        </p>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 shadow-sm transition-all duration-200">
            Kembali ke Dashboard
        </a>
    </div>
</body>
</html>



