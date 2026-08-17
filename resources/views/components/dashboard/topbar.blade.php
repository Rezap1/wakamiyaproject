@props(['userRole', 'header' => 'Dashboard'])
<header class="hidden lg:flex h-20 bg-white border-b border-slate-200 shadow-sm items-center justify-between px-6 lg:px-8 sticky top-0 z-40 transition-colors duration-200">
    
    <!-- Left Section: Header -->
    <div class="flex items-center gap-4 flex-1">
        <!-- Mobile Menu Button -->
        <button onclick="toggleSidebar()" class="p-2.5 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 rounded-xl transition-all focus:outline-none border border-slate-200 shadow-sm flex items-center justify-center shrink-0 group lg:hidden">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <h1 class="text-2xl font-bold text-slate-800 tracking-tight ml-2">
            {{ $header }}
        </h1>
    </div>

    <!-- Center Section: Global Search -->
    <div class="hidden md:flex flex-1 justify-center relative z-50 px-4" x-data="globalSearch()">
        <form action="{{ Route::has('search.index') ? route('search.index') : '#' }}" method="GET" class="relative w-full max-w-md">
            <div class="flex items-center bg-white border border-slate-200 rounded-lg px-4 py-2.5 hover:border-blue-500 transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 w-full shadow-sm">
                <input type="text" name="q" x-model="query" @focus="open = true; fetchResults()" @input.debounce.500ms="fetchResults()" @click.outside="open = false" placeholder="Cari apa saja..." class="bg-transparent border-none outline-none text-[13px] font-medium w-full text-slate-800 placeholder-slate-400 focus:ring-0 py-0" autocomplete="off">
                <svg class="w-4 h-4 text-slate-500 ml-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            
            <!-- Overlay Dropdown -->
            <div x-show="open" style="display: none;" class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden" x-transition.opacity>
                
                <!-- Loading State -->
                <div x-show="loading" class="p-6 text-center text-sm font-medium text-slate-500 flex flex-col items-center">
                    <svg class="animate-spin h-6 w-6 text-emerald-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Sedang mencari...
                </div>
                
                <div x-show="!loading" class="max-h-96 overflow-y-auto custom-scrollbar">
                    
                    <!-- History View -->
                    <div x-show="mode === 'history' && history.length > 0">
                        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest flex justify-between">
                            Pencarian Terakhir
                            <a href="#" @click.prevent="clearHistory" class="text-blue-400 hover:underline">Bersihkan</a>
                        </div>
                        <template x-for="h in history">
                            <a :href="`{{ Route::has('search.index') ? route('search.index') : '#' }}?q=${h}`" class="block px-5 py-2.5 text-[13px] font-medium text-slate-500 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span x-text="h"></span>
                            </a>
                        </template>
                    </div>

                    <!-- Results View -->
                    <div x-show="mode === 'results'">
                        <template x-for="(items, group) in results" :key="group">
                            <div>
                                <div class="px-5 py-2 bg-slate-50 border-y border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest" x-text="group"></div>
                                <template x-for="item in items">
                                    <a :href="item.url" class="block px-5 py-3 hover:bg-slate-50 transition border-b border-slate-200 last:border-b-0">
                                        <p class="text-[13px] font-bold text-slate-800" x-text="item.title"></p>
                                        <p class="text-[11px] font-medium text-slate-500 truncate mt-0.5" x-text="item.desc"></p>
                                    </a>
                                </template>
                            </div>
                        </template>
                        
                        <div x-show="Object.keys(results).length === 0 && query !== ''" class="p-8 text-center text-sm font-medium text-slate-500">
                            Tidak ada hasil untuk "<span x-text="query" class="font-bold text-slate-800"></span>"
                        </div>
                    </div>

                </div>
                
                <div x-show="query !== ''" class="p-3 border-t border-slate-200 bg-slate-50 text-center">
                    <button type="submit" class="text-[12px] font-bold text-blue-400 hover:underline">Lihat Semua Hasil</button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('globalSearch', () => ({
                open: false,
                query: '',
                loading: false,
                mode: 'history',
                history: [],
                results: {},
                
                async fetchResults() {
                    this.loading = true;
                    try {
                        const res = await fetch(`{{ route('search.overlay') }}?q=${encodeURIComponent(this.query)}`);
                        const data = await res.json();
                        
                        if (data.status === 'history') {
                            this.mode = 'history';
                            this.history = data.data;
                        } else {
                            this.mode = 'results';
                            this.results = data.data;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    this.loading = false;
                },
                
                async clearHistory() {
                    try {
                        await fetch(`{{ route('search.clearHistory') }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json'
                            }
                        });
                        this.history = [];
                    } catch(e) {}
                }
            }))
        })
    </script>

    <!-- Right Section: Date, Time, Notif, User -->
    <div class="flex items-center justify-end flex-1 gap-5">
        
        <!-- Date & Time Widget -->
        <div class="hidden xl:flex items-center gap-6">
            <!-- Date -->
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <div>
                    <p class="text-[13px] font-bold text-slate-800 leading-tight">{{ \Carbon\Carbon::now()->translatedFormat('d M Y') }}</p>
                    <p class="text-[11px] text-slate-500 font-medium leading-tight">{{ \Carbon\Carbon::now()->translatedFormat('l') }}</p>
                </div>
            </div>
            <!-- Time -->
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p id="live-clock" class="text-[13px] font-bold text-slate-800 leading-tight">{{ \Carbon\Carbon::now()->format('H:i') }}</p>
                    <p class="text-[11px] text-slate-500 font-medium leading-tight">WIB</p>
                </div>
            </div>
            
            <script>
                // Live clock logic running in the background
                setInterval(function() {
                    const clock = document.getElementById('live-clock');
                    if (clock) {
                        const now = new Date();
                        const h = String(now.getHours()).padStart(2, '0');
                        const m = String(now.getMinutes()).padStart(2, '0');
                        clock.innerText = h + ':' + m;
                    }
                }, 1000);
            </script>
        </div>

        <!-- Notification Bell -->
        @php
            $userId = auth()->user()->email ?? (auth()->user()->User_ID ?? 'user@example.com');
            $roleData = app(\App\Services\Core\RoleService::class)->getRoleById(auth()->user()->Role_ID ?? '');
            $userRole = strtoupper(trim($roleData['Role_Name'] ?? session('role') ?? 'GUEST'));
            $notifService = app(\App\Services\Core\NotificationService::class);
            $unreadCount = $notifService->UnreadCount($userId, $userRole);
            $recentNotifs = $notifService->RecentNotification($userId, $userRole, 5);
        @endphp
        <div class="relative flex items-center justify-center" x-data="{ openNotif: false }">
            <button @click="openNotif = !openNotif" @click.outside="openNotif = false" class="relative p-2 text-slate-500 hover:text-slate-800 transition-colors focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                @if($unreadCount > 0)
                <span class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full border border-white">{{ $unreadCount }}</span>
                @endif
            </button>

            <!-- Dropdown -->
            <div x-show="openNotif" style="display: none;" x-transition class="absolute top-full right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                    <h3 class="font-bold text-slate-800 text-[13px]">Notifikasi</h3>
                    <span class="text-[10px] bg-emerald-100 text-blue-400 px-2 py-0.5 rounded font-bold uppercase border border-emerald-200">{{ $unreadCount }} Baru</span>
                </div>
                <div class="max-h-80 overflow-y-auto custom-scrollbar">
                    @forelse($recentNotifs as $n)
                    <a href="{{ $n['Action_URL'] ?? route('notifications.index') }}" class="block p-4 border-b border-slate-200/50 hover:bg-slate-50 transition {{ ($n['Is_Read'] ?? 'FALSE') === 'FALSE' ? 'bg-emerald-50' : '' }}">
                        <p class="text-[13px] font-bold text-slate-800 truncate">{{ $n['Title'] ?? 'Notification' }}</p>
                        <p class="text-[11px] font-medium text-slate-500 mt-1 truncate">{{ $n['Message'] ?? '' }}</p>
                        <p class="text-[10px] text-slate-500 mt-1 font-semibold">{{ \Carbon\Carbon::parse($n['Created_At'])->diffForHumans() }}</p>
                    </a>
                    @empty
                    <div class="p-6 text-center text-sm font-medium text-slate-500">Belum ada notifikasi</div>
                    @endforelse
                </div>
                <div class="p-3 text-center border-t border-slate-200 bg-slate-50">
                    <a href="{{ route('notifications.index') }}" class="text-[11px] font-bold text-blue-400 hover:underline uppercase tracking-wide">Lihat Semua</a>
                </div>
            </div>
        </div>

        <div class="h-8 w-px bg-slate-300 mx-1"></div>

        <!-- User Profile -->
        <div class="relative" x-data="{ openProfile: false }">
            <div @click="openProfile = !openProfile" @click.outside="openProfile = false" class="flex items-center gap-3 cursor-pointer group">
                <div class="h-11 w-11 rounded-full bg-white relative shrink-0">
                    <x-user-avatar class="w-11 h-11" text-size="text-sm" />
                </div>
                <div class="text-left hidden md:block">
                    <p class="text-[15px] font-bold text-slate-800 leading-tight group-hover:text-emerald-600 transition-colors">{{ auth()->user()->Username ?? 'Deri Alamsah' }}</p>
                    <p class="text-[13px] text-slate-500 font-medium mt-0.5">{{ $userRole ?? 'Administrator' }}</p>
                </div>
                <svg class="w-4 h-4 text-slate-500 ml-1 group-hover:text-slate-800 transition-colors" :class="{ 'rotate-180': openProfile }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
            </div>

            <!-- Profile Dropdown -->
            <div x-show="openProfile" style="display: none;" class="absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden" x-transition>
                <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                    <p class="text-[13px] font-bold text-slate-800 truncate">{{ auth()->user()->Email ?? 'user@example.com' }}</p>
                    <p class="text-[11px] font-medium text-slate-500 truncate mt-0.5">ID: {{ auth()->user()->User_ID ?? 'N/A' }}</p>
                </div>
                <div class="py-2">
                    <a href="{{ route('profile.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] font-bold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Profil Saya
                    </a>
                    @if(in_array($userRole ?? '', ['ADMINISTRATOR', 'HR', 'FINANCE']))
                    <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] font-bold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Pengaturan Sistem
                    </a>
                    @endif
                </div>
                <div class="border-t border-slate-100 py-2">
                    <a href="{{ Route::has('activity.index') ? route('activity.index') : '#' }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] font-bold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Aktivitas Log
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-[13px] font-bold text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Keluar (Logout)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>




