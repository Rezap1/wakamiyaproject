@extends('layouts.app')
@section('header', 'Tambah Program Baru')
@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('programs.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Program" 
        description="Lengkapi informasi di bawah ini untuk membuat entitas program baru. Kode Program harus unik."
        buttonText="Simpan Program"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Program</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Program_Code" 
                        label="Kode Program" 
                        :required="true"
                        placeholder="Contoh: PRG-MAT, KLS-10A"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Program_Name" 
                            label="Nama Program" 
                            :required="true"
                            placeholder="Contoh: Matematika Lanjutan Kelas 10"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Program_Category" 
                            label="Kategori Program" 
                            :required="true"
                            :options="['Akademik' => 'Akademik', 'Pelatihan Bahasa' => 'Pelatihan Bahasa', 'Sertifikasi Profesi' => 'Sertifikasi Profesi', 'Ekstrakurikuler' => 'Ekstrakurikuler', 'Lainnya' => 'Lainnya']"
                            value=""
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi Program" 
                            placeholder="Penjelasan singkat mengenai program ini..."
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
@endsection
