@extends('layouts.app')
@section('header', 'Academic Progress')
@section('content')
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-sm text-gray-500 font-bold">Current GPA</h4>
        <p class="text-2xl font-black text-blue-700">{{ $progress['gpa'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-sm text-gray-500 font-bold">Attendance</h4>
        <p class="text-2xl font-black text-green-700">{{ $progress['attendance'] }}%</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-sm text-gray-500 font-bold">Credits Completed</h4>
        <p class="text-2xl font-black text-purple-700">{{ $progress['completed_credits'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-orange-500">
        <h4 class="text-sm text-gray-500 font-bold">Academic Status</h4>
        <p class="text-xl font-black text-orange-700 mt-1">{{ $progress['status'] }}</p>
    </div>
</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Riwayat Nilai</h3>
            <a href="{{ route('scores.export-csv') }}" class="text-xs px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors font-bold">CSV</a>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Assessment</th>
                        <th class="px-4 py-3 font-semibold">Score</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($myScores as $score)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $score['Assessment_ID'] ?? $score['Assignment_ID'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @php $scoreVal = $score['Score'] ?? $score['Score_Value'] ?? 0; @endphp
                            <span class="font-bold {{ $scoreVal >= 60 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $scoreVal }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if(($score['Status'] ?? '') === 'PASS')
                                <span class="px-2 py-1 text-[10px] font-bold bg-emerald-100 text-emerald-700 rounded-full">LULUS</span>
                            @else
                                <span class="px-2 py-1 text-[10px] font-bold bg-rose-100 text-rose-700 rounded-full">MENGULANG</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada data nilai.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Riwayat Kehadiran</h3>
            <a href="{{ route('attendances.export-csv') }}" class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors font-bold">CSV</a>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Sesi</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($myAttendances as $att)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-slate-600">{{ $att['Attendance_Date'] ?? $att['Date'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $att['Schedule_ID'] ?? $att['Session'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $status = $att['Status'] ?? '';
                                $badgeClass = match($status) {
                                    'Present' => 'bg-emerald-100 text-emerald-700',
                                    'Late' => 'bg-orange-100 text-orange-700',
                                    'Absent', 'Alpha' => 'bg-rose-100 text-rose-700',
                                    'Excused' => 'bg-blue-100 text-blue-700',
                                    'Sick' => 'bg-amber-100 text-amber-700',
                                    default => 'bg-slate-100 text-slate-700'
                                };
                            @endphp
                            <span class="px-2 py-1 text-[10px] font-bold rounded-full {{ $badgeClass }}">
                                {{ strtoupper($status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada riwayat kehadiran.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection



