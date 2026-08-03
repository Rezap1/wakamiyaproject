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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card class="prose max-w-none prose-slate">
                <h3 class="text-xl font-bold mb-4">Instruksi Tugas</h3>
                <div class="whitespace-pre-line text-slate-700">{{ $assignment['Description'] ?? 'Tidak ada instruksi.' }}</div>
            </x-card>
        </div>

        <div class="lg:col-span-1">
            <x-card title="Pengumpulan Tugas">
                @if($mySubmission)
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                        <div class="flex items-center text-blue-800 font-bold mb-2">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Tugas Telah Dikumpulkan
                        </div>
                        <p class="text-sm text-blue-700">Pada: {{ $mySubmission['Submission_Date'] ?? '-' }}</p>
                    </div>
                    <div class="text-sm">
                        <p class="font-bold text-slate-700 mb-1">Nilai:</p>
                        <p class="mb-3">{{ $mySubmission['Score'] ?? 'Belum dinilai' }}</p>
                        
                        <p class="font-bold text-slate-700 mb-1">Feedback:</p>
                        <p>{{ $mySubmission['Feedback'] ?? '-' }}</p>
                    </div>
                @else
                    <form action="{{ route('student.portal.assignments.upload', $assignment['Assignment_ID']) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">File Jawaban (Max 10MB)</label>
                            <input type="file" name="file" required class="block w-full text-sm text-slate-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100 border border-slate-200 rounded-lg p-2"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Komentar/Catatan (Opsional)</label>
                            <textarea name="comments" rows="3" class="w-full border-slate-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition-all"></textarea>
                        </div>
                        <x-button type="submit" variant="primary" class="w-full justify-center">Unggah Tugas</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</div>
@endsection
