@extends('layouts.app')

@section('header', 'Jadwal Kelas Saya')

@section('content')
<div class="w-full">
    <!-- Header Identitas -->
    <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 mb-6 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">🎓 JADWAL KELAS</span>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold text-[10px] border border-emerald-400/30">Aktif</span>
        </div>
        <div class="mt-3">
            <p class="text-xs text-sky-200 font-medium">Program & Kelas Belajar</p>
            <h3 class="text-xl font-black tracking-tight text-white mt-0.5">{{ $className }}</h3>
            <p class="text-[11px] text-sky-300 font-bold mt-0.5">{{ $batchName }}</p>
        </div>
    </div>

    @if(empty($schedules))
        <!-- EMPTY STATE -->
        <div class="bg-white rounded-3xl p-8 text-center shadow-sm border border-slate-200">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-4xl">📅</span>
            </div>
            <h3 class="text-lg font-extrabold text-slate-800">Belum ada jadwal kelas yang ditetapkan</h3>
            <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                Jadwal untuk kelas <span class="font-bold text-slate-700">{{ $className }}</span> belum tersedia. Silakan cek kembali nanti atau hubungi pengajar/administrator LPK Anda.
            </p>
        </div>
    @else
        <!-- MOBILE VIEW (CARD LIST) -->
        <div class="block md:hidden space-y-4">
            @foreach($schedules as $s)
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full {{ ($s['status'] === 'Completed') ? 'bg-emerald-500' : 'bg-sky-500' }}"></div>
                    
                    <div class="flex justify-between items-start mb-3 pl-2">
                        <div>
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">{{ $s['date'] ?? $s['day'] }}</span>
                            <div class="text-lg font-black text-slate-800 mt-0.5">{{ $s['time'] }}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border {{ ($s['status'] === 'Completed') ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-sky-50 text-sky-600 border-sky-200' }}">
                            {{ $s['status'] }}
                        </span>
                    </div>
                    
                    <div class="bg-slate-50 rounded-xl p-3 pl-4 border border-slate-100 mt-2">
                        <div class="mb-2">
                            <span class="text-[10px] text-slate-500 block">Materi</span>
                            <span class="text-sm font-bold text-indigo-700 block">{{ $s['subject'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-200/60">
                            <div>
                                <span class="text-[10px] text-slate-500 block">Pengajar</span>
                                <span class="text-[11px] font-bold text-slate-700 block truncate">{{ $s['teacher'] }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 block">Ruangan</span>
                                <span class="text-[11px] font-bold text-slate-700 block truncate">{{ $s['room'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- DESKTOP VIEW (TABLE) -->
        <div class="hidden md:block">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-extrabold">
                                <th class="p-4">Hari/Tanggal</th>
                                <th class="p-4">Waktu</th>
                                <th class="p-4">Materi</th>
                                <th class="p-4">Pengajar</th>
                                <th class="p-4">Ruangan</th>
                                <th class="p-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @foreach($schedules as $s)
                                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                    <td class="p-4 font-bold text-slate-800">{{ $s['date'] ?? $s['day'] }}</td>
                                    <td class="p-4 font-bold text-slate-600">{{ $s['time'] }}</td>
                                    <td class="p-4 font-extrabold text-indigo-600">{{ $s['subject'] }}</td>
                                    <td class="p-4 font-semibold text-slate-700">{{ $s['teacher'] }}</td>
                                    <td class="p-4 text-slate-600">{{ $s['room'] }}</td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border {{ ($s['status'] === 'Completed') ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-sky-50 text-sky-600 border-sky-200' }}">
                                            {{ $s['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
