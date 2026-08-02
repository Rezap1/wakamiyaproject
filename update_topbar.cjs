const fs = require('fs');
const filePath = 'd:\\\\orderan\\\\wakamiya\\\\resources\\\\views\\\\components\\\\dashboard\\\\topbar.blade.php';

const topbarCode = `@props(['userRole', 'header' => ''])

<header class="h-20 bg-white border-b border-slate-100 shadow-[0_4px_20px_-15px_rgba(0,0,0,0.05)] flex items-center justify-between px-6 lg:px-8 sticky top-0 z-10 transition-colors duration-200">
    
    <!-- Left Section: Header & Breadcrumb -->
    <div class="flex items-center gap-4">
        <!-- Mobile Menu Button -->
        <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-500 hover:text-blue-600 rounded-lg hover:bg-slate-50 transition-colors focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <div class="flex flex-col">
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                {{ $header }}
            </h1>
            <div class="hidden md:flex items-center text-[11px] font-semibold text-slate-400 mt-0.5 space-x-2 uppercase tracking-widest">
                <span class="text-blue-600">{{ $userRole }}</span>
                <span class="text-slate-300">/</span>
                <span>{{ $header }}</span>
            </div>
        </div>
    </div>

    <!-- Right Section: Search, Notif, User -->
    <div class="flex items-center">
        
        <!-- Global Search Component -->
        <div class="hidden lg:flex items-center relative z-50 mr-6" x-data="globalSearch()">
            <form action="{{ route('search.index') }}" method="GET" class="relative">
                <div class="flex items-center bg-[#f8fafc] border border-slate-100 rounded-full px-5 py-2.5 hover:border-blue-200 hover:bg-white transition-all focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-50 focus-within:bg-white w-[300px] xl:w-[400px]">
                    <svg class="w-4 h-4 text-slate-400 mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" x-model="query" @focus="open = true; fetchResults()" @input.debounce.500ms="fetchResults()" @click.outside="open = false" placeholder="Cari data atau modul..." class="bg-transparent border-none outline-none text-[13px] font-medium w-full text-slate-700 placeholder-slate-400 focus:ring-0" autocomplete="off">
                    
                    <!-- Shortcut Hint -->
                    <div class="hidden xl:flex items-center ml-2 space-x-1">
                        <kbd class="px-1.5 py-0.5 text-[10px] font-bold text-slate-400 bg-white border border-slate-200 rounded">Ctrl</kbd>
                        <kbd class="px-1.5 py-0.5 text-[10px] font-bold text-slate-400 bg-white border border-slate-200 rounded">K</kbd>
                    </div>
                </div>
                
                <!-- Overlay Dropdown -->
                <div x-show="open" style="display: none;" class="absolute top-full right-0 mt-3 w-96 bg-white rounded-2xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] border border-slate-100 overflow-hidden" x-transition.opacity>
                    
                    <!-- Loading State -->
                    <div x-show="loading" class="p-6 text-center text-sm font-medium text-slate-500 flex flex-col items-center">
                        <svg class="animate-spin h-6 w-6 text-blue-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Sedang mencari...
                    </div>
                    
                    <div x-show="!loading" class="max-h-96 overflow-y-auto custom-scrollbar">
                        
                        <!-- History View -->
                        <div x-show="mode === 'history' && history.length > 0">
                            <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-widest flex justify-between">
                                Pencarian Terakhir
                                <a href="#" @click.prevent="clearHistory" class="text-blue-500 hover:underline">Bersihkan</a>
                            </div>
                            <template x-for="h in history">
                                <a :href="\`{{ route('search.index') }}?q=\${h}\`" class="block px-5 py-2.5 text-[13px] font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span x-text="h"></span>
                                </a>
                            </template>
                        </div>

                        <!-- Results View -->
                        <div x-show="mode === 'results'">
                            <template x-for="(items, group) in results" :key="group">
                                <div>
                                    <div class="px-5 py-2 bg-slate-50 border-y border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-widest" x-text="group"></div>
                                    <template x-for="item in items">
                                        <a :href="item.url" class="block px-5 py-3 hover:bg-slate-50 transition border-b border-slate-50 last:border-b-0">
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
                    
                    <div x-show="query !== ''" class="p-3 border-t border-slate-100 bg-slate-50 text-center">
                        <button type="submit" class="text-[12px] font-bold text-blue-600 hover:underline">Lihat Semua Hasil</button>
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
                            const res = await fetch(\`{{ route('search.overlay') }}?q=\${encodeURIComponent(this.query)}\`);
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
                            await fetch(\`{{ route('search.clearHistory') }}\`, {
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

        <!-- Date & Time Widget -->
        <div class="hidden xl:flex items-center gap-3 mr-6 border-r border-slate-200 pr-6">
            <!-- Date -->
            <div class="flex items-center gap-2.5 bg-[#f8fafc] border border-slate-100 px-3.5 py-2 rounded-xl">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <div>
                    <p class="text-[11px] font-bold text-slate-700 leading-none">{{ \\Carbon\\Carbon::now()->translatedFormat('d M Y') }}</p>
                    <p class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide">{{ \\Carbon\\Carbon::now()->translatedFormat('l') }}</p>
                </div>
            </div>
            <!-- Time -->
            <div class="flex items-center gap-2.5 bg-[#f8fafc] border border-slate-100 px-3.5 py-2 rounded-xl">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="text-[11px] font-bold text-slate-700 leading-none">{{ \\Carbon\\Carbon::now()->format('H:i') }}</p>
                    <p class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide">WIB</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-1.5 mr-6 border-r border-slate-200 pr-6">
            <!-- Activity -->
            <a href="{{ route('activity.index') }}" class="relative p-2 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-colors hover:bg-slate-50 rounded-full w-10 h-10" title="Activity Center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </a>

            <!-- Notification Bell -->
            @php
                $userId = auth()->user()->Employee_ID ?? auth()->user()->User_ID;
                $notifService = app(\\App\\Services\\Core\\NotificationService::class);
                $myNotifs = $notifService->getMyNotifications($userId);
                $unreadCount = count(array_filter($myNotifs, function($n) { return ($n['Is_Read'] ?? 'FALSE') === 'FALSE'; }));
                $recentNotifs = array_slice($myNotifs, 0, 5);
            @endphp
            <div class="relative group">
                <a href="{{ route('notifications.index') }}" class="relative p-2 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-colors hover:bg-slate-50 rounded-full w-10 h-10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    @if($unreadCount > 0)
                    <span class="absolute top-2 right-2.5 flex items-center justify-center w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                    @endif
                </a>

                <!-- Dropdown -->
                <div class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                        <h3 class="font-bold text-slate-800 text-[13px]">Notifications</h3>
                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-bold uppercase">{{ $unreadCount }} New</span>
                    </div>
                    <div class="max-h-80 overflow-y-auto custom-scrollbar">
                        @forelse($recentNotifs as $n)
                        <a href="{{ $n['Action_URL'] ?? route('notifications.index') }}" class="block p-4 border-b border-slate-50 hover:bg-slate-50 transition {{ ($n['Is_Read'] ?? 'FALSE') === 'FALSE' ? 'bg-blue-50/20' : '' }}">
                            <p class="text-[13px] font-bold text-slate-800 truncate">{{ $n['Title'] ?? 'Notification' }}</p>
                            <p class="text-[11px] font-medium text-slate-500 mt-1 truncate">{{ $n['Message'] ?? '' }}</p>
                            <p class="text-[10px] text-slate-400 mt-1 font-semibold">{{ \\Carbon\\Carbon::parse($n['Created_At'])->diffForHumans() }}</p>
                        </a>
                        @empty
                        <div class="p-6 text-center text-sm font-medium text-slate-500">No recent notifications</div>
                        @endforelse
                    </div>
                    <div class="p-3 text-center border-t border-slate-100 bg-slate-50">
                        <a href="{{ route('notifications.index') }}" class="text-[11px] font-bold text-blue-600 hover:underline uppercase tracking-wide">View All</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Profile -->
        <div class="flex items-center gap-3 cursor-pointer group">
            <div class="text-right hidden md:block">
                <p class="text-[13px] font-bold text-slate-700 leading-tight">{{ auth()->user()->Username ?? 'Pengguna' }}</p>
                <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest">{{ $userRole ?? 'Unknown' }}</p>
            </div>
            <div class="h-10 w-10 rounded-full bg-slate-100 relative shrink-0 border-2 border-white ring-2 ring-transparent group-hover:ring-blue-100 transition-all">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->Username ?? 'U') }}&background=2563eb&color=fff" alt="User" class="w-full h-full rounded-full">
            </div>
        </div>
    </div>
</header>
`;

fs.writeFileSync(filePath, topbarCode, 'utf8');
console.log('Topbar updated successfully');
