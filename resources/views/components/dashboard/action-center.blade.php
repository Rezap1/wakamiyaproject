@props(['title', 'description', 'kpi' => [], 'quickActions' => [], 'reminders' => [], 'recentActivities' => []])

<div class="space-y-8">
    
    <!-- Top Welcome & Date -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">{{ $title }}</h2>
            <p class="text-sm font-medium text-slate-500 mt-2">{{ $description }}</p>
        </div>
        <div class="text-sm font-bold text-slate-400 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }} | Pusat Aksi Enterprise
        </div>
    </div>

    <!-- KPI Cards (Row 1) -->
        @if(count($kpi) > 0)
    @php
        $showGaji = !in_array('Gaji Bulan Ini', array_column($kpi, 'title')) && !request()->routeIs('dashboard.student');
        $totalCards = count($kpi) + ($showGaji ? 1 : 0);
        $gridClass = match($totalCards) {
            1 => 'lg:grid-cols-1',
            2 => 'lg:grid-cols-2',
            3 => 'lg:grid-cols-3',
            4 => 'lg:grid-cols-4',
            6 => 'lg:grid-cols-6',
            default => 'lg:grid-cols-5'
        };
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 {{ $gridClass }} gap-4">
        @foreach($kpi as $item)
            @if(isset($item['title']) && $item['title'] === 'Gaji Bulan Ini')
                @php 
                    $salStat = $item['value'];
                    $bgClass = $salStat == 'Diterima' ? 'bg-emerald-500 hover:bg-emerald-600 border-emerald-600' : 'bg-rose-500 hover:bg-rose-600 border-rose-600';
                @endphp
                <div class="{{ $bgClass }} rounded-2xl p-5 shadow-sm border flex flex-col justify-between hover:shadow-md transition-all relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                        <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h4 class="text-xs font-bold text-white/80 uppercase tracking-wider mb-2 relative z-10">{{ $item['title'] }}</h4>
                    <div class="text-2xl font-black text-white relative z-10">{{ $item['value'] }}</div>
                    @if(isset($item['link']))
                        <a href="{{ $item['link'] }}" class="absolute inset-0 z-20 block w-full h-full cursor-pointer"></a>
                    @endif
                </div>
            @else
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform">
                        <x-dynamic-component :component="View::exists('components.icons.' . ($item['icon'] ?? 'chart-bar')) ? 'icons.' . ($item['icon'] ?? 'chart-bar') : 'icons.bell'" class="w-24 h-24 text-{{ $item['color'] ?? 'blue' }}-500" />
                    </div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 relative z-10">{{ $item['title'] }}</h4>
                    <div class="text-2xl font-black text-{{ $item['color'] ?? 'slate' }}-600 relative z-10">{{ $item['value'] }}</div>
                    @if(isset($item['link']))
                        <a href="{{ $item['link'] }}" class="absolute inset-0 z-20 block w-full h-full cursor-pointer"></a>
                    @endif
                </div>
            @endif
        @endforeach
        
        <!-- Dynamic Gaji Saya Card for non-teachers who use action-center -->
        <!-- Dynamic Gaji Saya Card for non-teachers who use action-center -->
        @if($showGaji)
            @php 
                $salStat = \App\Services\Core\DashboardHelperService::getSalaryStatus(auth()->id()); 
                $bgClass = $salStat == 'Diterima' ? 'bg-emerald-500 hover:bg-emerald-600 border-emerald-600' : 'bg-rose-500 hover:bg-rose-600 border-rose-600';
                $iconBgClass = $salStat == 'Diterima' ? 'bg-emerald-600' : 'bg-rose-600';
            @endphp
            <div class="{{ $bgClass }} rounded-2xl p-5 shadow-sm border flex flex-col justify-between hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                    <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h4 class="text-xs font-bold text-white/80 uppercase tracking-wider mb-2 relative z-10">Gaji Bulan Ini</h4>
                <div class="text-xl font-black text-white relative z-10">{{ $salStat }}</div>
                <a href="#" class="absolute inset-0 z-20"></a>
            </div>
        @endif
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Quick Actions & Reminders -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                <h4 class="text-lg font-extrabold text-slate-800 mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Aksi Cepat
                </h4>
                
                @if(count($quickActions) > 0)
                    <div class="flex flex-wrap gap-3">
                        @foreach($quickActions as $action)
                            <a href="{{ $action['url'] ?? '#' }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-{{ $action['color'] ?? 'slate' }}-50 text-{{ $action['color'] ?? 'slate' }}-700 border border-{{ $action['color'] ?? 'slate' }}-200 rounded-xl text-sm font-bold hover:bg-{{ $action['color'] ?? 'slate' }}-100 hover:shadow-sm transition-all focus:ring-2 focus:ring-{{ $action['color'] ?? 'slate' }}-500 focus:outline-none">
                                @if(isset($action['icon']))
                                    <x-dynamic-component :component="View::exists('components.icons.' . $action['icon']) ? 'icons.' . $action['icon'] : 'icons.bell'" class="w-4 h-4 mr-2" />
                                @endif
                                {{ $action['title'] }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <x-universal.empty-state title="Tidak ada Aksi Cepat" description="Anda tidak memiliki akses aksi cepat saat ini." />
                @endif
            </div>

            <!-- Reminders / To-Do -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h4 class="text-lg font-extrabold text-slate-800 mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Tugas & Pengingat
                </h4>
                
                @if(count($reminders) > 0)
                    <div class="space-y-3">
                        @foreach($reminders as $reminder)
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-amber-100 bg-amber-50/50 hover:bg-amber-50 transition-colors">
                                <div class="bg-amber-100 text-amber-600 p-2 rounded-lg shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <h5 class="text-sm font-bold text-slate-800">{{ $reminder['title'] }}</h5>
                                    <p class="text-xs text-slate-600 mt-1">{{ $reminder['description'] }}</p>
                                    @if(isset($reminder['action_url']))
                                        <a href="{{ $reminder['action_url'] }}" class="inline-block mt-2 text-xs font-bold text-blue-600 hover:text-blue-800">Tindak Lanjuti &rarr;</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-universal.empty-state title="Semua Selesai!" description="Tidak ada pengingat atau tugas tertunda untuk Anda." />
                @endif
            </div>
            
            <!-- Custom Slot (For Charts) -->
            {{ $slot }}

        </div>

        <!-- Right Column: Recent Activity & Notifications -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Recent Activity -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 h-full">
                <h4 class="text-lg font-extrabold text-slate-800 mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Aktivitas Terbaru
                </h4>
                
                @if(count($recentActivities) > 0)
                    <div class="relative pl-4 space-y-6 before:absolute before:inset-y-0 before:left-[11px] before:w-0.5 before:bg-slate-200">
                        @foreach($recentActivities as $activity)
                            @php
                                $rawTitle = $activity['title'] ?? 'Aktivitas';
                                $actionMap = [
                                    'CREATE' => 'Membuat Data',
                                    'UPDATE' => 'Memperbarui Data',
                                    'DELETE' => 'Menghapus Data',
                                    'Generate_Invoice' => 'Membuat Tagihan',
                                    'PAY' => 'Pembayaran',
                                    'VERIFY' => 'Verifikasi',
                                    'PUBLISH' => 'Menerbitkan',
                                    'LOGIN' => 'Login',
                                ];
                                $displayTitle = $actionMap[strtoupper($rawTitle)] ?? ($actionMap[$rawTitle] ?? str_replace('_', ' ', $rawTitle));
                                
                                $rawDesc = $activity['description'] ?? '';
                                
                                // Parse Module — Desc
                                $moduleMap = [
                                    'FINANCE' => 'Keuangan',
                                    'HR' => 'SDM',
                                    'ACADEMIC' => 'Akademik',
                                    'MARKETING' => 'Marketing',
                                    'STUDENT' => 'Siswa',
                                    'TEACHER' => 'Guru',
                                    'EMPLOYEE' => 'Pegawai',
                                    'DOCUMENT' => 'Dokumen',
                                    'SYSTEM' => 'Sistem',
                                    'USER' => 'Pengguna',
                                    'ACTIVITY_LOG' => 'Log Aktivitas',
                                ];
                                
                                $descParts = explode(' — ', $rawDesc, 2);
                                if (count($descParts) == 2) {
                                    $mod = trim($descParts[0]);
                                    $det = trim($descParts[1]);
                                    
                                    $mod = $moduleMap[strtoupper($mod)] ?? $mod;
                                    
                                    if ($det === 'Generated' || $det === 'Aktivitas Generate_Invoice') {
                                        $det = 'Tagihan berhasil dibuat otomatis';
                                    } elseif (str_starts_with($det, 'Aktivitas Create pada')) {
                                        $det = str_replace('Aktivitas Create pada', 'Membuat data baru dengan referensi', $det);
                                    } elseif (str_starts_with($det, 'Aktivitas Update pada')) {
                                        $det = str_replace('Aktivitas Update pada', 'Memperbarui data dengan referensi', $det);
                                    } elseif (str_starts_with($det, 'Aktivitas Delete pada')) {
                                        $det = str_replace('Aktivitas Delete pada', 'Menghapus data dengan referensi', $det);
                                    }
                                    
                                    $displayDesc = $mod . ' — ' . $det;
                                } else {
                                    $displayDesc = $rawDesc;
                                }
                            @endphp
                            <div class="relative">
                                <div class="absolute -left-6 bg-white w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center">
                                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                                </div>
                                <p class="text-xs font-bold text-slate-500 mb-1">{{ $activity['time'] ?? 'Baru saja' }}</p>
                                <p class="text-sm font-semibold text-slate-800">{{ $displayTitle }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ $displayDesc }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-universal.empty-state title="Tidak ada aktivitas" description="Belum ada catatan aktivitas terbaru." />
                @endif
            </div>

        </div>
    </div>
</div>
