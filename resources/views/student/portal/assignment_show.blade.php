@extends('layouts.app')

@section('header', 'Detail Tugas')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="{{ $assignment['Title'] ?? 'Detail Tugas' }}" 
        description="Batas Waktu: {{ $assignment['Deadline'] ?? '-' }}"
        :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Tugas Saya' => route('student.portal.assignments'), 'Detail' => '#']"
    />

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 text-rose-700 p-4 rounded-xl border border-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6">
        <div class="space-y-6">
            <x-card class="prose max-w-none prose-slate">
                <h3 class="text-xl font-bold mb-4">Instruksi Tugas</h3>
                <div class="whitespace-pre-line text-slate-700">{{ $assignment['Description'] ?? 'Tidak ada instruksi.' }}</div>
            </x-card>
        </div>
    </div>
</div>
@endsection
