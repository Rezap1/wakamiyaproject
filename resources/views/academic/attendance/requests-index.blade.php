@extends('layouts.app')

@section('header', 'Attendance Requests Review Center')

@section('content')
<div class="w-full">
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-4">
            <h2 class="text-2xl font-black text-slate-800">Review Center</h2>
            
            <form action="{{ route('academic.attendance.requests.index') }}" method="GET" class="mt-4 md:mt-0 flex gap-2">
                <select name="status" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-semibold focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none" onchange="this.form.submit()">
                    <option value="PENDING" {{ $statusFilter == 'PENDING' ? 'selected' : '' }}>Pending</option>
                    <option value="APPROVED" {{ $statusFilter == 'APPROVED' ? 'selected' : '' }}>Approved</option>
                    <option value="REJECTED" {{ $statusFilter == 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                    <option value="ALL" {{ $statusFilter == 'ALL' ? 'selected' : '' }}>All</option>
                </select>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl p-4 mb-6 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 mb-6 text-sm font-bold">
            {{ session('error') }}
        </div>
    @endif

    @if($paginated->isEmpty())
        <!-- EMPTY STATE -->
        <div class="bg-white rounded-3xl p-12 text-center shadow-sm border border-slate-200">
            <div class="text-6xl mb-4">✅</div>
            <h3 class="text-xl font-black text-slate-800">Tidak ada pengajuan ({{ $statusFilter }})</h3>
            <p class="text-slate-500 mt-2">Semua request ketidakhadiran telah direview atau tidak ada request baru.</p>
        </div>
    @else
        <!-- MOBILE VIEW (CARD LIST) -->
        <div class="block md:hidden space-y-4">
            @foreach($paginated as $req)
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-black text-indigo-700 uppercase">{{ $req['Request_Type'] }}</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg border 
                            {{ $req['Status'] === 'PENDING' ? 'bg-amber-50 text-amber-600 border-amber-200' : '' }}
                            {{ $req['Status'] === 'APPROVED' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : '' }}
                            {{ $req['Status'] === 'REJECTED' ? 'bg-rose-50 text-rose-600 border-rose-200' : '' }}">
                            {{ $req['Status'] }}
                        </span>
                    </div>
                    <h4 class="font-bold text-slate-800">{{ $req['Student_Name'] }}</h4>
                    <p class="text-xs text-slate-500 mb-2">{{ $req['Class_Name'] }} | {{ $req['Attendance_Date'] }}</p>
                    <p class="text-[10px] font-semibold text-indigo-600">{{ $req['Attendance_Type'] ?? 'SCHEDULE' }} &middot; {{ $req['Target_Display'] ?? ($req['Schedule_ID'] ?? 'Tidak tersedia') }}</p>
                    
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <a href="{{ route('academic.attendance.requests.show', $req['Request_ID']) }}" class="block text-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-2 rounded-xl transition-colors">
                            Review
                        </a>
                        @if(!empty($req['Evidence_URL']))
                            <a href="{{ route('academic.attendance.requests.evidence', $req['Request_ID']) }}" class="block text-center bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold py-2 rounded-xl transition-colors border border-blue-200">
                                Download
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- DESKTOP VIEW (TABLE) -->
        <div class="hidden md:block">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-extrabold">
                            <th class="p-4">Tgl. Presensi</th>
                            <th class="p-4">Siswa</th>
                            <th class="p-4">Kelas</th>
                            <th class="p-4">Tipe</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($paginated as $req)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td class="p-4 font-bold text-slate-700">{{ $req['Attendance_Date'] }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800">{{ $req['Student_Name'] }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $req['Student_ID'] }}</div>
                                </td>
                                <td class="p-4 font-semibold text-slate-600">{{ $req['Class_Name'] }}</td>
                                <td class="p-4 font-extrabold text-indigo-600">
                                    <div>{{ $req['Request_Type'] }}</div>
                                    <div class="text-[10px] font-semibold text-slate-500">{{ $req['Attendance_Type'] ?? 'SCHEDULE' }} &middot; {{ $req['Target_Display'] ?? ($req['Schedule_ID'] ?? 'Tidak tersedia') }}</div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border 
                                        {{ $req['Status'] === 'PENDING' ? 'bg-amber-50 text-amber-600 border-amber-200' : '' }}
                                        {{ $req['Status'] === 'APPROVED' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : '' }}
                                        {{ $req['Status'] === 'REJECTED' ? 'bg-rose-50 text-rose-600 border-rose-200' : '' }}">
                                        {{ $req['Status'] }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    @if(!empty($req['Evidence_URL']))
                                        <a href="{{ route('academic.attendance.requests.evidence', $req['Request_ID']) }}" class="inline-block bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold text-xs px-3 py-1.5 rounded-lg border border-blue-200 transition-colors mr-1">
                                            Download
                                        </a>
                                    @endif
                                    <a href="{{ route('academic.attendance.requests.show', $req['Request_ID']) }}" class="inline-block bg-sky-50 text-sky-700 hover:bg-sky-100 font-bold text-xs px-3 py-1.5 rounded-lg border border-sky-200 transition-colors">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $paginated->links() }}
        </div>
    @endif
</div>
@endsection
