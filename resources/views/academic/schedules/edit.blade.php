@extends('layouts.app')

@section('header', 'Edit Jadwal')

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
        title="Formulir Pembaruan Jadwal" 
        description="Mengubah jadwal pelajaran: {{ $schedule['Schedule_ID'] ?? '' }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Jadwal' => route('schedules.index'), 'Edit' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('schedules.update', $schedule['Schedule_ID']) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="px-6 md:px-12">
                <x-form-section title="Informasi Jadwal" description="Tautkan kelas, subject, dan pengajar.">
                    <div>
                        <x-universal.searchable-select 
                            name="Class_ID" 
                            label="Kelas" 
                            :options="$classOptions" 
                            :required="true" 
                            value="{{ old('Class_ID', $schedule['Class_ID'] ?? '') }}" 
                        />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Subject_ID" 
                            label="Mata Pelajaran" 
                            :options="$subjectOptions" 
                            :required="true" 
                            value="{{ old('Subject_ID', $schedule['Subject_ID'] ?? '') }}" 
                        />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Teacher_ID" 
                            label="Pengajar" 
                            :options="$teacherOptions" 
                            :required="true" 
                            value="{{ old('Teacher_ID', $schedule['Teacher_ID'] ?? '') }}" 
                        />
                    </div>

                    <div>
                        <x-universal.searchable-select 
                            name="Academic_Year_ID" 
                            label="Tahun Ajaran" 
                            :options="$ayOptions" 
                            :required="true" 
                            value="{{ old('Academic_Year_ID', $schedule['Academic_Year_ID'] ?? '') }}" 
                        />
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-select name="Day_Of_Week" label="Hari" required>
                                <option value="Monday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Monday' ? 'selected' : '' }}>Senin (Monday)</option>
                                <option value="Tuesday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Tuesday' ? 'selected' : '' }}>Selasa (Tuesday)</option>
                                <option value="Wednesday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Wednesday' ? 'selected' : '' }}>Rabu (Wednesday)</option>
                                <option value="Thursday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Thursday' ? 'selected' : '' }}>Kamis (Thursday)</option>
                                <option value="Friday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Friday' ? 'selected' : '' }}>Jumat (Friday)</option>
                                <option value="Saturday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Saturday' ? 'selected' : '' }}>Sabtu (Saturday)</option>
                                <option value="Sunday" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Sunday' ? 'selected' : '' }}>Minggu (Sunday)</option>
                                <!-- Fallback for existing indonesian data -->
                                <option value="Senin" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Senin' ? 'selected' : '' }} class="hidden">Senin</option>
                                <option value="Selasa" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Selasa' ? 'selected' : '' }} class="hidden">Selasa</option>
                                <option value="Rabu" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Rabu' ? 'selected' : '' }} class="hidden">Rabu</option>
                                <option value="Kamis" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Kamis' ? 'selected' : '' }} class="hidden">Kamis</option>
                                <option value="Jumat" {{ old('Day_Of_Week', $schedule['Day_Of_Week'] ?? '') == 'Jumat' ? 'selected' : '' }} class="hidden">Jumat</option>
                            </x-select>
                        </div>
                        <div>
                            <x-input name="Room" label="Ruangan (Room)" value="{{ old('Room', $schedule['Room'] ?? '') }}" />
                        </div>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input type="time" name="Start_Time" label="Waktu Mulai" required value="{{ old('Start_Time', $schedule['Start_Time'] ?? '') }}" />
                        </div>
                        <div>
                            <x-input type="time" name="End_Time" label="Waktu Selesai" required value="{{ old('End_Time', $schedule['End_Time'] ?? '') }}" />
                        </div>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('schedules.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Perbarui Jadwal
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



