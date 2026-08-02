<?php

// Assessment - CREATE
$asmCreate = <<<'EOT'
@extends('layouts.app')
@section('header', 'Create Assessment')
@section('content')
<div class="space-y-6">
    <x-page-header title="Create New Assessment" description="Create a new assessment schedule and parameters." :breadcrumbs="['Dashboard' => '#', 'Assessments' => route('assessments.index'), 'Create' => '#']" />
    <form action="{{ route('assessments.store') }}" method="POST">
        @csrf
        <x-form-section title="Assessment Identity" description="Basic information about the assessment.">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Assessment Name</label>
                    <input type="text" name="Name" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Category</label>
                    <select name="Category" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach(config('assessment.categories') as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-form-section>
        <x-form-section title="Target Audience" description="Who is taking this assessment?">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Program</label>
                    <select name="Program_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($programs as $p)
                            <option value="{{ $p['Program_ID'] ?? '' }}">{{ $p['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Class</label>
                    <select name="Class_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($classes as $c)
                            <option value="{{ $c['Class_ID'] ?? '' }}">{{ $c['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Teacher</label>
                    <select name="Teacher_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($teachers as $t)
                            <option value="{{ $t['Teacher_ID'] ?? '' }}">{{ $t['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Exam Date</label>
                    <input type="date" name="Exam_Date" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md">Save Assessment</button>
            </div>
        </x-form-section>
    </form>
</div>
@endsection
EOT;
file_put_contents('resources/views/academic/assessments/create.blade.php', $asmCreate);

// Score - CREATE
$scoreCreate = <<<'EOT'
@extends('layouts.app')
@section('header', 'Input Score')
@section('content')
<div class="space-y-6" x-data="scoreEngine()">
    <x-page-header title="Input Assessment Score" description="Record student score. Grade and Pass status are calculated automatically." :breadcrumbs="['Dashboard' => '#', 'Scores' => route('scores.index'), 'Create' => '#']" />
    <form action="{{ route('scores.store') }}" method="POST">
        @csrf
        <x-form-section title="Score Details" description="Select the assessment, student, and input their final score.">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Assessment</label>
                    <select name="Assessment_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($assessments as $a)
                            <option value="{{ $a['Assessment_ID'] ?? '' }}">{{ $a['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Student</label>
                    <select name="Student_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($students as $s)
                            <option value="{{ $s['Student_ID'] ?? '' }}">{{ $s['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Final Score</label>
                    <input type="number" name="Score_Value" x-model="score" @input="calculateGrade()" class="w-full bg-white border-2 border-blue-200 text-blue-900 text-xl font-black rounded-xl p-3 text-center" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Grade (Auto)</label>
                    <input type="text" x-model="grade" class="w-full bg-slate-100 border border-slate-200 text-slate-600 text-xl font-black rounded-xl p-3 text-center cursor-not-allowed" readonly tabindex="-1">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md">Save Score</button>
            </div>
        </x-form-section>
    </form>
</div>
<script>
    function scoreEngine() {
        return {
            score: '', grade: '-', statusText: '-', configGrades: @json(config('assessment.grades')),
            passingScore: {{ config('assessment.passing_score', 65) }},
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
EOT;
file_put_contents('resources/views/academic/scores/create.blade.php', $scoreCreate);

// Score - INDEX
$scoreIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'Score Management')
@section('content')
<div class="space-y-6">
    <x-page-header title="Score Data" description="Monitor student scores." :breadcrumbs="['Dashboard' => '#', 'Scores' => '#']">
        <x-slot:actions>
            <a href="{{ route('scores.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl">Input Score</a>
        </x-slot:actions>
    </x-page-header>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr><th class="px-6 py-4">Student</th><th class="px-6 py-4 text-center">Score</th><th class="px-6 py-4 text-center">Grade</th><th class="px-6 py-4 text-center">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($scores as $item)
                        @php
                            $val = $item['Score_Value'] ?? 0;
                            $result = \App\Helpers\GradeHelper::calculate($val);
                        @endphp
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Student_ID'] ?? 'Unknown' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800 text-lg">{{ $val }}</td>
                            <td class="px-6 py-4 text-center font-bold">{{ $result['grade'] }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="{{ $result['pass'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $result['pass'] ? 'PASS' : 'FAIL' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('scores.show', $item['Score_ID'] ?? '1') }}" class="text-blue-600 font-bold text-xs hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No score records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/academic/scores/index.blade.php', $scoreIndex);

echo "Frontend CRUD updated successfully.\n";
?>
