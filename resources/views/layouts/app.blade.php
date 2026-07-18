<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen WAKAMIYA V1.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased selection:bg-primary-500 selection:text-white">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="w-72 bg-white shadow-2xl flex-shrink-0 z-20 flex flex-col transition-all duration-300 border-r border-gray-100">
            <!-- Brand / Logo Area -->
            <div class="h-24 flex items-center justify-center border-b border-gray-100 px-6 bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo LPK Wakamiya" class="w-12 h-12 object-contain" onerror="this.src='https://ui-avatars.com/api/?name=W&background=0097b2&color=fff&rounded=true&bold=true'">
                    <div class="flex flex-col">
                        <span class="text-xl font-extrabold text-gray-900 tracking-tight">LPK WAKAMIYA</span>
                        <span class="text-[10px] font-semibold text-primary-600 uppercase tracking-widest">Management System</span>
                    </div>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto py-6">
                <div class="px-4 mb-2">
                    <button type="button" class="flex items-center justify-between w-full px-2 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider hover:text-gray-600 transition-colors focus:outline-none" onclick="document.getElementById('menu-inti').classList.toggle('hidden'); document.getElementById('icon-inti').classList.toggle('rotate-180')">
                        <span>Modul Inti</span>
                        <svg id="icon-inti" class="w-4 h-4 transition-transform duration-200 {{ request()->routeIs('dashboard', 'users.*') ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <nav id="menu-inti" class="space-y-1 px-4 transition-all duration-300 overflow-hidden {{ request()->routeIs('dashboard', 'users.*') ? '' : 'hidden' }}">
                                        <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('dashboard') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dasbor Eksekutif
                    </a>
                                        
                                        <a href="{{ route('users.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('users.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Manajemen Pengguna
                    </a>
                                    </nav>

                <div class="px-4 mt-8 mb-2">
                    <button type="button" class="flex items-center justify-between w-full px-2 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider hover:text-gray-600 transition-colors focus:outline-none" onclick="document.getElementById('menu-hr').classList.toggle('hidden'); document.getElementById('icon-hr').classList.toggle('rotate-180')">
                        <span>Modul HR & Kepegawaian</span>
                        <svg id="icon-hr" class="w-4 h-4 transition-transform duration-200 {{ request()->routeIs('employees.*', 'teachers.*', 'departments.*', 'programs.*', 'batches.*', 'classes.*', 'positions.*') ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <nav id="menu-hr" class="space-y-1 px-4 transition-all duration-300 overflow-hidden {{ request()->routeIs('employees.*', 'teachers.*', 'departments.*', 'programs.*', 'batches.*', 'classes.*', 'positions.*') ? '' : 'hidden' }}">
                                        <a href="{{ route('employees.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('employees.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Data Pegawai
                    </a>
                                                            <a href="{{ route('teachers.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('teachers.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Tenaga Pendidik
                    </a>
                                                            <a href="{{ route('departments.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('departments.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Departemen
                    </a>
                                                            <a href="{{ route('programs.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('programs.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Master Program
                    </a>
                                                            <a href="{{ route('batches.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('batches.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Master Angkatan
                    </a>
                                                            <a href="{{ route('classes.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('classes.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Master Kelas
                    </a>
                                                            <a href="{{ route('positions.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('positions.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Jabatan / Posisi
                    </a>
                                    </nav>
                <div class="px-4 mt-8 mb-2">
                    <button type="button" class="flex items-center justify-between w-full px-2 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider hover:text-gray-600 transition-colors focus:outline-none" onclick="document.getElementById('menu-akademik').classList.toggle('hidden'); document.getElementById('icon-akademik').classList.toggle('rotate-180')">
                        <span>Modul Akademik & Penempatan</span>
                        <svg id="icon-akademik" class="w-4 h-4 transition-transform duration-200 {{ request()->routeIs('students.*', 'job-orders.*', 'interviews.*', 'matchings.*', 'applications.*', 'documents.*', 'coes.*', 'visas.*') ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <nav id="menu-akademik" class="space-y-1 px-4 transition-all duration-300 overflow-hidden {{ request()->routeIs('students.*', 'job-orders.*', 'interviews.*', 'matchings.*', 'applications.*', 'documents.*', 'coes.*', 'visas.*') ? '' : 'hidden' }}">
                                        <a href="{{ route('students.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('students.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                        Data Induk Siswa
                    </a>
                                        <a href="{{ route('job-orders.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('job-orders.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Job Order (Lowongan)
                    </a>
                                        <a href="{{ route('interviews.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('interviews.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Jadwal Interview
                    </a>
                    <a href="{{ route('matchings.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('matchings.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        Matching Kandidat
                    </a>
                    <a href="{{ route('applications.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('applications.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Aplikasi Kerja
                    </a>
                    <a href="{{ route('documents.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('documents.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h2m4 0h2m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Dokumen Legal
                    </a>
                    <a href="{{ route('coes.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('coes.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Sertifikat COE
                    </a>
                    <a href="{{ route('visas.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('visas.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Manajemen Visa
                    </a>
                </nav>
                <div class="px-4 mt-8 mb-2">
                    <button type="button" class="flex items-center justify-between w-full px-2 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider hover:text-gray-600 transition-colors focus:outline-none" onclick="document.getElementById('menu-sys').classList.toggle('hidden'); document.getElementById('icon-sys').classList.toggle('rotate-180')">
                        <span>Sistem & Konfigurasi</span>
                        <svg id="icon-sys" class="w-4 h-4 transition-transform duration-200 {{ request()->routeIs('developer-panel.*', 'companies.*', 'permissions.*', 'modules.*') ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <nav id="menu-sys" class="space-y-1 px-4 transition-all duration-300 overflow-hidden {{ request()->routeIs('developer-panel.*', 'companies.*', 'permissions.*', 'modules.*') ? '' : 'hidden' }}">
                                        <a href="{{ route('companies.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('companies.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Data Perusahaan
                    </a>
                                        <a href="{{ route('permissions.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('permissions.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Hak Akses (Permissions)
                    </a>
                                        <a href="{{ route('modules.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('modules.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        Modul Aplikasi
                    </a>
                                        <a href="{{ route('developer-panel.index') }}" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl {{ request()->routeIs('developer-panel.*') ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} transition-all">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                        Developer Panel
                    </a>
                </nav>
            </div>
            
            <div class="p-6 bg-gray-50/80 border-t border-gray-100">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-red-600 bg-red-50 rounded-xl hover:bg-red-600 hover:text-white transition-all shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col relative z-0 overflow-y-auto bg-[#F8FAFC]">
            <!-- Topbar -->
            <header class="h-20 bg-white/80 backdrop-blur-lg border-b border-gray-100 flex items-center justify-between px-10 sticky top-0 z-10">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">
                        @yield('header')
                    </h1>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden md:block">
                            <p class="text-sm font-bold text-gray-900">{{ auth()->user()->name ?? 'Pengguna' }}</p>
                            <p class="text-xs text-primary-600 font-medium">{{ auth()->user()->role_id ?? 'Admin' }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 text-white flex items-center justify-center font-bold text-lg shadow-md border-2 border-white ring-2 ring-primary-100">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="p-10 max-w-7xl mx-auto w-full">
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-green-50 text-green-700 border border-green-200 flex items-center shadow-sm">
                        <svg class="w-6 h-6 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 p-5 rounded-xl bg-red-50 text-red-700 border border-red-200 shadow-sm">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="font-bold">Terjadi Kesalahan!</span>
                        </div>
                        <ul class="list-disc pl-7 space-y-1 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

</body>
</html>
