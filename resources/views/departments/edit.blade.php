@extends('layouts.app')

@section('header', 'Edit Departemen')

@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('departments.update', $department['Department_ID']) }}" 
        method="PUT"
        title="Perbarui Departemen" 
        description="ID: {{ $department['Department_ID'] }}"
        buttonText="Perbarui Departemen"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="Department_Code" 
                label="Kode Departemen" 
                :required="true"
                value="{{ $department['Department_Code'] ?? '' }}"
            />
            
            <x-universal.input 
                name="Department_Name" 
                label="Nama Departemen" 
                :required="true"
                value="{{ $department['Department_Name'] ?? '' }}"
            />
            
            <x-universal.input 
                name="Manager_Employee_ID" 
                label="ID Manajer" 
                value="{{ $department['Manager_Employee_ID'] ?? '' }}"
            />
            
            <x-universal.select 
                name="Is_Active" 
                label="Status Aktif" 
                :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                value="{{ $department['Is_Active'] ?? 'TRUE' }}"
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan" 
                    value="{{ $department['Notes'] ?? '' }}"
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
