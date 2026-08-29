@extends('layouts.app')
@section('header', 'Pengajuan Izin/Sakit')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Pengajuan Izin/Sakit" 
        description="Status pengajuan izin/sakit dari siswa di kelas yang Anda ajar (Read-Only)."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Pengajuan Izin/Sakit' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50">
            <h3 class="text-lg font-bold text-slate-900">Pengajuan Absensi</h3>
            <p class="text-sm text-slate-500 mt-1">Diproses oleh Akademik. Anda hanya dapat memantau status.</p>
        </div>
        
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($attendanceRequests as $req)
                <div class="p-4 bg-white space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-extrabold text-slate-900">{{ $req['Student_Name'] }}</p>
                            <p class="text-xs font-semibold text-blue-600">{{ $req['Request_Type'] ?? 'Unknown' }} - Kelas {{ $req['Class_Name'] ?? $req['Class_ID'] ?? '-' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ strtoupper($req['Status'] ?? '') === 'APPROVED' ? 'bg-emerald-100 text-emerald-700' : (strtoupper($req['Status'] ?? '') === 'REJECTED' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                            {{ $req['Status'] ?? 'PENDING' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-500">
                        <p><span class="font-semibold">Tgl:</span> {{ $req['Attendance_Date'] ?? '-' }}</p>
                        <p class="mt-1 italic">"{{ $req['Reason'] ?? 'Tidak ada alasan' }}"</p>
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <x-empty-state icon="document-text" title="Belum ada pengajuan absensi." message="" />
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Tanggal</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Kelas</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Siswa</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Tipe</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Alasan</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendanceRequests as $req)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 text-slate-600 font-medium">{{ $req['Attendance_Date'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-semibold text-slate-600">{{ $req['Class_Name'] ?? $req['Class_ID'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ $req['Student_Name'] }}</td>
                        <td class="px-6 py-4 font-semibold text-blue-600">{{ $req['Request_Type'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-slate-500 italic max-w-xs truncate">{{ $req['Reason'] ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ strtoupper($req['Status'] ?? '') === 'APPROVED' ? 'bg-emerald-100 text-emerald-700' : (strtoupper($req['Status'] ?? '') === 'REJECTED' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $req['Status'] ?? 'PENDING' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12">
                            <x-empty-state icon="document-text" title="Belum ada pengajuan absensi." message="" />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
