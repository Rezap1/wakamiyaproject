@extends('layouts.app')
@section('header', 'Tambah Angkatan Baru')
@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $programOptions = [];
        foreach($programs as $program) {
            $programOptions[$program['Program_ID']] = $program['Program_Code'] . ' - ' . $program['Program_Name'];
        }
    @endphp

    <x-universal.form 
        action="{{ route('batches.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Angkatan (Batch)" 
        description="Buat angkatan baru dengan menautkannya pada program studi yang aktif."
        buttonText="Simpan Angkatan"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Angkatan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Batch_Code" 
                        label="Kode Angkatan" 
                        :required="true"
                        placeholder="Contoh: BATCH-01, MAT-A"
                    />

                    <x-universal.input 
                        name="Batch_Name" 
                        label="Nama Angkatan" 
                        :required="true"
                        placeholder="Contoh: Angkatan 1 - 2026"
                    />

                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="Program_ID" 
                            label="Tautkan ke Program" 
                            :required="true"
                            :options="$programOptions"
                            value=""
                        />
                    </div>

                    <x-universal.input 
                        name="Start_Date" 
                        label="Tanggal Mulai" 
                        type="date"
                        :required="true"
                    />

                    <x-universal.input 
                        name="End_Date" 
                        label="Tanggal Selesai" 
                        type="date"
                        :required="true"
                    />

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Batch_Status" 
                            label="Status Angkatan" 
                            :options="['Persiapan' => 'Persiapan (Pendaftaran)', 'Berlangsung' => 'Sedang Berlangsung', 'Selesai' => 'Selesai (Lulus)', 'Ditunda' => 'Ditunda', 'Dibatalkan' => 'Dibatalkan']"
                            value="Berlangsung"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi Singkat" 
                            placeholder="Informasi tambahan mengenai batch ini..."
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
        const startDateInput = document.getElementById('Start_Date');
        const endDateInput = document.getElementById('End_Date');
        
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
                endDateInput.min = this.value;
            });
        }
    });
</script>
@endsection
