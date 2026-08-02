@extends('layouts.app')

@section('header', 'Edit Posisi')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $deptOptions = [];
        foreach($departments as $dept) {
            $deptOptions[$dept['Department_ID']] = $dept['Department_Name'] . ' (' . $dept['Department_Code'] . ')';
        }
        
        $levels = ['Direksi' => 'Direksi', 'Manajer' => 'Manajer', 'Supervisor' => 'Supervisor', 'Staff' => 'Staf'];
        $currentLevel = old('Position_Level', $position['Position_Level']);
        if (!array_key_exists($currentLevel, $levels) && $currentLevel != '') {
            $levels[$currentLevel] = $currentLevel;
        }
    @endphp

    <x-universal.form 
        action="{{ route('positions.update', $position['Position_ID']) }}" 
        method="PUT"
        title="Perbarui Data Posisi" 
        description="ID Posisi: {{ $position['Position_ID'] }}"
        buttonText="Perbarui Posisi"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="Position_Code" 
                label="Kode Posisi" 
                :required="true"
                value="{{ $position['Position_Code'] ?? '' }}"
            />
            
            <x-universal.input 
                name="Position_Name" 
                label="Nama Posisi" 
                :required="true"
                value="{{ $position['Position_Name'] ?? '' }}"
            />
            
            <x-universal.searchable-select 
                name="Department_ID" 
                label="Departemen Induk" 
                :required="true"
                :options="$deptOptions"
                value="{{ $position['Department_ID'] ?? '' }}"
            />

            <x-universal.select 
                name="Position_Level" 
                label="Level Jabatan" 
                :required="true"
                :options="$levels"
                value="{{ $currentLevel }}"
            />

            <x-universal.select 
                name="Is_Active" 
                label="Status Aktif" 
                :required="true"
                :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                value="{{ $position['Is_Active'] ?? 'TRUE' }}"
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan (Opsional)" 
                    value="{{ $position['Notes'] ?? '' }}"
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
