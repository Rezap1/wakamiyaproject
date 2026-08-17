@extends('layouts.app')
@section('header', 'Input Nilai & Penilaian Siswa')
@section('content')

@php
    $studentOptions = [];
    foreach($students as $s) {
        $studentOptions[$s['Student_ID'] ?? ''] = ($s['Student_Number'] ?? $s['Student_ID']) . ' — ' . ($s['Full_Name'] ?? 'Unknown');
    }

    $subjectOptions = [];
    foreach($subjects as $subj) {
        $subjectOptions[$subj['Subject_ID'] ?? ''] = ($subj['Subject_Name'] ?? 'Mata Pelajaran') . ' (' . ($subj['Subject_Code'] ?? '-') . ')';
    }

    $scaleLabels = [
        1 => '1 - Sangat Kurang',
        2 => '2 - Kurang',
        3 => '3 - Cukup',
        4 => '4 - Baik',
        5 => '5 - Sangat Baik',
    ];
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="teacherAssessmentEngine()">
    <x-universal.form 
        action="{{ route('scores.store') }}" 
        method="POST"
        title="Form Penilaian Siswa" 
        description="Pilih siswa, tanggal, dan tipe penilaian. Form akan berubah secara dinamis sesuai tipe yang dipilih." 
        buttonText="Simpan Nilai"
        x-ref="scoreForm"
    >
        <div class="space-y-8">
            <!-- SECTION 1: STUDENT & DATE -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Dasar</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.searchable-select 
                        name="Student_ID" 
                        label="Pilih Siswa" 
                        :options="$studentOptions"
                        value=""
                        :required="true"
                    />

                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Penilaian <span class="text-rose-500 font-black">*</span></label>
                        <input type="date" name="Assessment_Date" x-model="assessmentDate" class="block w-full rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 px-4 py-2.5 shadow-sm text-sm">
                    </div>
                </div>
            </div>

            <!-- SECTION 2: CATEGORY SELECTION -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Tipe Penilaian <span class="text-rose-500 font-black">*</span></h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                        :class="category === 'LANGUAGE' ? 'border-purple-600 bg-purple-50/50 text-purple-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="Assessment_Category" value="LANGUAGE" x-model="category" class="sr-only">
                        <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                        <span class="text-xs uppercase font-bold">Bahasa</span>
                    </label>

                    <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                        :class="category === 'GENERAL' ? 'border-blue-600 bg-blue-50/50 text-blue-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="Assessment_Category" value="GENERAL" x-model="category" class="sr-only">
                        <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        <span class="text-xs uppercase font-bold">Ujian Bab</span>
                    </label>

                    <label class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all text-center"
                        :class="category === 'SPORTS' ? 'border-emerald-600 bg-emerald-50/50 text-emerald-700 font-bold shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="Assessment_Category" value="SPORTS" x-model="category" class="sr-only">
                        <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span class="text-xs uppercase font-bold">Olahraga</span>
                    </label>
                </div>
            </div>

            <!-- SECTION 3: DYNAMIC FORM -->
            <div>
                <!-- BAHASA (LANGUAGE) -->
                <div x-show="category === 'LANGUAGE'" x-transition class="space-y-6">
                    <h3 class="text-sm font-bold text-purple-800 mb-4 border-b border-purple-100 pb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                        Penilaian Bahasa — Skala 1 s/d 5
                    </h3>
                    <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach([
                            'speaking' => 'Bicara',
                            'writing' => 'Menulis',
                            'listening' => 'Mendengar',
                            'reading' => 'Membaca',
                            'ethics' => 'Sikap / Etika',
                            'motivation' => 'Motivasi Belajar',
                            'attendance' => 'Kehadiran'
                        ] as $field => $label)
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ $label }} <span class="text-rose-500">*</span></label>
                            <select name="{{ $field }}" x-model="lang.{{ $field }}" @change="recalculate()" class="w-full text-sm font-semibold rounded-xl border-slate-200 focus:ring-purple-500 bg-white">
                                <option value="">— Pilih —</option>
                                @foreach($scaleLabels as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- UJIAN BAB (GENERAL) -->
                <div x-show="category === 'GENERAL'" x-transition class="space-y-6">
                    <h3 class="text-sm font-bold text-blue-800 mb-4 border-b border-blue-100 pb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Penilaian Ujian Bab
                    </h3>
                    <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-universal.searchable-select 
                            name="Subject_ID" 
                            label="Mata Pelajaran" 
                            :options="$subjectOptions"
                            value=""
                            :required="true"
                        />

                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nilai (1 - 100) <span class="text-rose-500 font-black">*</span></label>
                            <input type="number" name="Score_Value" x-model="scoreGeneral" @input="recalculate()" min="1" max="100" class="block w-full text-xl font-black text-center rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 px-4 py-2.5 shadow-sm">
                        </div>
                    </div>
                </div>

                <!-- OLAHRAGA (SPORTS) -->
                <div x-show="category === 'SPORTS'" x-transition class="space-y-6">
                    <h3 class="text-sm font-bold text-emerald-800 mb-4 border-b border-emerald-100 pb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Metrik Evaluasi Olahraga
                    </h3>
                    <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Jarak Lari (km)</label>
                            <input type="number" step="0.1" min="0" name="running_distance" x-model="sports.running_distance" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Waktu (menit)</label>
                            <input type="number" step="0.1" min="0" name="running_time" x-model="sports.running_time" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Push Up</label>
                            <input type="number" min="0" name="push_up" x-model="sports.push_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Sit Up</label>
                            <input type="number" min="0" name="sit_up" x-model="sports.sit_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200 focus:ring-emerald-500">
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
                        label="Catatan (Opsional)" 
                        placeholder="Masukkan catatan evaluasi atau umpan balik..."
                    />
                </div>
            </div>

            <!-- Hidden: disable fields from other categories -->
            <template x-if="category !== 'LANGUAGE'">
                <div>
                    <input type="hidden" name="speaking" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="writing" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="listening" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="reading" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="ethics" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="motivation" value="" :disabled="category !== 'LANGUAGE'">
                    <input type="hidden" name="attendance" value="" :disabled="category !== 'LANGUAGE'">
                </div>
            </template>
        </div>
    </x-universal.form>
</div>

<script>
    function teacherAssessmentEngine() {
        return {
            category: 'LANGUAGE',
            assessmentDate: new Date().toISOString().split('T')[0],
            scoreGeneral: 80,
            sports: {
                running_distance: 0,
                running_time: 0,
                push_up: 0,
                sit_up: 0
            },
            lang: {
                speaking: '',
                writing: '',
                listening: '',
                reading: '',
                ethics: '',
                motivation: '',
                attendance: ''
            },
            computedScore: 0,
            submitting: false,

            init() {
                this.recalculate();
                // Double-submit protection
                const form = this.$refs.scoreForm;
                if (form) {
                    form.addEventListener('submit', (e) => {
                        if (this.submitting) {
                            e.preventDefault();
                            return;
                        }
                        this.submitting = true;
                        const btn = form.querySelector('button[type="submit"]');
                        if (btn) {
                            btn.disabled = true;
                            btn.textContent = 'Menyimpan...';
                        }
                    });
                }
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
                        parseInt(this.lang.speaking || 0),
                        parseInt(this.lang.writing || 0),
                        parseInt(this.lang.listening || 0),
                        parseInt(this.lang.reading || 0),
                        parseInt(this.lang.ethics || 0),
                        parseInt(this.lang.motivation || 0),
                        parseInt(this.lang.attendance || 0)
                    ];
                    const sum = rubrics.reduce((a, b) => a + b, 0);
                    const avg = sum / rubrics.length;
                    this.computedScore = Math.round((avg / 5) * 100);
                } else {
                    this.computedScore = Math.min(100, Math.max(0, parseInt(this.scoreGeneral || 0)));
                }
            }
        }
    }
</script>
@endsection
