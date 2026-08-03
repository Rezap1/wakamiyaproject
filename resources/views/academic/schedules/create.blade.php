@extends('layouts.app')

@section('header', 'Tambah Jadwal')

@section('content')
@php
    $classOptions = [];
    if(isset($classes)) {
        foreach($classes as $c) {
            $classOptions[$c['Class_ID'] ?? ''] = ($c['Class_Name'] ?? 'Unknown') . ' (' . ($c['Program_Name'] ?? '') . ')';
        }
    }
    
    $subjectOptions = [];
    if(isset($subjects)) {
        foreach($subjects as $s) {
            $subjectOptions[$s['Subject_ID'] ?? ''] = ($s['Subject_Name'] ?? 'Unknown') . ' (' . ($s['Subject_Code'] ?? '') . ')';
        }
    }
    
    $teacherOptions = [];
    if(isset($teachers)) {
        foreach($teachers as $t) {
            $teacherOptions[$t['Teacher_ID'] ?? ''] = ($t['Full_Name'] ?? 'Unknown') . ' (' . ($t['Specialization'] ?? '') . ')';
        }
    }
    
    $ayOptions = [];
    if(isset($academicYears)) {
        foreach($academicYears as $ay) {
            $ayOptions[$ay['Academic_Year_ID'] ?? ''] = ($ay['Year_Name'] ?? 'Unknown') . ' - ' . ($ay['Term'] ?? '');
        }
    }
@endphp

<div class="space-y-6">
    <x-page-header 
        title="Formulir Jadwal Kelas" 
        description="Buat jadwal pelajaran baru untuk kelas tertentu."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Jadwal' => route('schedules.index'), 'Tambah Baru' => '#']"
    />

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-6 py-4 rounded-xl flex items-start shadow-sm">
            <svg class="w-6 h-6 mr-3 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <h4 class="font-bold text-sm mb-1">Terjadi Kesalahan!</h4>
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('schedules.store') }}" method="POST">
            @csrf
            
            <div class="px-6 md:px-12">
                <x-form-section title="Informasi Jadwal" description="Tautkan kelas, subject, dan pengajar.">
                    <div>
                        <x-universal.searchable-select 
                            name="Class_ID" 
                            label="Kelas" 
                            :options="$classOptions" 
                            :required="true" 
                            value="{{ old('Class_ID') }}" 
                        />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Subject_ID" 
                            label="Mata Pelajaran" 
                            :options="$subjectOptions" 
                            :required="true" 
                            value="{{ old('Subject_ID') }}" 
                        />
                    </div>

                    <div>
                        @if(!empty($currentTeacherId))
                            <x-input name="Teacher_ID_Display" label="Pengajar" value="{{ $teacherOptions[$currentTeacherId] ?? $currentTeacherId }}" readonly class="bg-slate-100 cursor-not-allowed" />
                            <input type="hidden" name="Teacher_ID" value="{{ $currentTeacherId }}">
                        @else
                            <x-universal.searchable-select 
                                name="Teacher_ID" 
                                label="Pengajar" 
                                :options="$teacherOptions" 
                                :required="true" 
                                value="{{ old('Teacher_ID') }}" 
                            />
                        @endif
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Academic_Year_ID" 
                            label="Tahun Ajaran" 
                            :options="$ayOptions" 
                            :required="true" 
                            value="{{ old('Academic_Year_ID') }}" 
                        />
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-select name="Day_Of_Week" label="Hari" required>
                                <option value="Monday" {{ old('Day_Of_Week') == 'Monday' ? 'selected' : '' }}>Senin (Monday)</option>
                                <option value="Tuesday" {{ old('Day_Of_Week') == 'Tuesday' ? 'selected' : '' }}>Selasa (Tuesday)</option>
                                <option value="Wednesday" {{ old('Day_Of_Week') == 'Wednesday' ? 'selected' : '' }}>Rabu (Wednesday)</option>
                                <option value="Thursday" {{ old('Day_Of_Week') == 'Thursday' ? 'selected' : '' }}>Kamis (Thursday)</option>
                                <option value="Friday" {{ old('Day_Of_Week') == 'Friday' ? 'selected' : '' }}>Jumat (Friday)</option>
                                <option value="Saturday" {{ old('Day_Of_Week') == 'Saturday' ? 'selected' : '' }}>Sabtu (Saturday)</option>
                                <option value="Sunday" {{ old('Day_Of_Week') == 'Sunday' ? 'selected' : '' }}>Minggu (Sunday)</option>
                            </x-select>
                        </div>
                        <div>
                            <x-input name="Room_Name" label="Ruangan (Room)" value="{{ old('Room_Name') }}" placeholder="Contoh: Lab Komputer" />
                        </div>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input type="time" name="Start_Time" label="Waktu Mulai" required value="{{ old('Start_Time') }}" />
                        </div>
                        <div>
                            <x-input type="time" name="End_Time" label="Waktu Selesai" required value="{{ old('End_Time') }}" />
                        </div>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('schedules.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Jadwal
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



