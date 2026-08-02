@extends('layouts.app')
@section('header', 'Edit Nilai')
@section('content')

@php
    $result = \App\Helpers\GradeHelper::calculate($score['Score_Value'] ?? 85);
@endphp

<div class="max-w-4xl mx-auto" x-data="scoreEngine()">
    <x-universal.form 
        action="{{ route('scores.update', $score['Score_ID'] ?? 1) }}" 
        method="PUT"
        title="Edit Nilai Penilaian" 
        description="Perbarui nilai siswa. Grade dan status kelulusan dihitung secara otomatis."
        buttonText="Perbarui Nilai"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Detail Pembaruan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Assessment_ID" 
                        label="Penilaian" 
                        value="{{ $score['Assessment_ID'] ?? 'ASM-001' }}"
                        readonly
                    />

                    <x-universal.input 
                        name="Student_ID" 
                        label="Siswa" 
                        value="{{ $score['Student_ID'] ?? 'STU-001' }}"
                        readonly
                    />
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nilai Akhir <span class="text-rose-500 font-black ml-0.5">*</span></label>
                    <input type="number" name="Score" x-model="score" @input="calculateGrade()" class="block w-full text-xl font-black text-center rounded-xl bg-slate-50 border-slate-200 text-blue-900 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm" required>
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Grade (Otomatis)</label>
                    <input type="text" x-model="grade" class="block w-full text-xl font-black text-center rounded-xl bg-slate-100 border-slate-200 text-slate-600 px-4 py-2.5 shadow-sm cursor-not-allowed" readonly tabindex="-1">
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Status (Otomatis)</label>
                    <input type="text" x-model="statusText" :class="statusText === 'PASS' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (statusText === 'FAIL' ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-slate-500 bg-slate-100 border-slate-200')" class="block w-full border text-sm font-black uppercase rounded-xl px-4 py-4 text-center cursor-not-allowed shadow-sm" readonly tabindex="-1">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-universal.input 
                    name="Teacher_ID" 
                    label="Pengajar / Evaluator" 
                    value="{{ $score['Teacher_ID'] ?? 'Tanaka Sensei' }}"
                />

                <x-universal.input 
                    name="Exam_Date" 
                    label="Tanggal Ujian" 
                    type="date"
                    :required="true"
                    value="{{ date('Y-m-d') }}"
                />

                <div class="md:col-span-2">
                    <x-universal.textarea 
                        name="Notes" 
                        label="Catatan / Keterangan" 
                        value="{{ $score['Notes'] ?? '' }}"
                    />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function scoreEngine() {
        return {
            score: '{{ $score['Score_Value'] ?? 85 }}',
            grade: '{{ $result['grade'] ?? "-" }}',
            statusText: '{{ ($result['pass'] ?? true) ? 'PASS' : 'FAIL' }}',
            configGrades: @json(config('assessment.grades')),
            passingScore: {{ config('assessment.passing_score', 65) }},
            
            init() {
                this.calculateGrade();
            },
            
            calculateGrade() {
                let s = parseFloat(this.score);
                if (isNaN(s)) {
                    this.grade = '-';
                    this.statusText = '-';
                    return;
                }
                
                this.grade = 'E';
                for (let key in this.configGrades) {
                    if (s >= this.configGrades[key].min && s <= this.configGrades[key].max) {
                        this.grade = key;
                        break;
                    }
                }
                
                this.statusText = s >= this.passingScore ? 'PASS' : 'FAIL';
            }
        }
    }
</script>
@endsection
