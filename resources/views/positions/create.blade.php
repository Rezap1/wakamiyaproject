@extends('layouts.app')

@section('header', 'Tambah Posisi Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $deptOptions = [];
        foreach($departments as $dept) {
            $deptOptions[$dept['Department_ID']] = $dept['Department_Name'] . ' (' . $dept['Department_Code'] . ')';
        }
    @endphp

    <x-universal.form 
        action="{{ route('positions.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Posisi" 
        description="Lengkapi informasi di bawah ini untuk membuat data posisi baru."
        buttonText="Simpan Posisi"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="Position_Code" 
                label="Kode Posisi" 
                :required="true"
                placeholder="Contoh: HR-MGR"
            />
            
            <x-universal.input 
                name="Position_Name" 
                label="Nama Posisi" 
                :required="true"
                placeholder="Contoh: Manajer HR"
            />
            
            <x-universal.searchable-select 
                name="Department_ID" 
                label="Departemen Induk" 
                :required="true"
                :options="$deptOptions"
                value=""
            />

            <x-universal.select 
                name="Position_Level" 
                label="Level Jabatan" 
                :required="true"
                :options="['Direksi' => 'Direksi', 'Manajer' => 'Manajer', 'Supervisor' => 'Supervisor', 'Staff' => 'Staf']"
                value=""
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan (Opsional)" 
                    placeholder="Tambahkan catatan khusus untuk posisi ini jika diperlukan..."
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
