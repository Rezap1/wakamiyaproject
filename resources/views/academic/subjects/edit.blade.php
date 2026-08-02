@extends('layouts.app')
@section('header', 'Edit Mata Pelajaran')
@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('subjects.update', $subject['Subject_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Mata Pelajaran" 
        description="Mengubah data mata pelajaran: {{ $subject['Subject_Code'] ?? $subject['Subject_ID'] }}"
        buttonText="Perbarui Mata Pelajaran"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Mata Pelajaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Subject_Code" 
                        label="Kode Mata Pelajaran" 
                        :required="true"
                        value="{{ $subject['Subject_Code'] ?? '' }}"
                    />

                    <x-universal.input 
                        name="Subject_Name" 
                        label="Nama Mata Pelajaran" 
                        :required="true"
                        value="{{ $subject['Subject_Name'] ?? '' }}"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Program_ID" 
                            label="ID Program" 
                            value="{{ $subject['Program_ID'] ?? '' }}"
                        />
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
