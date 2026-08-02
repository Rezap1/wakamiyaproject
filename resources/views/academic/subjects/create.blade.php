@extends('layouts.app')
@section('header', 'Tambah Mata Pelajaran')
@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('subjects.store') }}" 
        method="POST"
        title="Pendaftaran Mata Pelajaran Baru" 
        description="Buat subject atau mata pelajaran baru."
        buttonText="Simpan Mata Pelajaran"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Mata Pelajaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Subject_Code" 
                        label="Kode Mata Pelajaran" 
                        :required="true"
                        placeholder="Contoh: MAT-01"
                    />

                    <x-universal.input 
                        name="Subject_Name" 
                        label="Nama Mata Pelajaran" 
                        :required="true"
                        placeholder="Contoh: Matematika Dasar"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Program_ID" 
                            label="ID Program" 
                            placeholder="Contoh: PRG-001"
                        />
                    </div>

                    <x-universal.input 
                        name="Credit" 
                        label="Kredit (SKS)" 
                        type="number"
                        placeholder="3"
                    />

                    <x-universal.input 
                        name="Duration" 
                        label="Durasi (Menit)" 
                        type="number"
                        placeholder="90"
                    />

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Is_Active" 
                            label="Status Sistem" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                            value="TRUE"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi" 
                            placeholder="Deskripsi mata pelajaran..."
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
