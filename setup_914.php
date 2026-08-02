<?php
$dir = 'resources/views/academic/scores';
if (!is_dir($dir)) mkdir($dir, 0777, true);

// 1. INDEX BLADE
$index = <<<'EOT'
@extends('layouts.app')

@section('header', 'Score Management')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Score & Grading Data" 
        description="Monitor student scores, grades, and passing statuses across all assessments."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Scores' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('scores.export') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-all shadow-sm mr-2">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </a>
            <a href="{{ route('scores.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Input Score
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="flex flex-1 flex-wrap items-center gap-3 w-full">
                <!-- Search -->
                <div class="relative w-full md:w-64">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" class="block w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-900" placeholder="Search student...">
                </div>
                
                <!-- Category Filter -->
                <select class="py-2 pl-3 pr-8 text-sm bg-white border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-600 font-medium">
                    <option value="">All Categories</option>
                    @foreach(config('assessment.categories') as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>

                <!-- Program Filter -->
                <select class="py-2 pl-3 pr-8 text-sm bg-white border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-600 font-medium hidden lg:block">
                    <option value="">All Programs</option>
                    <option value="PRG-01">Software Eng</option>
                    <option value="PRG-02">Language</option>
                </select>

                <!-- Class Filter -->
                <select class="py-2 pl-3 pr-8 text-sm bg-white border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-600 font-medium hidden lg:block">
                    <option value="">All Classes</option>
                    <option value="CLS-01">Class A</option>
                </select>
                
                <button class="px-3 py-2 text-sm font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-900 transition-colors flex items-center shrink-0">
                    More Filters
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Assessment / Teacher</th>
                        <th class="px-6 py-4 text-center">Score</th>
                        <th class="px-6 py-4 text-center">Grade</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Exam Date</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @php
                        $mocks = [
                            ['id' => 1, 'student' => 'Budi Santoso', 'ass' => 'Mid Test - N4', 'cat' => 'Mid Test', 'teacher' => 'Tanaka Sensei', 'score' => 92, 'date' => '12 Oct 2026'],
                            ['id' => 2, 'student' => 'Andi Wijaya', 'ass' => 'JLPT Simulation', 'cat' => 'JLPT', 'teacher' => 'Sato Sensei', 'score' => 60, 'date' => '14 Oct 2026'],
                            ['id' => 3, 'student' => 'Siti Aminah', 'ass' => 'Daily Kanji', 'cat' => 'Daily Quiz', 'teacher' => 'Yamada Sensei', 'score' => 45, 'date' => '15 Oct 2026'],
                        ];
                    @endphp
                    @forelse($mocks as $item)
                        @php
                            $result = \App\Helpers\GradeHelper::calculate($item['score']);
                            $catBadge = match($item['cat']) {
                                'Placement Test' => 'bg-indigo-100 text-indigo-700',
                                'Daily Quiz' => 'bg-emerald-100 text-emerald-700',
                                'Mid Test' => 'bg-purple-100 text-purple-700',
                                'JLPT' => 'bg-amber-100 text-amber-700',
                                default => 'bg-blue-100 text-blue-700',
                            };
                            $statusBadge = $result['pass'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700';
                            $statusText = $result['pass'] ? 'PASS' : 'FAIL';
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-500">
                                        {{ substr($item['student'], 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 block">{{ $item['student'] }}</span>
                                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">ID: STU-00{{ $item['id'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-700 text-sm">{{ $item['ass'] }}</span>
                                        <span class="{{ $catBadge }} px-1.5 py-0.5 text-[9px] font-extrabold rounded uppercase tracking-wider">{{ $item['cat'] }}</span>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $item['teacher'] }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-black text-slate-800 text-lg block">{{ $item['score'] }}</span>
                                <span class="text-[10px] font-bold text-slate-400">{{ $result['percentage'] }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="w-8 h-8 mx-auto rounded-lg {{ $result['pass'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-600 border border-red-200' }} flex items-center justify-center font-black text-lg">
                                    {{ $result['grade'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="{{ $statusBadge }} px-2.5 py-1 text-[11px] font-bold rounded-lg">{{ $statusText }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs text-slate-500 font-medium">{{ $item['date'] }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ route('scores.show', $item['id']) }}" class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('scores.edit', $item['id']) }}" class="p-2 text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-600 mb-1">No Scores Found</h3>
                                    <p class="text-xs text-slate-400">There are no score records matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Showing 1 to 3 of 3 entries</span>
            <div class="flex items-center gap-1">
                <button class="px-3 py-1 text-sm font-semibold text-slate-400 bg-white border border-slate-200 rounded-lg cursor-not-allowed">Prev</button>
                <button class="px-3 py-1 text-sm font-bold text-white bg-blue-600 rounded-lg shadow-sm shadow-blue-200">1</button>
                <button class="px-3 py-1 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/index.blade.php", $index);

// 2. CREATE BLADE
$create = <<<'EOT'
@extends('layouts.app')

@section('header', 'Input Score')

@section('content')
<div class="space-y-6" x-data="scoreEngine()">
    <x-page-header 
        title="Input Assessment Score" 
        description="Record student score. Grade and Pass status are calculated automatically."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Scores' => route('scores.index'), 'Create' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('scores.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-form-section title="Score Details" description="Select the assessment, student, and input their final score.">
                <form action="{{ route('scores.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment</label>
                            <select name="Assessment_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-semibold">
                                <option value="ASM-001">ASM-001 - Midterm Exam</option>
                                <option value="ASM-002">ASM-002 - Daily Quiz</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Student</label>
                            <select name="Student_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-semibold">
                                <option value="STU-001">STU-001 - Budi Santoso</option>
                                <option value="STU-002">STU-002 - Andi Wijaya</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Final Score</label>
                            <input type="number" name="Score" x-model="score" @input="calculateGrade()" class="w-full bg-white border-2 border-blue-200 text-blue-900 text-xl font-black rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors text-center" placeholder="0 - 100" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Grade (Auto)</label>
                            <input type="text" x-model="grade" class="w-full bg-slate-100 border border-slate-200 text-slate-600 text-xl font-black rounded-xl block p-3 text-center cursor-not-allowed" readonly tabindex="-1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status (Auto)</label>
                            <input type="text" x-model="statusText" :class="statusText === 'PASS' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (statusText === 'FAIL' ? 'text-red-600 bg-red-50 border-red-200' : 'text-slate-500 bg-slate-100 border-slate-200')" class="w-full border text-sm font-black uppercase rounded-xl block p-4 text-center cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Teacher / Evaluator</label>
                            <input type="text" name="Teacher_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="e.g. Tanaka Sensei">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Exam Date</label>
                            <input type="date" name="Exam_Date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Notes / Remark</label>
                        <textarea name="Notes" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Optional comments on student performance..."></textarea>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                            Save Score
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
        
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-indigo-50 rounded-2xl p-6 border border-indigo-100 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-24 h-24 bg-indigo-100/50 rounded-bl-full -mr-8 -mt-8"></div>
                <h3 class="text-sm font-bold text-indigo-900 mb-4 flex items-center relative z-10">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Grading System
                </h3>
                <div class="space-y-3 relative z-10">
                    <p class="text-xs font-medium text-indigo-700">The grade is dynamically calculated based on <code>config/assessment.php</code> rules.</p>
                    <div class="bg-white/60 rounded-xl p-3 border border-indigo-100">
                        <ul class="text-[11px] font-bold text-indigo-800 space-y-1">
                            @foreach(config('assessment.grades') as $g => $range)
                                <li class="flex justify-between"><span>{{ $g }}</span> <span>{{ $range['min'] }} - {{ $range['max'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="bg-emerald-100/80 rounded-xl p-3 border border-emerald-200 flex justify-between items-center text-emerald-800 text-xs font-bold">
                        <span>Passing Minimum</span>
                        <span class="text-lg font-black">{{ config('assessment.passing_score') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function scoreEngine() {
        return {
            score: '',
            grade: '-',
            statusText: '-',
            configGrades: @json(config('assessment.grades')),
            passingScore: {{ config('assessment.passing_score', 65) }},
            
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
EOT;
file_put_contents("$dir/create.blade.php", $create);

// 3. EDIT BLADE
$edit = <<<'EOT'
@extends('layouts.app')

@section('header', 'Edit Score')

@section('content')
<div class="space-y-6" x-data="scoreEngine()">
    <x-page-header 
        title="Edit Assessment Score" 
        description="Update student score. Grade and Pass status are calculated automatically."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Scores' => route('scores.index'), 'Edit' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('scores.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $result = \App\Helpers\GradeHelper::calculate($score['Score_Value'] ?? 85);
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-form-section title="Update Details" description="Modify the student's score. The Assessment Code is locked.">
                <form action="{{ route('scores.update', $score['Score_ID'] ?? 1) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment</label>
                            <input type="text" name="Assessment_ID" class="w-full bg-slate-100 border border-slate-200 text-slate-500 text-sm font-bold rounded-xl block p-3 cursor-not-allowed" value="{{ $score['Assessment_ID'] ?? 'ASM-001' }}" readonly>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Student</label>
                            <input type="text" name="Student_ID" class="w-full bg-slate-100 border border-slate-200 text-slate-500 text-sm font-bold rounded-xl block p-3 cursor-not-allowed" value="{{ $score['Student_ID'] ?? 'STU-001' }}" readonly>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Final Score</label>
                            <input type="number" name="Score" x-model="score" @input="calculateGrade()" class="w-full bg-white border-2 border-blue-200 text-blue-900 text-xl font-black rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors text-center" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Grade (Auto)</label>
                            <input type="text" x-model="grade" class="w-full bg-slate-100 border border-slate-200 text-slate-600 text-xl font-black rounded-xl block p-3 text-center cursor-not-allowed" readonly tabindex="-1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status (Auto)</label>
                            <input type="text" x-model="statusText" :class="statusText === 'PASS' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (statusText === 'FAIL' ? 'text-red-600 bg-red-50 border-red-200' : 'text-slate-500 bg-slate-100 border-slate-200')" class="w-full border text-sm font-black uppercase rounded-xl block p-4 text-center cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Teacher / Evaluator</label>
                            <input type="text" name="Teacher_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" value="{{ $score['Teacher_ID'] ?? 'Tanaka Sensei' }}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Exam Date</label>
                            <input type="date" name="Exam_Date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Notes / Remark</label>
                        <textarea name="Notes" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors">{{ $score['Notes'] ?? '' }}</textarea>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-amber-500 rounded-xl hover:bg-amber-600 focus:ring-4 focus:ring-amber-300 transition-all shadow-md shadow-amber-200">
                            Update Score
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
    </div>
</div>

<script>
    function scoreEngine() {
        return {
            score: '{{ $score['Score_Value'] ?? 85 }}',
            grade: '{{ $result['grade'] }}',
            statusText: '{{ $result['pass'] ? 'PASS' : 'FAIL' }}',
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
EOT;
file_put_contents("$dir/edit.blade.php", $edit);

// 4. SHOW BLADE
$show = <<<'EOT'
@extends('layouts.app')

@section('header', 'Score Detail')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Score Profile & Report" 
        description="Detailed view of student examination result and grading metrics."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Scores' => route('scores.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('scores.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm mr-2">
                Back
            </a>
            <a href="{{ route('scores.edit', $score['Score_ID'] ?? 1) }}" class="px-4 py-2 text-sm font-bold text-white bg-amber-500 rounded-xl hover:bg-amber-600 focus:ring-4 focus:ring-amber-300 transition-colors shadow-sm shadow-amber-200">
                Edit Score
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $rawScore = $score['Score_Value'] ?? 92;
        $result = \App\Helpers\GradeHelper::calculate($rawScore);
        $statusBadge = $result['pass'] ? 'bg-emerald-500 shadow-emerald-200' : 'bg-red-500 shadow-red-200';
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Centerpiece: Score Card -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden text-center p-8 relative">
                <div class="absolute inset-x-0 top-0 h-24 {{ $statusBadge }} shadow-lg"></div>
                
                <div class="relative mt-8">
                    <div class="w-32 h-32 mx-auto bg-white rounded-full flex items-center justify-center border-8 border-slate-50 shadow-md">
                        <span class="text-5xl font-black text-slate-800">{{ $rawScore }}</span>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-center gap-4">
                    <div class="bg-slate-50 px-4 py-2 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Grade</p>
                        <p class="text-2xl font-black {{ $result['pass'] ? 'text-emerald-600' : 'text-red-600' }}">{{ $result['grade'] }}</p>
                    </div>
                    <div class="bg-slate-50 px-4 py-2 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Status</p>
                        <p class="text-lg font-black {{ $result['pass'] ? 'text-emerald-600' : 'text-red-600' }} uppercase mt-1">{{ $result['pass'] ? 'PASS' : 'FAIL' }}</p>
                    </div>
                </div>

                <div class="mt-8 border-t border-slate-100 pt-6">
                    <h3 class="font-bold text-slate-800">{{ $score['Student_ID'] ?? 'Budi Santoso' }}</h3>
                    <p class="text-xs font-semibold text-slate-500 mt-1">Student ID: STU-001</p>
                </div>
            </div>

            <!-- Timeline -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">Exam Timeline</h4>
                <div class="relative border-l-2 border-slate-100 ml-3 space-y-6">
                    <div class="relative">
                        <div class="absolute -left-[29px] bg-white border-4 border-slate-200 text-slate-500 w-14 h-14 rounded-full flex items-center justify-center font-bold text-[10px]">TAKEN</div>
                        <div class="ml-12 pt-1">
                            <p class="text-sm font-bold text-slate-800">{{ $score['Exam_Date'] ?? date('d M Y') }}</p>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[29px] bg-white border-4 border-emerald-100 text-emerald-500 w-14 h-14 rounded-full flex items-center justify-center font-bold text-[10px]">GRADED</div>
                        <div class="ml-12 pt-1">
                            <p class="text-sm font-bold text-emerald-600">Today</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Assessment Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Assessment Context
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Assessment Name</p>
                        <p class="font-bold text-slate-800 mt-1">{{ $score['Assessment_ID'] ?? 'Midterm Exam Fall' }}</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Category</p>
                        <p class="font-bold text-purple-600 mt-1">Mid Test</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Evaluator / Teacher</p>
                        <p class="font-bold text-slate-800 mt-1">{{ $score['Teacher_ID'] ?? 'Tanaka Sensei' }}</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Max Possible Score</p>
                        <p class="font-bold text-slate-800 mt-1">100</p>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Evaluator Notes / Remark</h4>
                <p class="text-sm font-semibold text-slate-700 leading-relaxed bg-amber-50 border border-amber-100 rounded-xl p-4 italic">
                    "{{ $score['Notes'] ?? 'Student showed excellent comprehension of the material but needs to improve speed.' }}"
                </p>
            </div>

            <!-- Integrations Placeholder -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl shadow-sm border border-blue-100 p-6">
                <h4 class="text-[11px] font-bold text-blue-400 uppercase tracking-wider mb-2">System Integrations</h4>
                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-white border border-blue-100 text-blue-700 text-xs font-bold rounded-lg shadow-sm">Sync to Report Card ✓</span>
                    <span class="px-3 py-1 bg-white border border-blue-100 text-blue-700 text-xs font-bold rounded-lg shadow-sm">Dashboard Updated ✓</span>
                    <span class="px-3 py-1 bg-white border border-blue-100 text-blue-700 text-xs font-bold rounded-lg shadow-sm opacity-50">Certificate (Pending)</span>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/show.blade.php", $show);

echo "Created 4 views in academic/scores.\n";
?>
