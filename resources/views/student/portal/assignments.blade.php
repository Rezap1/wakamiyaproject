@extends('layouts.app')

@section('header', 'Tugas Saya')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Daftar Tugas (Assignments)" 
        description="Semua tugas yang diberikan kepada Anda."
        :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Portal' => '#', 'Tugas' => '#']"
    />

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-4 border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 text-rose-700 p-4 rounded-xl mb-4 border border-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($assignments as $assignment)
            <x-card class="flex flex-col h-full hover:border-blue-300 transition-colors">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-bold text-slate-800">{{ $assignment['Title'] ?? 'Untitled' }}</h3>
                    <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-lg whitespace-nowrap">
                        DL: {{ $assignment['Deadline'] ?? '-' }}
                    </span>
                </div>
                <div class="text-sm text-slate-600 mb-6 flex-grow">
                    <p class="line-clamp-3">{{ $assignment['Description'] ?? 'Tidak ada deskripsi' }}</p>
                </div>
                <div class="pt-4 border-t border-slate-100">
                    <a href="{{ route('student.portal.assignments.show', $assignment['Assignment_ID']) }}" class="inline-flex w-full justify-center items-center px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl transition-colors text-sm">
                        Lihat & Kerjakan
                    </a>
                </div>
            </x-card>
        @empty
            <div class="col-span-full">
                <x-universal.empty-state title="Belum Ada Tugas" description="Saat ini belum ada tugas untuk kelas Anda." />
            </div>
        @endforelse
    </div>
</div>
@endsection
