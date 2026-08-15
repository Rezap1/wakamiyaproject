@extends('layouts.app')
@section('header', 'Edit Nilai & Evaluasi')
@section('content')

@php
    $category = strtoupper($score['Assessment_Category'] ?? 'GENERAL');
    $details = $score['Parsed_Details'] ?? [];
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="academicScoreEditEngine()">
    <x-universal.form 
        action="{{ route('scores.update', $score['Score_ID']) }}" 
        method="PUT"
        title="Edit Data Nilai & Evaluasi" 
        description="Perbarui metrik dan evaluasi siswa (ID: {{ $score['Score_ID'] }})."
        buttonText="Perbarui Data Nilai"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Informasi Referensi</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-universal.input 
                        name="Score_ID" 
                        label="ID Nilai" 
                        value="{{ $score['Score_ID'] }}"
                        readonly
                    />
                    <x-universal.input 
                        name="Assessment_ID" 
                        label="ID Assessment" 
                        value="{{ $score['Assessment_ID'] }}"
                        readonly
                    />
                    <x-universal.input 
                        name="Student_ID" 
                        label="ID Siswa" 
                        value="{{ $score['Student_ID'] }}"
                        readonly
                    />
                </div>
            </div>

            <!-- CATEGORY DISPLAY & INPUTS -->
            <div>
                <input type="hidden" name="Assessment_Category" value="{{ $category }}">
                
                <div class="mb-6 p-4 rounded-xl border flex items-center justify-between {{ $category === 'SPORTS' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : ($category === 'LANGUAGE' ? 'bg-purple-50 border-purple-200 text-purple-800' : 'bg-blue-50 border-blue-200 text-blue-800') }}">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider">Kategori Penilaian</span>
                        <h4 class="text-lg font-black mt-0.5">
                            @if($category === 'SPORTS') 🏀 OLAHRAGA (SPORTS)
                            @elseif($category === 'LANGUAGE') 🗣️ BAHASA (LANGUAGE)
                            @else 📚 AKADEMIK UMUM (GENERAL)
                            @endif
                        </h4>
                    </div>
                    <span class="px-3 py-1 text-xs font-bold rounded-lg bg-white shadow-sm border">Fixed Category</span>
                </div>

                <!-- CATEGORY 1: GENERAL ACADEMIC SCORE -->
                @if($category === 'GENERAL')
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nilai Akhir (0 - 100) <span class="text-rose-500 font-black">*</span></label>
                                <input type="number" name="Score_Value" x-model="scoreGeneral" @input="recalculate()" min="0" max="100" class="block w-full text-xl font-black text-center rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 px-4 py-2.5 shadow-sm">
                            </div>
                        </div>
                    </div>
                @endif

                <!-- CATEGORY 2: SPORTS EVALUATION METRICS -->
                @if($category === 'SPORTS')
                    <div class="space-y-6">
                        <h3 class="text-sm font-bold text-emerald-800 mb-4 border-b border-emerald-100 pb-2">Metrik Evaluasi Olahraga</h3>
                        <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Jarak Lari (km)</label>
                                <input type="number" step="0.1" min="0" name="running_distance" x-model="sports.running_distance" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Waktu Lari (menit)</label>
                                <input type="number" step="0.1" min="0" name="running_time" x-model="sports.running_time" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Push Up</label>
                                <input type="number" min="0" name="push_up" x-model="sports.push_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Sit Up</label>
                                <input type="number" min="0" name="sit_up" x-model="sports.sit_up" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                        </div>
                    </div>
                @endif

                <!-- CATEGORY 3: LANGUAGE EVALUATION RUBRICS -->
                @if($category === 'LANGUAGE')
                    <div class="space-y-6">
                        <h3 class="text-sm font-bold text-purple-800 mb-4 border-b border-purple-100 pb-2">Rubrik Evaluasi Kemampuan Bahasa</h3>
                        <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Speaking</label>
                                <input type="number" min="0" max="100" name="speaking" x-model="lang.speaking" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Writing</label>
                                <input type="number" min="0" max="100" name="writing" x-model="lang.writing" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Listening</label>
                                <input type="number" min="0" max="100" name="listening" x-model="lang.listening" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Reading</label>
                                <input type="number" min="0" max="100" name="reading" x-model="lang.reading" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Ethics</label>
                                <input type="number" min="0" max="100" name="ethics" x-model="lang.ethics" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Motivation</label>
                                <input type="number" min="0" max="100" name="motivation" x-model="lang.motivation" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Attendance</label>
                                <input type="number" min="0" max="100" name="attendance" x-model="lang.attendance" @input="recalculate()" class="w-full text-sm font-bold text-center rounded-xl border-slate-200">
                            </div>
                        </div>
                    </div>
                @endif

                <!-- SCORE SUMMARY PREVIEW CARD -->
                <div class="mt-6 bg-slate-900 text-white p-5 rounded-2xl flex items-center justify-between shadow-lg">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kalkulasi Skor Terkini</p>
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
                        label="Catatan Evaluator" 
                        value="{{ $score['Remarks'] ?? ($score['Notes'] ?? ($details['notes'] ?? '')) }}"
                    />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function academicScoreEditEngine() {
        return {
            category: '{{ $category }}',
            scoreGeneral: {{ $score['Score_Value'] ?? ($score['Score'] ?? 80) }},
            sports: {
                running_distance: {{ $details['running_distance'] ?? 0 }},
                running_time: {{ $details['running_time'] ?? 0 }},
                push_up: {{ $details['push_up'] ?? 0 }},
                sit_up: {{ $details['sit_up'] ?? 0 }}
            },
            lang: {
                speaking: {{ $details['speaking'] ?? 80 }},
                writing: {{ $details['writing'] ?? 80 }},
                listening: {{ $details['listening'] ?? 80 }},
                reading: {{ $details['reading'] ?? 80 }},
                ethics: {{ $details['ethics'] ?? 80 }},
                motivation: {{ $details['motivation'] ?? 80 }},
                attendance: {{ $details['attendance'] ?? 100 }}
            },
            computedScore: {{ $score['Score_Value'] ?? ($score['Score'] ?? 80) }},

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
