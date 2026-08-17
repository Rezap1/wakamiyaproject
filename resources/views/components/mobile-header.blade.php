@props(['title' => 'WMS', 'showBack' => false, 'backUrl' => null])

<!-- STANDARDIZED WMS MOBILE HEADER (WAKAMIYA BRAND MATCHING SIDEBAR & LOGO) -->
<div class="lg:hidden sticky top-0 z-40 bg-white/95 backdrop-blur-xl border-b border-slate-200/80 px-4 py-3 shadow-xs select-none">
    <div class="flex items-center justify-between max-w-md mx-auto">
        <!-- LEFT: HAMBURGER OR BACK BUTTON WITH LOGO -->
        <div class="flex items-center gap-3">
            @if($showBack)
                <a href="{{ $backUrl ?? url()->previous() }}" class="p-2 rounded-xl bg-slate-100 text-slate-800 hover:bg-slate-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </a>
            @else
                <button onclick="toggleSidebar()" class="p-2 rounded-xl bg-slate-100 text-slate-800 hover:bg-slate-200 transition-colors active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            @endif

            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-[#111827] rounded-full flex items-center justify-center border-2 border-sky-400 p-0.5 shadow-sm">
                    <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo" class="w-full h-full object-cover rounded-full bg-white" onerror="this.style.display='none'">
                </div>
                <h1 class="text-base font-black text-[#111827] tracking-tight">
                    {{ $title }}
                </h1>
            </div>
        </div>

        <!-- RIGHT: NOTIFICATION & DYNAMIC PROFILE AVATAR -->
        <div class="flex items-center gap-2.5">
            <a href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}" class="relative p-2 rounded-xl text-slate-600 hover:text-sky-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="absolute top-0.5 right-0.5 w-4 h-4 bg-rose-500 text-white text-[9px] font-black rounded-full border-2 border-white flex items-center justify-center">3</span>
            </a>

            <a href="{{ route('profile.index') }}" class="block">
                <x-user-avatar class="w-8.5 h-8.5" text-size="text-[10px]" />
            </a>
        </div>
    </div>
</div>
