<?php
$dir = 'resources/views/academic/attendances';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// 1. INDEX BLADE
$index = <<<'EOT'
@extends('layouts.app')

@section('header', 'Attendance Management')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Attendance Dashboard" 
        description="Monitor and manage employee and student attendance efficiently."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Attendance' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('attendances.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Mark Attendance
            </a>
        </x-slot:actions>
    </x-page-header>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Total Hadir</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">1,240</h3>
                <span class="text-[10px] font-bold text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded-full mt-2 inline-block">HARI INI</span>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Terlambat</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">45</h3>
                <span class="text-[10px] font-bold text-orange-500 bg-orange-50 px-2 py-0.5 rounded-full mt-2 inline-block">HARI INI</span>
            </div>
            <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Izin / Sakit</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">18</h3>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full mt-2 inline-block">HARI INI</span>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Alpha</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">7</h3>
                <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full mt-2 inline-block">HARI INI</span>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 flex items-center justify-between relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Persentase</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">96.2%</h3>
                <span class="text-[10px] font-bold text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded-full mt-2 inline-block">+0.4%</span>
            </div>
            <div class="w-16 h-16 absolute -right-3 -bottom-3 text-emerald-100 opacity-50 group-hover:scale-110 transition-transform">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" class="block w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-900" placeholder="Search by name, ID...">
                </div>
                <button class="px-4 py-2 text-sm font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-900 focus:ring-4 focus:ring-slate-100 transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Filter
                </button>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="font-semibold text-slate-500">Date:</span>
                <input type="date" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500" value="{{ date('Y-m-d') }}">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">ID / Type</th>
                        <th class="px-6 py-4">Employee / Student</th>
                        <th class="px-6 py-4">Time In</th>
                        <th class="px-6 py-4">Time Out</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendances ?? [] as $item)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-800 block">{{ $item['Attendance_ID'] ?? 'ATD-'.rand(100,999) }}</span>
                                <span class="text-[11px] text-slate-400 font-medium mt-1">{{ isset($item['Student_ID']) ? 'Student' : 'Employee' }}</span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-slate-200 mr-3 flex-shrink-0 flex items-center justify-center text-xs font-bold text-slate-500">
                                        {{ substr($item['Student_ID'] ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm">{{ $item['Student_ID'] ?? 'Unknown' }}</p>
                                        <p class="text-[11px] text-slate-500 font-medium">{{ $item['Schedule_ID'] ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-700">08:00 AM</td>
                            <td class="px-6 py-4 font-bold text-slate-700">05:00 PM</td>
                            <td class="px-6 py-4">
                                @php
                                    $status = strtolower($item['Status'] ?? 'present');
                                    $badge = match($status) {
                                        'present' => 'bg-emerald-100 text-emerald-700',
                                        'late' => 'bg-orange-100 text-orange-700',
                                        'sick' => 'bg-blue-100 text-blue-700',
                                        'leave' => 'bg-cyan-100 text-cyan-700',
                                        'permission' => 'bg-purple-100 text-purple-700',
                                        'absent' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <span class="{{ $badge }} px-2.5 py-1 text-[10px] font-extrabold rounded uppercase tracking-wide">{{ $item['Status'] ?? 'Present' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('attendances.show', $item['Attendance_ID'] ?? 1) }}" class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('attendances.edit', $item['Attendance_ID'] ?? 1) }}" class="p-2 text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <!-- Mock Data Since we lack DB -->
                        @for($i=1; $i<=5; $i++)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-800 block">ATD-00{{ $i }}</span>
                                <span class="text-[11px] text-slate-400 font-medium mt-1">Student</span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800">
                                <div class="flex items-center">
                                    <img src="https://ui-avatars.com/api/?name=User+{{ $i }}&background=e2e8f0&color=475569" class="w-8 h-8 rounded-full mr-3 border border-slate-200">
                                    <div>
                                        <p class="text-sm">John Doe {{ $i }}</p>
                                        <p class="text-[11px] text-slate-500 font-medium">Class A</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-700">07:5{{ rand(0,9) }} AM</td>
                            <td class="px-6 py-4 font-bold text-slate-700">05:1{{ rand(0,9) }} PM</td>
                            <td class="px-6 py-4">
                                @php
                                    $statuses = ['Present', 'Late', 'Sick', 'Leave', 'Permission', 'Absent'];
                                    $status = $statuses[array_rand($statuses)];
                                    $badge = match(strtolower($status)) {
                                        'present' => 'bg-emerald-100 text-emerald-700',
                                        'late' => 'bg-orange-100 text-orange-700',
                                        'sick' => 'bg-blue-100 text-blue-700',
                                        'leave' => 'bg-cyan-100 text-cyan-700',
                                        'permission' => 'bg-purple-100 text-purple-700',
                                        'absent' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <span class="{{ $badge }} px-2.5 py-1 text-[10px] font-extrabold rounded uppercase tracking-wide">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('attendances.show', $i) }}" class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('attendances.edit', $i) }}" class="p-2 text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endfor
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Showing 1 to 5 of 5 entries</span>
            <div class="flex items-center gap-1">
                <button class="px-3 py-1 text-sm font-semibold text-slate-400 bg-white border border-slate-200 rounded-lg cursor-not-allowed">Prev</button>
                <button class="px-3 py-1 text-sm font-bold text-white bg-blue-600 rounded-lg shadow-sm shadow-blue-200">1</button>
                <button class="px-3 py-1 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">2</button>
                <button class="px-3 py-1 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/index.blade.php", $index);

// 2. CREATE BLADE
$create = <<<'EOT'
@extends('layouts.app')

@section('header', 'Mark Attendance')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Mark Attendance" 
        description="Record attendance for students or employees manually."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Attendance' => route('attendances.index'), 'Create' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('attendances.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-form-section title="Attendance Information" description="Please fill in the required attendance data carefully.">
                <form action="{{ route('attendances.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">User Type</label>
                            <select name="type" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="student">Student</option>
                                <option value="employee">Employee</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Target User</label>
                            <input type="text" name="Student_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Enter ID or Name" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Date</label>
                            <input type="date" name="Attendance_Date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status</label>
                            <select name="Status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="Present">Present (Hadir)</option>
                                <option value="Late">Late (Terlambat)</option>
                                <option value="Sick">Sick (Sakit)</option>
                                <option value="Leave">Leave (Cuti)</option>
                                <option value="Permission">Permission (Izin)</option>
                                <option value="Absent">Absent (Alpha)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Time In</label>
                            <input type="time" name="Time_In" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Time Out</label>
                            <input type="time" name="Time_Out" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Notes</label>
                        <textarea name="Notes" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Additional notes or reasons (optional)..."></textarea>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                            Save Attendance
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
        
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
                <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Guidelines
                </h3>
                <ul class="text-xs font-medium text-blue-700 space-y-3">
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        For <strong>Present</strong>, Time In and Time Out are typically recorded.
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        For <strong>Sick</strong> or <strong>Leave</strong>, you can leave the time fields blank but notes are highly recommended.
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        Check the schedule to ensure the target user belongs to a valid session today.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/create.blade.php", $create);

// 3. EDIT BLADE
$edit = <<<'EOT'
@extends('layouts.app')

@section('header', 'Edit Attendance')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Edit Attendance" 
        description="Update an existing attendance record."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Attendance' => route('attendances.index'), 'Edit' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('attendances.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-form-section title="Attendance Data" description="Modify the details of this attendance log.">
                <form action="{{ route('attendances.update', $attendance['Attendance_ID'] ?? 1) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Target User</label>
                            <input type="text" name="Student_ID" class="w-full bg-slate-100 border border-slate-200 text-slate-500 text-sm rounded-xl block p-3 font-semibold" value="{{ $attendance['Student_ID'] ?? 'John Doe' }}" readonly>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Date</label>
                            <input type="date" name="Attendance_Date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ $attendance['Attendance_Date'] ?? date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status</label>
                            <select name="Status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="Present" {{ ($attendance['Status'] ?? '') == 'Present' ? 'selected' : '' }}>Present</option>
                                <option value="Late" {{ ($attendance['Status'] ?? '') == 'Late' ? 'selected' : '' }}>Late</option>
                                <option value="Sick" {{ ($attendance['Status'] ?? '') == 'Sick' ? 'selected' : '' }}>Sick</option>
                                <option value="Leave" {{ ($attendance['Status'] ?? '') == 'Leave' ? 'selected' : '' }}>Leave</option>
                                <option value="Absent" {{ ($attendance['Status'] ?? '') == 'Absent' ? 'selected' : '' }}>Absent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Time In</label>
                            <input type="time" name="Time_In" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" value="08:00">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Notes</label>
                        <textarea name="Notes" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Notes...">{{ $attendance['Notes'] ?? '' }}</textarea>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                            Update Attendance
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/edit.blade.php", $edit);

// 4. SHOW BLADE
$show = <<<'EOT'
@extends('layouts.app')

@section('header', 'Attendance Detail')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Attendance Detail" 
        description="View comprehensive information about this attendance record."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Attendance' => route('attendances.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('attendances.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm mr-2">
                Back
            </a>
            <a href="{{ route('attendances.edit', $attendance['Attendance_ID'] ?? 1) }}" class="px-4 py-2 text-sm font-bold text-white bg-amber-500 rounded-xl hover:bg-amber-600 focus:ring-4 focus:ring-amber-300 transition-colors shadow-sm shadow-amber-200">
                Edit Record
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $status = strtolower($attendance['Status'] ?? 'present');
        $badge = match($status) {
            'present' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
            'late' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
            'absent' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
            default => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: User Info -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden text-center p-8">
                <div class="w-24 h-24 rounded-full bg-slate-100 border-4 border-white shadow-md mx-auto mb-4 flex items-center justify-center">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($attendance['Student_ID'] ?? 'User') }}&background=e2e8f0&color=475569&size=96&rounded=true">
                </div>
                <h3 class="text-xl font-extrabold text-slate-800">{{ $attendance['Student_ID'] ?? 'Unknown User' }}</h3>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1 mb-4">Student</p>
                
                <span class="{{ $badge['bg'] }} {{ $badge['text'] }} inline-flex items-center px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $badge['icon'] !!}</svg>
                    {{ $attendance['Status'] ?? 'Present' }}
                </span>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">Academic Details</h4>
                <ul class="space-y-4">
                    <li>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Department</p>
                        <p class="text-sm font-semibold text-slate-800">Software Engineering</p>
                    </li>
                    <li>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Program</p>
                        <p class="text-sm font-semibold text-slate-800">Full Stack Development</p>
                    </li>
                    <li>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Batch / Class</p>
                        <p class="text-sm font-semibold text-slate-800">Batch 5 - Class A</p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right: Timeline & Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Record Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Record ID</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $attendance['Attendance_ID'] ?? 'ATD-10294' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Date</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $attendance['Attendance_Date'] ?? date('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Schedule ID</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $attendance['Schedule_ID'] ?? 'SCH-001' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Duration</p>
                            <p class="text-sm font-bold text-blue-600 mt-1">9 hrs 0 mins</p>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="mt-8">
                        <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-6">Activity Timeline</h4>
                        <div class="relative border-l-2 border-slate-100 ml-3 space-y-8">
                            
                            <div class="relative">
                                <span class="absolute -left-[25px] bg-white border-4 border-emerald-100 text-emerald-500 w-12 h-12 rounded-full flex items-center justify-center font-bold">
                                    IN
                                </span>
                                <div class="ml-10">
                                    <h5 class="text-sm font-bold text-slate-800">Check In</h5>
                                    <p class="text-xs font-semibold text-slate-500 mt-0.5">08:00 AM <span class="mx-2">•</span> 192.168.1.1</p>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <span class="absolute -left-[25px] bg-white border-4 border-orange-100 text-orange-500 w-12 h-12 rounded-full flex items-center justify-center font-bold">
                                    OUT
                                </span>
                                <div class="ml-10">
                                    <h5 class="text-sm font-bold text-slate-800">Check Out</h5>
                                    <p class="text-xs font-semibold text-slate-500 mt-0.5">05:00 PM <span class="mx-2">•</span> 192.168.1.1</p>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <div class="mt-10 pt-6 border-t border-slate-100">
                        <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Notes</h4>
                        <p class="text-sm font-medium text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-100">{{ $attendance['Notes'] ?? 'No additional notes provided.' }}</p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/show.blade.php", $show);

echo "Created 4 views in academic/attendances.\n";
?>
