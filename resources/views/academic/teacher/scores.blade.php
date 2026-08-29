@extends('layouts.app')
@section('header', 'Penilaian')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Penilaian" 
        description="Manajemen nilai siswa pada kelas yang Anda ajar."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Penilaian' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Daftar Penilaian</h3>
                <p class="text-sm text-slate-500 mt-1">Data penilaian dari siswa kelas Anda.</p>
            </div>
            <div>
                <a href="{{ route('teacher.workspace.scores.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Penilaian
                </a>
            </div>
        </div>

        <div class="md:hidden divide-y divide-slate-100">
            @forelse($scores as $score)
                <div class="p-4 bg-white space-y-3" x-data="{ open: false }">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-extrabold text-slate-900">{{ $score['Student_Name'] ?? 'Unknown Student' }}</p>
                            <p class="text-xs font-semibold text-blue-600 mt-0.5">{{ $score['Assessment_Category'] ?? 'Legacy Score' }} &bull; {{ date('d M Y', strtotime($score['Created_At'] ?? now())) }}</p>
                        </div>
                        @if(empty($score['Evaluation_Details']))
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-lg font-bold bg-slate-100 text-slate-700">
                                {{ $score['Score'] ?? '0' }}
                            </span>
                        @else
                            <button @click="open = !open" class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg">
                                Lihat Detail
                            </button>
                        @endif
                    </div>
                    @if(!empty($score['Evaluation_Details']))
                    <div x-show="open" class="pt-3 border-t border-slate-100 text-sm space-y-2">
                        @php
                            $details = json_decode($score['Evaluation_Details'], true);
                            $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                            $aspectMap = ['speaking' => 'Bicara', 'writing' => 'Menulis', 'listening' => 'Mendengar', 'reading' => 'Membaca', 'ethics' => 'Sikap/Etika', 'motivation' => 'Motivasi', 'attendance' => 'Kehadiran'];
                        @endphp
                        @foreach($aspectMap as $key => $label)
                            @if(isset($details[$key]))
                                <div class="flex justify-between">
                                    <span class="text-slate-500">{{ $label }}</span>
                                    <span class="font-semibold text-slate-800">{{ $details[$key] }} - {{ $labels[$details[$key]] ?? '' }}</span>
                                </div>
                            @endif
                        @endforeach
                        @if(!empty($details['notes']))
                            <div class="mt-2 pt-2 border-t border-slate-50">
                                <p class="text-xs text-slate-400">Catatan:</p>
                                <p class="text-slate-700 italic">{{ $details['notes'] }}</p>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            @empty
                <div class="p-8">
                    <x-empty-state icon="chart-bar" title="Belum ada data penilaian." message="" />
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Penilaian</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Siswa</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($scores as $score)
                    <tr class="hover:bg-slate-50 transition-colors" x-data="{ open: false }">
                        <td class="px-6 py-4 font-medium text-slate-600">
                            {{ $score['Assessment_Category'] ?? 'Legacy Score' }}
                            <div class="text-xs text-slate-400 mt-1">{{ date('d M Y', strtotime($score['Created_At'] ?? now())) }}</div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ $score['Student_Name'] ?? 'Unknown Student' }}</td>
                        <td class="px-6 py-4">
                            @if(empty($score['Evaluation_Details']))
                                <span class="font-semibold text-slate-900 text-lg">{{ $score['Score'] ?? '0' }}</span>
                            @else
                                <button @click="open = !open" class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                    <span x-show="!open">Lihat Detail</span>
                                    <span x-show="open">Tutup Detail</span>
                                </button>
                                
                                <div x-show="open" class="mt-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm text-sm min-w-[250px] absolute z-10" @click.away="open = false">
                                    @php
                                        $details = json_decode($score['Evaluation_Details'], true);
                                        $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                                        $aspectMap = ['speaking' => 'Bicara', 'writing' => 'Menulis', 'listening' => 'Mendengar', 'reading' => 'Membaca', 'ethics' => 'Sikap/Etika', 'motivation' => 'Motivasi Belajar', 'attendance' => 'Kehadiran'];
                                    @endphp
                                    <div class="space-y-2">
                                    @foreach($aspectMap as $key => $label)
                                        @if(isset($details[$key]))
                                            <div class="flex justify-between gap-4">
                                                <span class="text-slate-500">{{ $label }}</span>
                                                <span class="font-semibold text-slate-800">{{ $details[$key] }} - {{ $labels[$details[$key]] ?? '' }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                    </div>
                                    @if(!empty($details['notes']))
                                        <div class="mt-3 pt-3 border-t border-slate-100">
                                            <p class="text-xs font-semibold text-slate-400 mb-1">Catatan:</p>
                                            <p class="text-slate-700 italic whitespace-normal">{{ $details['notes'] }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12">
                            <x-empty-state icon="chart-bar" title="Belum ada data penilaian." message="" />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
