@extends('layouts.app')
@section('header', 'Input Nilai & Evaluasi Karyawan / Siswa')
@section('content')

@php
    $assessmentOptions = [];
    foreach($assessments as $a) {
        $assessmentOptions[$a['Assessment_ID'] ?? ''] = ($a['Title'] ?? $a['Assessment_Name'] ?? 'Penilaian') . ' (' . ($a['Assessment_ID'] ?? '-') . ')';
    }
    
    $studentOptions = [];
    foreach($students as $s) {
        $studentOptions[$s['Student_ID'] ?? ''] = ($s['Full_Name'] ?? 'Unknown') . ' (' . ($s['Student_Number'] ?? '-') . ')';
    }

    $subjectOptions = [];
    foreach($subjects as $subj) {
        $subjectOptions[$subj['Subject_ID'] ?? ''] = ($subj['Subject_Name'] ?? 'Mata Pelajaran') . ' (' . ($subj['Subject_Code'] ?? '-') . ')';
    }
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="academicScoreEngine()">
    <x-universal.form 
        action="{{ route('scores.store') }}" 
        method="POST"
        title="Form Penilaian & Evaluasi" 
        description="Pilih kategori penilaian (Akademik Umum, Olahraga, atau Bahasa). Sistem akan menghitung grade & kelulusan secara otomatis." 
        buttonText="Simpan Data Nilai"
    >
        <div class="space-y-8">
            <!-- SECTION 1: HEADER & CATEGORY SELECTION -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Dasar & Kategori Assessment</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="md:col-span-3">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase tracking-wider">Kategori Penilaian <span class="text-rose-500 font-black">*</span></label>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                                :class="category === 'GENERAL' ? 'border-blue-600 bg-blue-50/50 text-blue-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                                <input type="radio" name="Assessment_Category" value="GENERAL" x-model="category" class="sr-only">
                                <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                <span class="text-xs uppercase font-bold">1. Akademik Umum</span>
                            </label>

                            <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                                :class="category === 'SPORTS' ? 'border-emerald-600 bg-emerald-50/50 text-emerald-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                                <input type="radio" name="Assessment_Category" value="SPORTS" x-model="category" class="sr-only">
                                <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span class="text-xs uppercase font-bold">2. Olahraga (Sports)</span>
                            </label>

                            <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                                :class="category === 'LANGUAGE' ? 'border-purple-600 bg-purple-50/50 text-purple-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                                <input type="radio" name="Assessment_Category" value="LANGUAGE" x-model="category" class="sr-only">
                                <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                                <span class="text-xs uppercase font-bold">3. Bahasa (Language)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.searchable-select 
                        name="Assessment_ID" 
                        label="Pilih Assessment / Penilaian" 
                        :options="$assessmentOptions"
                        value=""
                        :required="true"
                    />

                    <x-universal.searchable-select 
                        name="Student_ID" 
                        label="Pilih Siswa / Peserta" 
                        :options="$studentOptions"
                        value=""
                        :required="true"
                    />
                </div>
            </div>

            <!-- SECTION 2: DYNAMIC EVALUATION INPUTS BASED ON CATEGORY -->
            <div>
                <!-- CATEGORY 1: GENERAL ACADEMIC SCORE -->
                <div x-show="category === 'GENERAL'" class="space-y-6">
                    <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Penilaian Akademik Umum</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-universal.searchable-select 
                            name="Subject_ID" 
                            label="Mata Pelajaran (Opsional)" 
                            :options="$subjectOptions"
                            value=""
                        />

                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nilai Akhir (0 - 100) <span class="text-rose-500 font-black">*</span></label>
                            <input type="number" name="Score_Value" x-model="scoreGeneral" @input="recalculate()" min="0" max="100" class="block w-full text-xl font-black text-center rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 px-4 py-2.5 shadow-sm">
                        </div>
                    </div>
                </div>

                <!-- CATEGORY 2: SPORTS EVALUATION METRICS -->
                <div x-show="category === 'SPORTS'" class="space-y-6">
                    <h3 class="text-sm font-bold text-emerald-800 mb-4 border-b border-emerald-100 pb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Metrik Evaluasi Olahraga (Sports Metrics)
                    </h3>
                    <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Jarak Lari (km)</label>
                            <input type="number" step="0.1" min="0" name="running_distance" x-model="sports.running_distance" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Waktu Lari (menit)</label>
                            <input type="number" step="0.1" min="0" name="running_time" x-model="sports.running_time" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Push Up</label>
                            <input type="number" min="0" name="push_up" x-model="sports.push_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Sit Up</label>
                            <input type="number" min="0" name="sit_up" x-model="sports.sit_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- CATEGORY 3: LANGUAGE EVALUATION RUBRICS -->
                <div x-show="category === 'LANGUAGE'" class="space-y-6">
                    <h3 class="text-sm font-bold text-purple-800 mb-4 border-b border-purple-100 pb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                        Rubrik Evaluasi Kemampuan Bahasa (Skala 0 - 100)
                    </h3>
                    <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Speaking (Berbicara)</label>
                            <input type="number" min="0" max="100" name="speaking" x-model="lang.speaking" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Writing (Menulis)</label>
                            <input type="number" min="0" max="100" name="writing" x-model="lang.writing" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Listening (Mendengar)</label>
                            <input type="number" min="0" max="100" name="listening" x-model="lang.listening" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Reading (Membaca)</label>
                            <input type="number" min="0" max="100" name="reading" x-model="lang.reading" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Ethics (Etika)</label>
                            <input type="number" min="0" max="100" name="ethics" x-model="lang.ethics" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Learning Motivation</label>
                            <input type="number" min="0" max="100" name="motivation" x-model="lang.motivation" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Attendance (Kehadiran)</label>
                            <input type="number" min="0" max="100" name="attendance" x-model="lang.attendance" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-purple-500">
                        </div>
                    </div>
                </div>

                <!-- SCORE SUMMARY PREVIEW CARD -->
                <div class="mt-6 bg-slate-900 text-white p-5 rounded-2xl flex items-center justify-between shadow-lg">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kalkulasi Skor Komposit</p>
                        <h4 class="text-2xl font-black mt-1" x-text="computedScore + ' / 100'"></h4>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 text-xs font-bold rounded-lg uppercase"
                              :class="computedScore >= 65 ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                              x-text="computedScore >= 65 ? 'PASS (LULUS)' : 'FAIL (TIDAK LULUS)'"></span>
                    </div>
                </div>

                <!-- NOTES -->
                <div class="mt-6">
                    <x-universal.textarea 
                        name="Notes" 
                        label="Catatan & Evaluasi Pengajar / Pelatih" 
                        placeholder="Masukkan evaluasi deskriptif, umpan balik, atau saran perbaikan..."
                    />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function academicScoreEngine() {
        return {
            category: 'GENERAL',
            scoreGeneral: 80,
            sports: {
                running_distance: 5,
                running_time: 30,
                push_up: 25,
                sit_up: 25
            },
            lang: {
                speaking: 80,
                writing: 85,
                listening: 78,
                reading: 90,
                ethics: 85,
                motivation: 90,
                attendance: 100
            },
            computedScore: 80,

            init() {
                this.recalculate();
            },

            recalculate() {
                if (this.category === 'SPORTS') {
                    const dist = parseFloat(this.sports.running_distance || 0);
                    const push = parseInt(this.sports.push_up || 0);
                    const sit = parseInt(this.sports.sit_up || 0);
                    
                    const distScore = Math.min(100, (dist / 5) * 100 * 0.3);
                    const pushScore = Math.min(100, (push / 30) * 100 * 0.35);
                    const sitScore = Math.min(100, (sit / 30) * 100 * 0.35);
                    
                    this.computedScore = Math.min(100, Math.round(distScore + pushScore + sitScore));
                } else if (this.category === 'LANGUAGE') {
                    const rubrics = [
                        parseFloat(this.lang.speaking || 0),
                        parseFloat(this.lang.writing || 0),
                        parseFloat(this.lang.listening || 0),
                        parseFloat(this.lang.reading || 0),
                        parseFloat(this.lang.ethics || 0),
                        parseFloat(this.lang.motivation || 0),
                        parseFloat(this.lang.attendance || 0)
                    ];
                    const sum = rubrics.reduce((a, b) => a + b, 0);
                    this.computedScore = Math.round(sum / rubrics.length);
                } else {
                    this.computedScore = Math.min(100, Math.max(0, parseInt(this.scoreGeneral || 0)));
                }
            }
        }
    }
</script>
@endsection
