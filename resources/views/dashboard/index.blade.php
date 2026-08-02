@extends('layouts.app')

@section('header', 'Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Welcome Banner -->
    <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Using a placeholder Mt Fuji image to match the request -->
        <div class="absolute inset-0 bg-cover bg-center opacity-90" style="background-image: url('https://images.unsplash.com/photo-1490806843957-31f4c9a91c65?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');"></div>
        <!-- Gradient overlay to ensure text readability -->
        <div class="absolute inset-0 bg-gradient-to-r from-white via-white/80 to-transparent"></div>
        <div class="relative p-8 md:p-10 z-10 w-full md:w-2/3">
            <h1 class="text-3xl font-extrabold text-slate-800 mb-2">Selamat Datang, {{ auth()->user()->Full_Name ?? 'Administrator' }}!</h1>
            <p class="text-blue-700 font-semibold mb-4">Anda berhasil login ke WAKAMIYA MANAGEMENT SYSTEM</p>
            <p class="text-slate-600 font-medium">Semangat bekerja dan terus memberikan yang terbaik.</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <!-- Pengguna -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">PENGGUNA</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['users'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Student -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">SISWA</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['students'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Teacher -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">GURU</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['teachers'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Batch -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">BATCH</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['batches'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- HR -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-pink-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 4 0 014 0z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">HR</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['hr'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Finance -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-teal-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">KEUANGAN</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['finance'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Academic -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">AKADEMIK</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['academic'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Marketing -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-rose-500 flex items-center justify-center text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">MARKETING</div>
                    <div class="text-2xl font-black text-slate-800">{{ $kpi['marketing'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Gaji Saya -->
        @php 
            $salStat = \App\Services\Core\DashboardHelperService::getSalaryStatus(auth()->id()); 
            $bgClass = $salStat == 'Diterima' ? 'bg-emerald-500 hover:bg-emerald-600 border-emerald-600' : 'bg-rose-500 hover:bg-rose-600 border-rose-600';
            $textClass = 'text-white';
            $iconBgClass = $salStat == 'Diterima' ? 'bg-emerald-600' : 'bg-rose-600';
        @endphp
        <a href="#" class="{{ $bgClass }} rounded-2xl p-4 shadow-sm border transition-colors block">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full {{ $iconBgClass }} flex items-center justify-center text-white shrink-0 shadow-inner">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-white/80 uppercase tracking-wider">GAJI BULAN INI</div>
                    <div class="text-sm font-black text-white">{{ $salStat }}</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Main Grid Section 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Grafik Student (Data Riil) -->
        <div class="lg:col-span-5 bg-white rounded-2xl p-6 shadow-sm border border-slate-200 flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Grafik Student
                </h3>
                <span class="text-xs text-slate-400 font-bold">6 Bulan Terakhir</span>
            </div>
            <div class="flex items-center justify-center gap-6 mb-4">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Pendaftar
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Aktif
                </div>
            </div>
            
            @php
                $growthData = $charts['studentGrowth'] ?? ['labels' => [], 'registered' => [], 'active' => []];
                $hasGrowthData = count($growthData['labels']) > 0 && (array_sum($growthData['registered']) > 0 || array_sum($growthData['active']) > 0);
            @endphp

            @if($hasGrowthData)
                <div class="space-y-3 flex-1">
                    @foreach($growthData['labels'] as $i => $label)
                        @php
                            $reg = $growthData['registered'][$i] ?? 0;
                            $act = $growthData['active'][$i] ?? 0;
                            $maxVal = max(max($growthData['registered'] ?? [1]), max($growthData['active'] ?? [1]), 1);
                            $regWidth = round(($reg / $maxVal) * 100);
                            $actWidth = round(($act / $maxVal) * 100);
                        @endphp
                        <div>
                            <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                                <span>{{ $label }}</span>
                                <span>{{ $reg }} / {{ $act }}</span>
                            </div>
                            <div class="flex gap-1">
                                <div class="h-1.5 bg-blue-400 rounded-full" style="width: {{ max($regWidth, 2) }}%"></div>
                                <div class="h-1.5 bg-emerald-400 rounded-full" style="width: {{ max($actWidth, 2) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex-1 flex items-center justify-center min-h-[200px]">
                    <p class="text-slate-400 text-sm">Belum ada data pertumbuhan siswa.</p>
                </div>
            @endif
        </div>

        <!-- Aktivitas Terbaru -->
        <div class="lg:col-span-4 bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2 mb-6">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Aktivitas Terbaru
            </h3>
            <div class="space-y-6">
                @forelse($recentActivities ?? [] as $activity)
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="flex-1">
                        @php
                            $rawAction = $activity['Action'] ?? 'Aktivitas';
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
                            $displayAction = $actionMap[strtoupper($rawAction)] ?? ($actionMap[$rawAction] ?? str_replace('_', ' ', $rawAction));
                        @endphp
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-bold text-slate-800">{{ $displayAction }}</h4>
                            <span class="text-xs text-slate-400">{{ isset($activity['Created_At']) ? \Carbon\Carbon::parse($activity['Created_At'])->diffForHumans() : 'Baru saja' }}</span>
                        </div>
                        @php
                            $rawModule = $activity['Module'] ?? '';
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
                            $displayModule = $moduleMap[strtoupper($rawModule)] ?? $rawModule;

                            $desc = $activity['New_Value'] ?? ($activity['Description'] ?? '');
                            if (is_string($desc) && str_starts_with($desc, '{')) {
                                $decoded = json_decode($desc, true);
                                if (is_array($decoded) && isset($decoded['description'])) {
                                    $desc = $decoded['description'];
                                } elseif (is_array($decoded) && isset($decoded['title'])) {
                                    $desc = $decoded['title'];
                                } else {
                                    $action = str_replace('_', ' ', $activity['Action'] ?? '');
                                    $refId = $activity['Reference_ID'] ?? '';
                                    $desc = "Aktivitas " . ucwords(strtolower($action)) . ($refId ? " pada {$refId}" : '');
                                }
                            }
                            
                            // Translate common auto-generated descriptions
                            if ($desc === 'Generated' || $desc === 'Aktivitas Generate_Invoice') {
                                $desc = 'Tagihan berhasil dibuat otomatis';
                            } elseif (str_starts_with($desc, 'Aktivitas Create pada')) {
                                $desc = str_replace('Aktivitas Create pada', 'Membuat data baru dengan referensi', $desc);
                            } elseif (str_starts_with($desc, 'Aktivitas Update pada')) {
                                $desc = str_replace('Aktivitas Update pada', 'Memperbarui data dengan referensi', $desc);
                            } elseif (str_starts_with($desc, 'Aktivitas Delete pada')) {
                                $desc = str_replace('Aktivitas Delete pada', 'Menghapus data dengan referensi', $desc);
                            }
                        @endphp
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ $displayModule }} — {{ $desc }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center text-slate-400 text-xs py-4">Belum ada aktivitas terbaru</div>
                @endforelse
            </div>
        </div>

        <!-- Pengumuman -->
        <div class="lg:col-span-3 bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Pengumuman
                </h3>
            </div>
            
            <div class="space-y-4">
                @forelse($notifications['pengumuman'] ?? [] as $notif)
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider shrink-0 mt-0.5">INFO</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">{{ $notif['title'] }}</h4>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $notif['description'] }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center text-slate-400 text-xs py-4">Belum ada pengumuman</div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Ringkasan Keuangan (Data Riil) -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2 mb-6">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                Ringkasan Keuangan
            </h3>
            
            @php
                $revChange = $financeSummary['revenue_change'] ?? 0;
                $expChange = $financeSummary['expense_change'] ?? 0;
                $balChange = $financeSummary['balance_change'] ?? 0;
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Pendapatan -->
                <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100 flex flex-col justify-between relative overflow-hidden">
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">TOTAL PENDAPATAN</div>
                        <div class="text-lg font-black text-slate-800">Rp {{ number_format($financeSummary['pendapatan'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        @if($revChange != 0)
                            <span class="text-xs font-bold {{ $revChange >= 0 ? 'text-emerald-500' : 'text-red-500' }}">{{ $revChange >= 0 ? '+' : '' }}{{ $revChange }}% <span class="text-slate-400 font-normal">dari bulan lalu</span></span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </div>
                </div>
                
                <!-- Pengeluaran -->
                <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100 flex flex-col justify-between relative overflow-hidden">
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">TOTAL PENGELUARAN</div>
                        <div class="text-lg font-black text-slate-800">Rp {{ number_format($financeSummary['pengeluaran'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        @if($expChange != 0)
                            <span class="text-xs font-bold {{ $expChange <= 0 ? 'text-emerald-500' : 'text-red-500' }}">{{ $expChange >= 0 ? '+' : '' }}{{ $expChange }}% <span class="text-slate-400 font-normal">dari bulan lalu</span></span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </div>
                </div>
                
                <!-- Saldo Bersih -->
                <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100 flex flex-col justify-between relative overflow-hidden">
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">SALDO BERSIH</div>
                        <div class="text-lg font-black text-slate-800">Rp {{ number_format($financeSummary['saldo'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        @if($balChange != 0)
                            <span class="text-xs font-bold {{ $balChange >= 0 ? 'text-blue-500' : 'text-red-500' }}">{{ $balChange >= 0 ? '+' : '' }}{{ $balChange }}% <span class="text-slate-400 font-normal">dari bulan lalu</span></span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Kegiatan -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Kalender Kegiatan
                </h3>
            </div>
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4 text-sm font-bold text-slate-800">
                    {{ \Carbon\Carbon::now()->translatedFormat('F Y') }}
                </div>
                <div class="flex bg-slate-50 rounded-lg p-1 border border-slate-200 text-xs font-bold">
                    <button class="px-3 py-1 rounded-md bg-blue-600 text-white shadow-sm">Bulan</button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @forelse($calendar ?? [] as $event)
                <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 flex flex-col justify-center text-center">
                    <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">{{ \Carbon\Carbon::parse($event['date'])->format('d M Y') }}</span>
                    <span class="text-xs font-bold text-slate-800">{{ $event['title'] }}</span>
                    <span class="text-[10px] text-slate-500 mt-1">{{ $event['type'] }}</span>
                </div>
                @empty
                <div class="col-span-3 text-center text-slate-400 text-xs py-4">Belum ada kalender kegiatan</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
