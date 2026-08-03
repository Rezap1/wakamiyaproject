@extends('layouts.app')
@section('header', 'Buat Penilaian')
@section('content')

@php
    $categoryOptions = [];
    $categories = config('assessment.categories') ?? ['Placement Test', 'Daily Quiz', 'Assignment', 'Mid Test', 'Final Test'];
    foreach($categories as $cat) {
        $categoryOptions[$cat] = $cat;
    }
    
    $programOptions = [];
    foreach($programs ?? [] as $p) {
        $programOptions[$p['Program_ID'] ?? ''] = $p['Name'] ?? 'Unknown';
    }
    
    $classOptions = [];
    foreach($classes ?? [] as $c) {
        $classOptions[$c['Class_ID'] ?? ''] = $c['Name'] ?? 'Unknown';
    }
    
    $teacherOptions = [];
    foreach($teachers ?? [] as $t) {
        $teacherOptions[$t['Teacher_ID'] ?? ''] = $t['Full_Name'] ?? 'Unknown';
    }
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <x-universal.form 
        action="{{ route('assessments.store') }}" 
        method="POST"
        title="Buat Penilaian Baru" 
        description="Buat jadwal dan parameter penilaian baru." 
        buttonText="Simpan Penilaian"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Identitas Penilaian</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Name" 
                        label="Nama Penilaian" 
                        :required="true"
                    />
                    
                    <x-universal.select 
                        name="Category" 
                        label="Kategori" 
                        :required="true"
                        :options="$categoryOptions"
                    />
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Target Peserta</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.select 
                        name="Program_ID" 
                        label="Program" 
                        :options="$programOptions"
                        value=""
                    />
                    
                    <x-universal.select 
                        name="Class_ID" 
                        label="Kelas" 
                        :options="$classOptions"
                        value=""
                    />
                    
                    @if(!empty($currentTeacherId))
                        <x-universal.input 
                            name="Teacher_ID_Display" 
                            label="Pengajar" 
                            value="{{ $teacherOptions[$currentTeacherId] ?? $currentTeacherId }}" 
                            readonly 
                            class="bg-slate-100 cursor-not-allowed" 
                        />
                        <input type="hidden" name="Teacher_ID" value="{{ $currentTeacherId }}">
                    @else
                        <x-universal.select 
                            name="Teacher_ID" 
                            label="Pengajar" 
                            :options="$teacherOptions"
                            value=""
                        />
                    @endif
                    
                    <x-universal.input 
                        name="Exam_Date" 
                        label="Tanggal Ujian" 
                        type="date"
                        value="{{ date('Y-m-d') }}"
                    />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
