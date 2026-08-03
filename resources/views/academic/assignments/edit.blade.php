@extends('layouts.app')

@section('header', 'Form Tugas')

@section('content')
@php
    $classOptions = [];
    if(isset($classes)) {
        foreach($classes as $c) {
            $classOptions[$c['Class_ID'] ?? ''] = ($c['Class_Name'] ?? 'Unknown') . ' (' . ($c['Program_Name'] ?? '') . ')';
        }
    }
    $teacherOptions = [];
    if(isset($teachers)) {
        foreach($teachers as $t) {
            $teacherOptions[$t['Teacher_ID'] ?? ''] = ($t['Full_Name'] ?? 'Unknown') . ' (' . ($t['Specialization'] ?? '') . ')';
        }
    }
@endphp

<div class="space-y-6">
    <x-page-header 
        title="Formulir Pembaruan Tugas" 
        description="Mengubah data tugas: {{ $assignment['Assignment_ID'] ?? '' }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Tugas' => route('assignments.index'), 'Edit' => '#']"
    >
        <x-slot:actions>
            @php
                $status = $assignment['Status'] ?? 'Draft';
                $statusColor = match($status) {
                    'Published' => 'green',
                    'Draft' => 'gray',
                    'Closed' => 'red',
                    default => 'blue'
                };
            @endphp
            <x-badge color="{{ $statusColor }}" type="solid" class="uppercase">
                Status Saat Ini: {{ $status }}
            </x-badge>
        </x-slot:actions>
    </x-page-header>

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('assignments.update', $assignment['Assignment_ID']) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="px-6 md:px-12">
                <x-form-section title="Informasi Tugas" description="Detail penugasan, kelas yang dituju, dan pengaturan waktu.">
                    <div class="sm:col-span-2">
                        <x-input name="Title" label="Judul Tugas" required value="{{ old('Title', $assignment['Title'] ?? '') }}" />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Class_ID" 
                            label="Kelas" 
                            :options="$classOptions" 
                            :required="true" 
                            value="{{ old('Class_ID', $assignment['Class_ID'] ?? '') }}" 
                        />
                    </div>

                    @if(isset($currentTeacherId))
                        <input type="hidden" name="Teacher_ID" value="{{ $currentTeacherId }}">
                        <div>
                            <x-input name="_teacher_display" label="Pengajar" value="{{ $teacherOptions[$currentTeacherId] ?? 'Nama Pengajar' }}" disabled readonly />
                        </div>
                    @else
                        <div>
                            <x-universal.searchable-select 
                                name="Teacher_ID" 
                                label="Pengajar" 
                                :options="$teacherOptions" 
                                :required="true" 
                                value="{{ old('Teacher_ID', $assignment['Teacher_ID'] ?? '') }}" 
                            />
                        </div>
                    @endif

                    <div>
                        @php
                            $deadline = old('Deadline', $assignment['Deadline'] ?? '');
                            if($deadline) {
                                // Format to YYYY-MM-DDThh:mm for datetime-local
                                $deadline = date('Y-m-d\TH:i', strtotime($deadline));
                            }
                        @endphp
                        <x-input type="datetime-local" name="Deadline" label="Tenggat Waktu (Deadline)" required value="{{ $deadline }}" />
                    </div>

                    <div>
                        <x-select name="Status" label="Status Publikasi" required>
                            <option value="Draft" {{ old('Status', $assignment['Status'] ?? '') == 'Draft' ? 'selected' : '' }}>Draft (Belum Dipublikasikan)</option>
                            <option value="Published" {{ old('Status', $assignment['Status'] ?? '') == 'Published' ? 'selected' : '' }}>Published (Terpublikasi)</option>
                            <option value="Closed" {{ old('Status', $assignment['Status'] ?? '') == 'Closed' ? 'selected' : '' }}>Closed (Ditutup)</option>
                        </x-select>
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="Description" label="Deskripsi / Instruksi Tugas" rows="4">{{ old('Description', $assignment['Description'] ?? '') }}</x-textarea>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('assignments.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Perbarui Tugas
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
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
        });
    });
</script>
@endsection



