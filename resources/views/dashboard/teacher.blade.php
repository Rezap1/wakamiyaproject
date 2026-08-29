@extends('layouts.app')
@section('header', 'Dashboard Pengajar')

@section('content')
@php
    $ctx = app(\App\Services\Dashboard\DashboardContextService::class)->getContext();
    $greeting = $ctx['greeting'] ?? 'Selamat datang';
    $greetingIcon = $ctx['greeting_icon'] ?? '👋';
    $dateFormatted = $ctx['dateFormatted'] ?? date('l, d F Y');
    $timeFormatted = \Carbon\Carbon::now('Asia/Jakarta')->format('H:i');

    $teacherName = $ctx['user_name'] ?? 'Pengajar';

    $kpiList = [
        ['title' => "Jadwal Hari Ini", 'value' => $kpi['today_classes'] ?? 0, 'icon' => 'calendar', 'color' => 'blue', 'link' => route('teacher.workspace.schedule')],
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_classes'] ?? 0, 'icon' => 'view-boards', 'color' => 'indigo', 'link' => route('teacher.workspace.classes')],
        ['title' => 'Total Siswa', 'value' => $kpi['my_students'] ?? 0, 'icon' => 'user-group', 'color' => 'emerald', 'link' => route('teacher.workspace.classes')],
        ['title' => 'Kehadiran Hari Ini', 'value' => $kpi['attendance_today'] ?? 0, 'icon' => 'clock', 'color' => 'amber', 'link' => route('teacher.workspace.attendances')],
        ['title' => 'Kelas Berikutnya', 'value' => $kpi['next_class'] ?? 'Tidak ada', 'icon' => 'play', 'color' => 'rose', 'link' => '#'],
    ];

    $quickActions = [
        ['title' => 'Jadwal Mengajar', 'url' => route('teacher.workspace.schedule'), 'icon' => 'calendar', 'color' => 'blue'],
        ['title' => 'Kelas Saya', 'url' => route('teacher.workspace.classes'), 'icon' => 'view-boards', 'color' => 'indigo'],
        ['title' => 'Daftar Siswa', 'url' => route('teacher.workspace.students'), 'icon' => 'user-group', 'color' => 'purple'],
        ['title' => 'Kehadiran Siswa', 'url' => route('teacher.workspace.attendances'), 'icon' => 'clock', 'color' => 'amber'],
        ['title' => 'Izin/Sakit', 'url' => route('teacher.workspace.attendance-requests'), 'icon' => 'document-text', 'color' => 'rose'],
        ['title' => 'Penilaian', 'url' => route('teacher.workspace.scores'), 'icon' => 'chart-bar', 'color' => 'emerald'],
        ['title' => 'Tugas Harian', 'url' => route('teacher.workspace.assignments'), 'icon' => 'clipboard-list', 'color' => 'cyan'],
    ];
@endphp

<div class="space-y-6 lg:space-y-8 max-w-7xl mx-auto pb-10">

    <!-- 1. HEADER HERO -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-2xl shadow-lg p-5 md:p-8 text-white flex flex-col md:flex-row justify-between items-start md:items-center">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                {{ $greeting }}, {{ $teacherName }} {{ $greetingIcon }}
            </h1>
            <p class="text-slate-300 mt-1 font-medium">Teacher / Pengajar</p>
        </div>
        <div class="mt-4 md:mt-0 text-left md:text-right">
            <p class="text-sm text-slate-400 font-semibold">{{ $dateFormatted }}</p>
            <p class="text-xl font-bold text-blue-400 mt-1 clock-display">{{ $timeFormatted }} WIB</p>
        </div>
    </div>

    <!-- 2. KPI SECTION -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach($kpiList as $item)
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ $item['title'] }}</span>
                <div class="w-8 h-8 rounded-full bg-{{ $item['color'] }}-50 flex items-center justify-center shrink-0">
                    <x-dynamic-component :component="View::exists('components.icons.' . $item['icon']) ? 'icons.' . $item['icon'] : 'icons.bell'" class="w-4 h-4 text-{{ $item['color'] }}-500" />
                </div>
            </div>
            <div class="mt-auto">
                <span class="text-2xl font-black text-slate-800 line-clamp-1">{{ $item['value'] }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <!-- 3. MAIN CONTENT GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">

        <!-- LEFT COLUMN: Schedules & Classes -->
        <div class="lg:col-span-2 space-y-6 lg:space-y-8">

            <!-- Jadwal Mengajar Hari Ini -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center">
                        <x-dynamic-component component="icons.bell" class="w-5 h-5 text-blue-500 mr-2"/>
                        Jadwal Mengajar Hari Ini
                    </h2>
                    <a href="{{ route('teacher.workspace.schedule') }}" class="text-sm font-bold text-blue-600 hover:text-blue-700">Lihat Semua</a>
                </div>
                <div class="p-0">
                    @if(count($todayClasses ?? []) > 0)
                        <div class="divide-y divide-slate-100">
                            @foreach(array_slice($todayClasses, 0, 4) as $cls)
                            <div class="p-4 sm:px-6 hover:bg-slate-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-16 text-center shrink-0 bg-blue-50 rounded-lg py-2">
                                        <span class="block text-xs font-bold text-blue-700">{{ explode(' - ', $cls['time'])[0] ?? '' }}</span>
                                        <span class="block text-[10px] text-blue-400 font-semibold">{{ explode(' - ', $cls['time'])[1] ?? '' }}</span>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800">{{ $cls['subject'] ?? 'Materi' }}</h3>
                                        <p class="text-xs text-slate-500 mt-1 font-medium">{{ $cls['class'] ?? 'Kelas' }} • Ruang: {{ $cls['room'] ?? '-' }}</p>
                                    </div>
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Terjadwal</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8">
                            <x-empty-state icon="calendar" title="Belum ada jadwal mengajar hari ini." message="Tidak ada aktivitas kelas yang dijadwalkan pada hari ini." />
                        </div>
                    @endif
                </div>
            </div>

            <!-- Kelas Saya Summary -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center">
                        <x-dynamic-component component="icons.bell" class="w-5 h-5 text-indigo-500 mr-2"/>
                        Kelas Saya
                    </h2>
                    <a href="{{ route('teacher.workspace.classes') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">Lihat Semua</a>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-500 mb-4 font-medium">Anda memiliki akses penuh untuk melihat daftar siswa dan absensi pada kelas yang ditugaskan kepada Anda.</p>
                    <a href="{{ route('teacher.workspace.classes') }}" class="inline-flex items-center justify-center w-full sm:w-auto px-5 py-2.5 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700 transition">
                        Buka Kelas Saya
                    </a>
                </div>
            </div>

            <!-- Kehadiran Hari Ini -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center">
                        <x-dynamic-component component="icons.bell" class="w-5 h-5 text-amber-500 mr-2"/>
                        Kehadiran Hari Ini
                    </h2>
                </div>
                <div class="p-6">
                    @if(array_sum($attendanceStats ?? []) > 0)
                        <div class="grid grid-cols-4 gap-4 text-center">
                            <div class="bg-emerald-50 rounded-xl p-3">
                                <span class="block text-2xl font-black text-emerald-600">{{ $attendanceStats['hadir'] ?? 0 }}</span>
                                <span class="block text-[10px] font-bold text-emerald-800 uppercase mt-1">Hadir</span>
                            </div>
                            <div class="bg-blue-50 rounded-xl p-3">
                                <span class="block text-2xl font-black text-blue-600">{{ $attendanceStats['sakit'] ?? 0 }}</span>
                                <span class="block text-[10px] font-bold text-blue-800 uppercase mt-1">Sakit</span>
                            </div>
                            <div class="bg-indigo-50 rounded-xl p-3">
                                <span class="block text-2xl font-black text-indigo-600">{{ $attendanceStats['izin'] ?? 0 }}</span>
                                <span class="block text-[10px] font-bold text-indigo-800 uppercase mt-1">Izin</span>
                            </div>
                            <div class="bg-rose-50 rounded-xl p-3">
                                <span class="block text-2xl font-black text-rose-600">{{ $attendanceStats['alpa'] ?? 0 }}</span>
                                <span class="block text-[10px] font-bold text-rose-800 uppercase mt-1">Alpa</span>
                            </div>
                        </div>
                    @else
                        <x-empty-state icon="clock" title="Belum ada data kehadiran hari ini." message="Tidak ada absensi yang telah diisi atau diproses hari ini." />
                    @endif
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Actions & Activity -->
        <div class="space-y-6 lg:space-y-8">

            <!-- Menu Cepat -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center">
                        <x-dynamic-component component="icons.bell" class="w-5 h-5 text-amber-500 mr-2"/>
                        Menu Cepat
                    </h2>
                </div>
                <div class="p-4 grid grid-cols-2 gap-3">
                    @foreach($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="flex flex-col items-center justify-center p-4 rounded-xl border border-slate-100 hover:border-{{ $action['color'] }}-200 hover:bg-{{ $action['color'] }}-50 transition-all text-center group {{ $action['url'] === '#' ? 'opacity-50 cursor-not-allowed' : '' }}">
                            <div class="w-10 h-10 rounded-full bg-slate-50 group-hover:bg-white flex items-center justify-center mb-2 shadow-sm text-{{ $action['color'] }}-500">
                                <x-dynamic-component :component="View::exists('components.icons.' . $action['icon']) ? 'icons.' . $action['icon'] : 'icons.bell'" class="w-5 h-5" />
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-{{ $action['color'] }}-700">{{ $action['title'] }}</span>
                            @if($action['url'] === '#')
                                <span class="text-[9px] text-slate-400 mt-1 block">Segera tersedia</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Aktivitas Terbaru -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center">
                        <x-dynamic-component component="icons.bell" class="w-5 h-5 text-rose-500 mr-2"/>
                        Aktivitas Terbaru
                    </h2>
                </div>
                <div class="p-0">
                    @if(count($recentActivities ?? []) > 0)
                        <div class="divide-y divide-slate-100">
                            @foreach(array_slice($recentActivities, 0, 4) as $activity)
                            <div class="p-4 sm:px-6 hover:bg-slate-50 transition-colors">
                                <p class="text-xs font-bold text-slate-800">{{ $activity['title'] }}</p>
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $activity['description'] }}</p>
                                <p class="text-[10px] text-slate-400 font-semibold mt-2">{{ $activity['time'] }}</p>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6">
                            <x-empty-state icon="bell-snooze" title="Belum ada aktivitas terbaru" message="" />
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.querySelectorAll('.clock-display').forEach(el => {
            el.textContent = hours + ':' + minutes + ' WIB';
        });
    }
    setInterval(updateClock, 60000);
</script>
@endpush
@endsection
