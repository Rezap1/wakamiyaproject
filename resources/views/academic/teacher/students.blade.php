@extends('layouts.app')
@section('header', 'Daftar Siswa')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Daftar Siswa {{ isset($className) ? '- ' . $className : 'Anda' }}" 
        description="Siswa yang terdaftar pada kelas yang Anda ajar."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Daftar Siswa' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 md:hidden">
            <h3 class="font-bold text-slate-800">Daftar Siswa ({{ count($students) }})</h3>
        </div>
        
        <!-- Mobile View: Cards -->
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($students as $s)
                <div class="p-4 bg-white space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-extrabold text-slate-900">{{ $s['Full_Name'] ?? $s['Username'] ?? 'Data siswa tidak ditemukan' }}</p>
                            <p class="text-xs text-slate-500 font-medium">{{ $s['Student_Number'] ?? $s['NIS'] ?? '-' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ strtoupper($s['Is_Active'] ?? '') === 'TRUE' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ strtoupper($s['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif' }}
                        </span>
                    </div>
                    <div class="flex gap-4 text-xs">
                        <div>
                            <span class="block text-slate-400">Gender</span>
                            <span class="font-semibold text-slate-700">{{ $s['Gender'] ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-slate-400">Batch</span>
                            <span class="font-semibold text-slate-700">{{ $s['Batch_Name'] ?? 'Batch tidak ditemukan' }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <x-empty-state 
                        icon="user-group" 
                        title="Belum ada siswa pada kelas yang Anda ajar." 
                        message="" 
                    />
                </div>
            @endforelse
        </div>

        <!-- Desktop View: Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">No. Siswa</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Nama Siswa</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Gender</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Kelas</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $s)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-slate-600">{{ $s['Student_Number'] ?? $s['NIS'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ $s['Full_Name'] ?? $s['Username'] ?? 'Data siswa tidak ditemukan' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $s['Gender'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-semibold text-slate-600">{{ $s['Class_Name'] ?? 'Kelas tidak ditemukan' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ strtoupper($s['Is_Active'] ?? '') === 'TRUE' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ strtoupper($s['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12">
                            <x-empty-state 
                                icon="user-group" 
                                title="Belum ada siswa pada kelas yang Anda ajar." 
                                message="" 
                            />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
