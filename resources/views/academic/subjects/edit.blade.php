@extends('layouts.app')
@section('header', 'Edit Materi')
@section('content')
<?php
$programOptions = [];
if(isset($programs)) {
    foreach($programs as $p) {
        $programOptions[$p['Program_ID'] ?? ''] = ($p['Program_Name'] ?? 'Unknown') . ' (' . ($p['Program_Code'] ?? '') . ')';
    }
}
?>
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('subjects.update', $subject['Subject_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Materi" 
        description="Mengubah data materi: {{ $subject['Subject_Code'] ?? $subject['Subject_ID'] }}"
        buttonText="Perbarui Materi"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Materi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Subject_Code" 
                        label="Kode Materi" 
                        :required="true"
                        value="{{ $subject['Subject_Code'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="Subject_Name" 
                        label="Nama Materi" 
                        :required="true"
                        value="{{ $subject['Subject_Name'] ?? '' }}"
                    />

                    <div class="md:col-span-2">
                        @if(empty($programOptions))
                            <x-universal.select 
                                name="Program_ID" 
                                label="Program" 
                                :options="['' => 'Belum ada Program tersedia']"
                                :disabled="true"
                                :required="true"
                            />
                        @else
                            <x-universal.searchable-select 
                                name="Program_ID" 
                                label="Program" 
                                :options="$programOptions" 
                                :required="true" 
                                value="{{ old('Program_ID', $subject['Program_ID'] ?? '') }}" 
                            />
                        @endif
                    </div>

                    <x-universal.input 
                        name="Credit" 
                        label="Kredit (SKS)" 
                        type="number"
                        value="{{ $subject['Credit'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="Duration" 
                        label="Durasi (Menit)" 
                        type="number"
                        value="{{ $subject['Duration'] ?? '' }}"
                    />

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Is_Active" 
                            label="Status Sistem" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                            value="{{ $subject['Is_Active'] ?? 'TRUE' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi" 
                            value="{{ $subject['Description'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
