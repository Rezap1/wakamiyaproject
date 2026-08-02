@extends('layouts.app')

@section('header', 'Tambah Departemen Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('departments.store') }}" 
        method="POST"
        title="Buat Departemen" 
        description="Lengkapi formulir di bawah ini untuk menambahkan departemen baru."
        buttonText="Simpan Departemen"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="Department_Code" 
                label="Kode Departemen" 
                :required="true"
                placeholder="Contoh: SDM"
            />
            
            <x-universal.input 
                name="Department_Name" 
                label="Nama Departemen" 
                :required="true"
                placeholder="Contoh: Sumber Daya Manusia"
            />
            
            <x-universal.input 
                name="Manager_Employee_ID" 
                label="ID Manajer (Opsional)" 
                placeholder="Contoh: EMP000001"
                helper="Masukkan ID Karyawan dari karyawan yang menjadi manajer departemen ini."
            />
            
            <x-universal.select 
                name="Is_Active" 
                label="Status Aktif" 
                :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                value="TRUE"
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan" 
                    placeholder="Tambahkan catatan jika ada"
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
