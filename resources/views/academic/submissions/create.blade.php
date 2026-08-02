@extends('layouts.app')

@section('header', 'Input Pengumpulan Manual')

@section('content')
@php
    $assignmentOptions = [];
    if(isset($assignments)) {
        foreach($assignments as $a) {
            $assignmentOptions[$a['Assignment_ID'] ?? ''] = ($a['Title'] ?? 'Unknown');
        }
    }
    $studentOptions = [];
    if(isset($students)) {
        foreach($students as $s) {
            $studentOptions[$s['Student_ID'] ?? ''] = ($s['Full_Name'] ?? 'Unknown') . ' - ' . ($s['Class_Name'] ?? '');
        }
    }
@endphp

<div class="space-y-6">
    <x-page-header 
        title="Input Tugas Manual" 
        description="Bantu siswa mengunggah tugas mereka ke sistem secara manual."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Pengumpulan' => route('submissions.index'), 'Upload' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('submissions.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="px-6 md:px-12">
                <x-form-section title="Informasi Tugas" description="Tentukan Assignment ID, siswa, dan unggah file tugas.">
                    <div>
                        <x-universal.searchable-select 
                            name="Assignment_ID" 
                            label="Tugas" 
                            :options="$assignmentOptions" 
                            :required="true" 
                            value="{{ old('Assignment_ID', $assignmentId ?? '') }}" 
                        />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Student_ID" 
                            label="Siswa" 
                            :options="$studentOptions" 
                            :required="true" 
                            value="{{ old('Student_ID') }}" 
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">File Lampiran (Attachment)</label>
                        <input type="file" name="AttachmentFile" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-colors border border-slate-200 rounded-xl" required>
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="Comment" label="Catatan Tambahan" rows="3" placeholder="Pesan atau catatan terkait pengumpulan tugas ini (opsional)...">{{ old('Comment') }}</x-textarea>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('submissions.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Upload Tugas
                </x-button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Mengunggah...`;
        });
    });
</script>
@endsection



