@extends('layouts.app')
@section('header', 'Perbarui Data Kelas')
@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $programOptions = [];
        foreach($programs as $program) {
            $programOptions[$program['Program_ID']] = $program['Program_Code'] . ' - ' . $program['Program_Name'] . (($program['Is_Active'] ?? 'TRUE') === 'FALSE' ? ' (Nonaktif)' : '');
        }

        $teacherOptions = [];
        foreach($teachers as $teacher) {
            $teacherOptions[$teacher['Teacher_ID']] = $teacher['Teacher_Code'] . ' - ' . $teacher['Full_Name'] . ' (' . $teacher['Specialization'] . ')' . (($teacher['Is_Active'] ?? 'TRUE') === 'FALSE' ? ' (Nonaktif)' : '');
        }
    @endphp

    <x-universal.form 
        action="{{ route('classes.update', $class['Class_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Kelas (Rombel)" 
        description="Mengubah data kelas: {{ $class['Class_ID'] }}"
        buttonText="Perbarui Kelas"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Kelas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Class_Code" 
                        label="Kode Kelas" 
                        :required="true"
                        value="{{ $class['Class_Code'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="Class_Name" 
                        label="Nama Kelas" 
                        :required="true"
                        value="{{ $class['Class_Name'] ?? '' }}"
                    />

                    <x-universal.select 
                        name="Program_ID" 
                        label="Tautkan ke Program" 
                        :required="true"
                        :options="$programOptions"
                        :value="old('Program_ID', $class['Program_ID'])"
                    />

                    <div>
                        <label for="Batch_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Tautkan ke Angkatan <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Batch_ID" id="Batch_ID" required class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Batch_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                @foreach($batches as $batch)
                                    <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID', $class['Batch_ID']) == $batch['Batch_ID'] ? 'selected' : '' }}>
                                        {{ $batch['Batch_Code'] }} - {{ $batch['Batch_Name'] }} {{ ($batch['Is_Active'] ?? 'TRUE') === 'FALSE' ? '(Nonaktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('Batch_ID') <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Homeroom_Teacher_ID" 
                            label="Wali Kelas" 
                            :required="true"
                            :options="$teacherOptions"
                            :value="old('Homeroom_Teacher_ID', $class['Homeroom_Teacher_ID'])"
                        />
                    </div>

                    <x-universal.input 
                        name="Capacity" 
                        label="Kapasitas Maksimal" 
                        type="number"
                        :required="true"
                        value="{{ $class['Capacity'] ?? 20 }}"
                    />

                    <x-universal.input 
                        name="Current_Student" 
                        label="Jumlah Siswa Saat Ini" 
                        type="number"
                        value="{{ $class['Current_Student'] ?? 0 }}"
                    />

                    <div>
                        <x-universal.select 
                            name="Class_Status" 
                            label="Status Kelas" 
                            :options="['Persiapan' => 'Persiapan', 'Aktif' => 'Aktif Berjalan', 'Penuh' => 'Penuh', 'Selesai' => 'Selesai (Lulus)', 'Ditutup' => 'Ditutup / Nonaktif']"
                            value="{{ $class['Class_Status'] ?? 'Aktif' }}"
                        />
                    </div>

                    <div>
                        <x-universal.select 
                            name="Is_Active" 
                            label="Status Sistem" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif (Soft Delete)']"
                            value="{{ $class['Is_Active'] ?? 'TRUE' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi / Ruangan" 
                            value="{{ $class['Description'] ?? '' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Internal" 
                            value="{{ $class['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const programSelect = document.getElementById('Program_ID');
        const batchSelect = document.getElementById('Batch_ID');
        const batchOptions = batchSelect.querySelectorAll('option[data-program]');
        const currentBatchId = "{{ old('Batch_ID', $class['Batch_ID']) }}";

        function filterBatches() {
            const selectedProgramId = programSelect.value;

            batchOptions.forEach(option => {
                if (option.getAttribute('data-program') === selectedProgramId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedProgramId) {
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            }
        }

        programSelect.addEventListener('change', filterBatches);
        
        if (programSelect.value) {
            filterBatches();
            if(currentBatchId) {
                batchSelect.value = currentBatchId;
            }
        }
    });
</script>
@endsection
