@extends('layouts.app')
@section('header', 'Input Nilai')
@section('content')

@php
    $assessmentOptions = [];
    foreach($assessments as $a) {
        $assessmentOptions[$a['Assessment_ID'] ?? ''] = $a['Assessment_Name'] ?? 'Unknown';
    }
    
    $studentOptions = [];
    foreach($students as $s) {
        $studentOptions[$s['Student_ID'] ?? ''] = ($s['Full_Name'] ?? 'Unknown') . ' (' . ($s['Student_Number'] ?? '-') . ')';
    }
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="scoreEngine()">
    <x-universal.form 
        action="{{ route('scores.store') }}" 
        method="POST"
        title="Input Nilai Penilaian" 
        description="Catat nilai siswa. Grade dan status kelulusan dihitung secara otomatis." 
        buttonText="Simpan Nilai"
    >
        <x-slot:alpineBinding>
            :disabled="!student.Student_ID && isSelected"
        </x-slot:alpineBinding>
        
        <div class="space-y-8">
            <x-smart-warning x-show="!student.Student_ID && isSelected" 
                message="Data siswa tidak lengkap. Silakan lengkapi data master terlebih dahulu." 
                type="warning" 
                class="mb-6" />

            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Detail Nilai</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <x-universal.searchable-select 
                        name="Assessment_ID" 
                        label="Penilaian" 
                        :options="$assessmentOptions"
                        value=""
                    />

                    <div>
                        <x-universal.searchable-select 
                            name="Student_ID" 
                            label="Siswa" 
                            :options="$studentOptions"
                            value=""
                        />
                    </div>
                </div>

                <!-- SMART FORM SECTION (AUTO-FILL) -->
                <div class="bg-slate-50 rounded-xl p-5 border border-slate-200 mb-8" x-show="studentId">
                    <h3 class="text-sm font-bold text-slate-700 mb-4 border-b border-slate-200 pb-2">Informasi Siswa (Auto-fill)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <x-universal.input name="auto_name" label="Nama Lengkap" x-model="student.Full_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-universal.input name="auto_program" label="Program" x-model="student.Program_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-universal.input name="auto_batch" label="Batch" x-model="student.Batch_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-universal.input name="auto_class" label="Class" x-model="student.Class_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nilai Akhir <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <input type="number" name="Score_Value" x-model="score" @input="calculateGrade()" class="block w-full text-xl font-black text-center rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm" required>
                    </div>
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Grade (Otomatis)</label>
                        <input type="text" x-model="grade" readonly tabindex="-1" class="block w-full text-xl font-black text-center rounded-xl bg-slate-100 border-slate-200 text-slate-600 px-4 py-2.5 shadow-sm cursor-not-allowed">
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
<script>
    function scoreEngine() {
        return {
            studentId: '',
            isSelected: false,
            student: {},
            score: '', grade: '-', statusText: '-', configGrades: @json(config('assessment.grades')),
            passingScore: {{ config('assessment.passing_score', 65) }},

            init() {
                const el = document.getElementById('Student_ID');
                if(el) {
                    el.addEventListener('change', (e) => {
                        this.studentId = e.target.value;
                        this.fetchStudentData();
                    });
                }
            },
            
            fetchStudentData() {
                if (!this.studentId) {
                    this.student = {};
                    this.isSelected = false;
                    return;
                }
                
                this.isSelected = true;
                
                // Fetch from API
                fetch(`/api/students/${this.studentId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.student = data;
                    })
                    .catch(error => {
                        console.error('Error fetching student data:', error);
                        this.student = {};
                    });
            },
            
            calculateGrade() {
                let s = parseFloat(this.score);
                if (isNaN(s)) { this.grade = '-'; this.statusText = '-'; return; }
                this.grade = 'E';
                for (let key in this.configGrades) {
                    if (s >= this.configGrades[key].min && s <= this.configGrades[key].max) {
                        this.grade = key; break;
                    }
                }
                this.statusText = s >= this.passingScore ? 'PASS' : 'FAIL';
            }
        }
    }
</script>
@endsection
