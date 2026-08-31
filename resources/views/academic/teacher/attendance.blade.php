@extends('layouts.app')
@section('header', 'Kehadiran Kelas')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Kehadiran - {{ $className }}"
        description="Roster, riwayat kehadiran siswa, dan pengajuan absen."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Kelas Saya' => route('teacher.workspace.classes'), 'Kehadiran' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50">
            <h3 class="text-lg font-bold text-slate-900">Pengajuan Absensi (Read-Only)</h3>
            <p class="text-sm text-slate-500 mt-1">Status pengajuan izin/sakit yang diproses oleh Akademik.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-white text-slate-500 border-b border-slate-100">
                    <tr>
                        <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Tanggal</th>
                        <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Siswa</th>
                        <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Tipe</th>
                        <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Alasan</th>
                        <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendanceRequests as $request)
                        @php
                            $requestStatus = strtoupper($request['Status'] ?? 'PENDING');
                            $requestClass = $requestStatus === 'APPROVED'
                                ? 'bg-emerald-100 text-emerald-700'
                                : ($requestStatus === 'REJECTED' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3 text-slate-600">{{ $request['Attendance_Date'] ?? '-' }}</td>
                            <td class="px-5 py-3 font-bold text-slate-900">{{ $request['Student_Name'] }}</td>
                            <td class="px-5 py-3 text-blue-600 font-semibold">{{ $request['Request_Type'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-slate-500 italic max-w-xs truncate">{{ $request['Reason'] ?? '-' }}</td>
                            <td class="px-5 py-3"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold {{ $requestClass }}">{{ $requestStatus }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500 text-xs">Tidak ada pengajuan absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="mb-4">
            <h3 class="text-lg font-bold text-slate-900">Riwayat Kehadiran (Read-Only)</h3>
            <p class="text-sm text-slate-500 mt-1">Roster dan data absensi kelas ini untuk tanggal {{ $dateFilter ?? date('Y-m-d') }}.</p>
        </div>
        @include('academic.teacher._attendance_groups', ['attendanceGroups' => $attendanceGroups ?? collect(), 'dateFilter' => $dateFilter ?? null])
    </div>
</div>
@endsection
