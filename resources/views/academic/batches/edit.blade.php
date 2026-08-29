@extends('layouts.app')
@section('header', 'Perbarui Data Angkatan')
@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $programOptions = [];
        foreach($programs as $program) {
            $programOptions[$program['Program_ID']] = $program['Program_Code'] . ' - ' . $program['Program_Name'] . (($program['Is_Active'] ?? 'TRUE') === 'FALSE' ? ' (Program Nonaktif)' : '');
        }
    @endphp

    <x-universal.form 
        action="{{ route('batches.update', $batch['Batch_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Angkatan" 
        description="Mengubah data angkatan: {{ $batch['Batch_ID'] }}"
        buttonText="Perbarui Angkatan"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Angkatan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Batch_Code" 
                        label="Kode Angkatan" 
                        :required="true"
                        value="{{ $batch['Batch_Code'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="Batch_Name" 
                        label="Nama Angkatan" 
                        :required="true"
                        value="{{ $batch['Batch_Name'] ?? '' }}"
                    />

                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="Program_ID" 
                            label="Tautkan ke Program" 
                            :required="true"
                            :options="$programOptions"
                            value="{{ $batch['Program_ID'] ?? '' }}"
                        />
                    </div>

                    <x-universal.input 
                        name="Start_Date" 
                        label="Tanggal Mulai" 
                        type="date"
                        :required="true"
                        value="{{ $batch['Start_Date'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="End_Date" 
                        label="Tanggal Selesai" 
                        type="date"
                        :required="true"
                        value="{{ $batch['End_Date'] ?? '' }}"
                    />

                    <div>
                        <x-universal.select 
                            name="Batch_Status" 
                            label="Status Angkatan" 
                            :options="['Persiapan' => 'Persiapan (Pendaftaran)', 'Berlangsung' => 'Sedang Berlangsung', 'Selesai' => 'Selesai (Lulus)', 'Ditunda' => 'Ditunda', 'Dibatalkan' => 'Dibatalkan']"
                            value="{{ $batch['Batch_Status'] ?? 'Berlangsung' }}"
                        />
                    </div>
                    
                    <div>
                        <x-universal.select 
                            name="Is_Active" 
                            label="Status Sistem" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif (Soft Delete)']"
                            value="{{ $batch['Is_Active'] ?? 'TRUE' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi Singkat" 
                            value="{{ $batch['Description'] ?? '' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Internal" 
                            value="{{ $batch['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('Start_Date');
        const endDateInput = document.getElementById('End_Date');
        
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
                endDateInput.min = this.value;
            });
            if (startDateInput.value) {
                endDateInput.min = startDateInput.value;
            }
        }
    });
</script>
@endsection
