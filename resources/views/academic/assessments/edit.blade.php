@extends('layouts.app')
@section('header', 'Edit Penilaian')
@section('content')

@php
    $categoryOptions = [];
    foreach(config('assessment.categories', ['Daily Quiz', 'Mid Test', 'Final Test']) as $cat) {
        $categoryOptions[$cat] = $cat;
    }
@endphp

<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('assessments.update', $assessment['Assessment_ID'] ?? 1) }}" 
        method="PUT"
        title="Edit Penilaian" 
        description="Perbarui konfigurasi penilaian."
        buttonText="Perbarui Penilaian"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Detail Pembaruan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Assessment_Code" 
                        label="Kode Penilaian" 
                        value="{{ $assessment['Assessment_Code'] ?? 'ASM-001' }}"
                        readonly
                    />

                    <x-universal.input 
                        name="Assessment_Name" 
                        label="Nama Penilaian" 
                        :required="true"
                        value="{{ $assessment['Assessment_Name'] ?? 'Midterm Exam' }}"
                    />

                    <x-universal.select 
                        name="Category" 
                        label="Kategori" 
                        :options="$categoryOptions"
                        value="{{ $assessment['Category'] ?? 'Mid Test' }}"
                    />

                    <x-universal.select 
                        name="Status" 
                        label="Status" 
                        :options="['Draft' => 'Draft', 'Published' => 'Published', 'Closed' => 'Closed', 'Archived' => 'Archived']"
                        value="{{ $assessment['Status'] ?? 'Published' }}"
                    />

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Description" 
                            label="Deskripsi" 
                            value="{{ $assessment['Description'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
