<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem Manajemen WAKAMIYA V1.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6 selection:bg-primary-500 selection:text-white">

    <div class="max-w-5xl w-full bg-white rounded-[2rem] shadow-2xl overflow-hidden flex flex-col md:flex-row relative">
        <!-- Dekorasi Background Latar Belakang -->
        <div class="absolute -top-32 -left-32 w-64 h-64 bg-primary-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob"></div>
        <div class="absolute -bottom-32 -right-32 w-64 h-64 bg-primary-300 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob animation-delay-2000"></div>

        <!-- Sisi Kiri: Branding & Logo -->
        <div class="w-full md:w-1/2 bg-gradient-to-br from-primary-500 to-primary-800 text-white p-14 flex flex-col justify-center relative overflow-hidden text-center md:text-left">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white to-transparent"></div>
            
            <div class="relative z-10 flex flex-col items-center md:items-start">
                <!-- Logo -->
                <div class="bg-white p-4 rounded-full shadow-xl mb-8 inline-block">
                    <img src="{{ asset('img/logo.png') }}" alt="Logo Wakamiya" class="w-24 h-24 object-contain" onerror="this.src='https://ui-avatars.com/api/?name=W&background=0097b2&color=fff&rounded=true&bold=true'">
                </div>
                
                <h1 class="text-4xl font-extrabold tracking-tight mb-3 drop-shadow-md">LPK WAKAMIYA</h1>
                <h2 class="text-lg font-semibold text-primary-100 uppercase tracking-widest mb-6">Management System V1.0</h2>
                
                <p class="text-primary-50 text-base leading-relaxed max-w-sm mt-4 hidden md:block opacity-90">
                    Sistem informasi terintegrasi untuk pengelolaan Sumber Daya Manusia, Akademik, Keuangan, dan Penempatan ke Jepang secara profesional.
                </p>
            </div>
        </div>

        <!-- Sisi Kanan: Form Login -->
        <div class="w-full md:w-1/2 p-10 md:p-16 flex flex-col justify-center bg-white relative z-10">
            <div class="mb-10 text-center md:text-left">
                <h3 class="text-3xl font-extrabold text-gray-900 mb-2">Selamat Datang</h3>
                <p class="text-gray-500 font-medium">Silakan masuk menggunakan akun Anda.</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-700 border border-red-200 text-sm flex items-start shadow-sm">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <ul class="list-disc pl-4 space-y-1 font-medium">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-bold text-gray-700 mb-2">Alamat Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                        </div>
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="pl-11 appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base transition-colors bg-gray-50 hover:bg-white" placeholder="admin@wakamiya.co.id">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-bold text-gray-700 mb-2">Kata Sandi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="pl-11 appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base transition-colors bg-gray-50 hover:bg-white" placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded cursor-pointer">
                        <label for="remember-me" class="ml-2 block text-sm font-medium text-gray-700 cursor-pointer">
                            Ingat Saya
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="#" class="font-bold text-primary-600 hover:text-primary-800 transition-colors">
                            Lupa sandi?
                        </a>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-base font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-1 hover:shadow-primary-500/30">
                        Masuk ke Dasbor
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
