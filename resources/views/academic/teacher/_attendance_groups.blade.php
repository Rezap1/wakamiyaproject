@php
    $attendanceGroups = $attendanceGroups ?? collect();
@endphp

<div class="space-y-5">
    @forelse($attendanceGroups as $group)
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100 bg-slate-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <p class="text-xs font-black text-indigo-500 uppercase tracking-wider">{{ $group['Class_ID'] }}</p>
                        <h3 class="text-lg font-bold text-slate-900">{{ $group['Class_Name'] }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ $group['Date_Display'] ?: 'Semua tanggal' }} &middot; {{ $group['Total'] }} siswa</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[10px] font-bold">
                        <span class="px-2 py-1 rounded bg-emerald-50 text-emerald-700">Hadir {{ $group['Hadir'] }}</span>
                        <span class="px-2 py-1 rounded bg-amber-50 text-amber-700">Terlambat {{ $group['Terlambat'] }}</span>
                        <span class="px-2 py-1 rounded bg-blue-50 text-blue-700">Izin {{ $group['Izin'] }}</span>
                        <span class="px-2 py-1 rounded bg-yellow-50 text-yellow-700">Sakit {{ $group['Sakit'] }}</span>
                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-700">Belum {{ $group['Belum_Absen'] }}</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-white text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Siswa</th>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Tanggal</th>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Check In</th>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Check Out</th>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Status</th>
                            <th class="px-5 py-3 font-bold uppercase text-[10px] tracking-wider">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($group['Students'] as $student)
                            @php
                                $attendance = $student['Attendance'] ?? null;
                                $status = $student['Display_Status'] ?? 'Belum Absen';
                                $statusKey = $student['Status_Key'] ?? 'NOT_ATTENDED';
                                $statusClass = match($statusKey) {
                                    'PRESENT' => 'bg-emerald-100 text-emerald-700',
                                    'LATE' => 'bg-amber-100 text-amber-700',
                                    'SICK' => 'bg-yellow-100 text-yellow-700',
                                    'PERMITTED' => 'bg-blue-100 text-blue-700',
                                    'ABSENT' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3">
                                    <p class="font-bold text-slate-900">{{ $student['Student_Name'] }}</p>
                                    <p class="text-[10px] text-slate-500 font-mono">{{ $student['Student_ID'] }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $attendance['Attendance_Date'] ?? ($dateFilter ?? '-') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $student['Check_In_Time'] ?? '--:--' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $student['Check_Out_Time'] ?? '--:--' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $statusClass }}">{{ $status }}</span>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-500">{{ $attendance['Attendance_Type'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">Tidak ada siswa pada kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <x-empty-state icon="clock" title="Belum ada data kelas." message="Tidak ada kelas yang dapat ditampilkan pada scope pengajar Anda." />
        </div>
    @endforelse
</div>
