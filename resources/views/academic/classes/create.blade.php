@extends('layouts.app')
@section('header', 'Tambah Kelas Baru')
@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $programOptions = [];
        foreach($programs as $program) {
            $programOptions[$program['Program_ID']] = $program['Program_Code'] . ' - ' . $program['Program_Name'];
        }

        $teacherOptions = [];
        foreach($teachers as $teacher) {
            $teacherOptions[$teacher['Teacher_ID']] = $teacher['Teacher_Code'] . ' - ' . $teacher['Full_Name'] . ' (' . $teacher['Specialization'] . ')';
        }
    @endphp

    <x-universal.form 
        action="{{ route('classes.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Kelas (Rombel)" 
        description="Buat ruang kelas baru dan tautkan ke Program, Angkatan, serta Wali Kelas."
        buttonText="Simpan Kelas"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Kelas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Class_Code" 
                        label="Kode Kelas" 
                        :required="true"
                        placeholder="Contoh: KLS-A, KLS-JP-01"
                    />

                    <x-universal.input 
                        name="Class_Name" 
                        label="Nama Kelas" 
                        :required="true"
                        placeholder="Contoh: Kelas Pagi A"
                    />

                    <x-universal.select 
                        name="Program_ID" 
                        label="Tautkan ke Program" 
                        :required="true"
                        :options="$programOptions"
                        value=""
                    />

                    <div>
                        <label for="Batch_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Tautkan ke Angkatan <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Batch_ID" id="Batch_ID" required disabled class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Batch_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                <option value="" disabled selected>Pilih Program terlebih dahulu...</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID') == $batch['Batch_ID'] ? 'selected' : '' }}>
                                        {{ $batch['Batch_Code'] }} - {{ $batch['Batch_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500 font-medium">*Daftar angkatan akan disaring berdasarkan program terpilih.</p>
                        @error('Batch_ID') <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Homeroom_Teacher_ID" 
                            label="Wali Kelas" 
                            :required="true"
                            :options="$teacherOptions"
                            value=""
                        />
                    </div>

                    <x-universal.input 
                        name="Capacity" 
                        label="Kapasitas Maksimal" 
                        type="number"
                        :required="true"
                        value="20"
                    />

                    <x-universal.input 
                        name="Current_Student" 
                        label="Jumlah Siswa Saat Ini" 
                        type="number"
                        value="0"
                    />

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Class_Status" 
                            label="Status Kelas" 
                            :options="['Persiapan' => 'Persiapan', 'Aktif' => 'Aktif Berjalan', 'Penuh' => 'Penuh', 'Selesai' => 'Selesai (Lulus)', 'Ditutup' => 'Ditutup / Nonaktif']"
                            value="Aktif"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi / Ruangan" 
                            placeholder="Informasi tambahan, letak ruang kelas, atau jadwal..."
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Internal" 
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
        const oldBatchId = "{{ old('Batch_ID') }}";

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
                batchSelect.disabled = false;
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            } else {
                batchSelect.disabled = true;
                batchSelect.value = "";
            }
        }

        programSelect.addEventListener('change', filterBatches);
        
        if (programSelect.value) {
            filterBatches();
            if(oldBatchId) {
                batchSelect.value = oldBatchId;
            }
        }
    });
</script>
@endsection
