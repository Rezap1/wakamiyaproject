@extends('layouts.app')
@section('header', 'Perbarui Data Program')
@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('programs.update', $program['Program_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Program" 
        description="Mengubah data program: {{ $program['Program_ID'] }}"
        buttonText="Perbarui Program"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Program</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Program_Code" 
                        label="Kode Program" 
                        :required="true"
                        value="{{ $program['Program_Code'] ?? '' }}"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Program_Name" 
                            label="Nama Program" 
                            :required="true"
                            value="{{ $program['Program_Name'] ?? '' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Program_Category" 
                            label="Kategori Program" 
                            :required="true"
                            :options="['Akademik' => 'Akademik', 'Pelatihan Bahasa' => 'Pelatihan Bahasa', 'Sertifikasi Profesi' => 'Sertifikasi Profesi', 'Ekstrakurikuler' => 'Ekstrakurikuler', 'Lainnya' => 'Lainnya']"
                            value="{{ $program['Program_Category'] ?? '' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi Program" 
                            value="{{ $program['Description'] ?? '' }}"
                        />
                    </div>
                    
                    <div>
                        <x-universal.select 
                            name="Is_Active" 
                            label="Status Aktif Sistem" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                            value="{{ $program['Is_Active'] ?? 'TRUE' }}"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Internal" 
                            value="{{ $program['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
