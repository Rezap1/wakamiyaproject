@extends('layouts.app')
@section('header', 'Jadwal Mengajar')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Jadwal Mengajar" 
        description="Jadwal mengajar kelas Anda."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Jadwal' => '#']"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($schedules as $schedule)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $schedule['Day'] ?? $schedule['Day_Of_Week'] ?? 'N/A' }}
                        </span>
                        <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $schedule['Class_Name'] ?? $schedule['Class_ID'] }}</h3>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-slate-700 block">{{ $schedule['Start_Time'] ?? '--:--' }} - {{ $schedule['End_Time'] ?? '--:--' }}</span>
                    </div>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-start text-sm">
                        <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        <div>
                            <span class="block text-slate-500 text-xs font-medium">Materi</span>
                            <span class="font-semibold text-slate-800">{{ $schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="flex items-start text-sm">
                        <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <div>
                            <span class="block text-slate-500 text-xs font-medium">Ruangan</span>
                            <span class="font-semibold text-slate-800">{{ $schedule['Room'] ?? 'TBA' }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                    <a href="{{ route('teacher.workspace.classes.students', $schedule['Class_ID']) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Daftar Siswa</a>
                    <a href="{{ route('teacher.workspace.classes.attendance', $schedule['Class_ID']) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Kehadiran</a>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-empty-state 
                    icon="calendar" 
                    title="Belum ada jadwal mengajar" 
                    message="Saat ini Anda belum ditugaskan untuk mengajar pada jadwal manapun." 
                />
            </div>
        @endforelse
    </div>
</div>
@endsection
