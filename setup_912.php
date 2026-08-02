<?php

$dir = 'resources/views/academic/assessments';
if (!is_dir($dir)) mkdir($dir, 0777, true);

// 1. INDEX BLADE
$index = <<<'EOT'
@extends('layouts.app')

@section('header', 'Assessment Management')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Assessment Data" 
        description="Manage all assessments, quizzes, and exams across programs."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assessments' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('assessments.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Create Assessment
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" class="block w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 text-slate-900" placeholder="Search assessments...">
                </div>
                <button class="px-4 py-2 text-sm font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-900 focus:ring-4 focus:ring-slate-100 transition-colors flex items-center shrink-0">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Filter
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Assessment Info</th>
                        <th class="px-6 py-4">Category & Subject</th>
                        <th class="px-6 py-4">Target Class</th>
                        <th class="px-6 py-4 text-center">Score / Date</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $mocks = [
                            ['code' => 'ASM-001', 'name' => 'Midterm Exam Fall', 'cat' => 'Mid Test', 'sub' => 'Basic Japanese', 'class' => 'Batch 5 - Class A', 'max' => 100, 'date' => '12 Oct 2026', 'status' => 'Published'],
                            ['code' => 'ASM-002', 'name' => 'Kanji Reading Q1', 'cat' => 'Daily Quiz', 'sub' => 'Kanji N4', 'class' => 'Batch 5 - Class B', 'max' => 50, 'date' => '14 Oct 2026', 'status' => 'Closed'],
                            ['code' => 'ASM-003', 'name' => 'Interview Prep 1', 'cat' => 'Interview', 'sub' => 'Interview Skills', 'class' => 'Batch 4 - Class A', 'max' => 100, 'date' => '15 Oct 2026', 'status' => 'Draft'],
                        ];
                    @endphp
                    @forelse($assessments ?? $mocks as $item)
                        @php
                            $cat = $item['Category'] ?? $item['cat'];
                            $catBadge = match($cat) {
                                'Placement Test' => 'bg-indigo-100 text-indigo-700',
                                'Daily Quiz' => 'bg-emerald-100 text-emerald-700',
                                'Assignment' => 'bg-cyan-100 text-cyan-700',
                                'Mid Test' => 'bg-purple-100 text-purple-700',
                                'Final Test' => 'bg-rose-100 text-rose-700',
                                'Interview' => 'bg-amber-100 text-amber-700',
                                default => 'bg-blue-100 text-blue-700',
                            };
                            
                            $status = $item['Status'] ?? $item['status'];
                            $statusBadge = match($status) {
                                'Draft' => 'bg-slate-100 text-slate-700',
                                'Published' => 'bg-blue-100 text-blue-700',
                                'Closed' => 'bg-emerald-100 text-emerald-700',
                                'Archived' => 'bg-orange-100 text-orange-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-800 block">{{ $item['Assessment_Code'] ?? $item['code'] }}</span>
                                <span class="text-xs text-slate-500 font-medium mt-1">{{ $item['Assessment_Name'] ?? $item['name'] }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="{{ $catBadge }} px-2 py-0.5 text-[10px] font-extrabold rounded uppercase tracking-wide inline-block mb-1">{{ $cat }}</span>
                                <span class="block text-xs font-semibold text-slate-600">{{ $item['Subject_ID'] ?? $item['sub'] }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-slate-800 text-sm block">{{ $item['Class_ID'] ?? $item['class'] }}</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">{{ $item['Program_ID'] ?? 'Software Engineering' }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-bold text-slate-800 block text-lg">{{ $item['Max_Score'] ?? $item['max'] }}</span>
                                <span class="text-[11px] text-slate-500 font-medium mt-1">{{ $item['Assessment_Date'] ?? $item['date'] }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="{{ $statusBadge }} px-2.5 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ route('assessments.show', $item['Assessment_ID'] ?? 1) }}" class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('assessments.edit', $item['Assessment_ID'] ?? 1) }}" class="p-2 text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-600 mb-1">No Assessments Found</h3>
                                    <p class="text-xs text-slate-400">There are no assessment records matching your criteria.</p>
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

@section('header', 'Create Assessment')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Create Assessment" 
        description="Configure a new exam, quiz, or assignment."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assessments' => route('assessments.index'), 'Create' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('assessments.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-form-section title="Assessment Identity" description="Basic information regarding the test or assignment.">
                <form action="{{ route('assessments.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment Code</label>
                            <input type="text" name="Assessment_Code" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="e.g. ASM-004" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment Name</label>
                            <input type="text" name="Assessment_Name" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="e.g. Final Semester Exam" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Category</label>
                            <select name="Category" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                @foreach(config('assessment.categories', ['Daily Quiz', 'Mid Test', 'Final Test']) as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Maximum Score</label>
                            <input type="number" name="Max_Score" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" value="100" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Description</label>
                        <textarea name="Description" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Material covered, rules, or guidelines..."></textarea>
                    </div>

            </x-form-section>
            
            <x-form-section title="Target Audience" description="Specify which class and subject this assessment is for.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Program</label>
                            <select name="Program_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="PRG-01">Software Engineering</option>
                                <option value="PRG-02">Language Course</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Batch / Class</label>
                            <select name="Class_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="CLS-01">Batch 5 - Class A</option>
                                <option value="CLS-02">Batch 5 - Class B</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Subject</label>
                            <select name="Subject_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="SUB-01">Basic Japanese N5</option>
                                <option value="SUB-02">Web Development</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Teacher in Charge</label>
                            <input type="text" name="Teacher_ID" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" placeholder="Teacher Name or ID">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment Date</label>
                            <input type="date" name="Assessment_Date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status</label>
                            <select name="Status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="Draft">Draft</option>
                                <option value="Published">Published</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                            Create Assessment
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
        
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
                <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Scoring Strategy
                </h3>
                <ul class="text-xs font-medium text-blue-700 space-y-3">
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        Default passing score is configured at <strong>{{ config('assessment.passing_score', 65) }}</strong>.
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        Ensure maximum score aligns with grading metric (usually 100).
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span> 
                        <strong>Published</strong> status means teachers can start inputting scores for this assessment.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/create.blade.php", $create);

// 3. EDIT BLADE
$edit = <<<'EOT'
@extends('layouts.app')

@section('header', 'Edit Assessment')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Edit Assessment" 
        description="Update existing assessment configuration."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assessments' => route('assessments.index'), 'Edit' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('assessments.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm">
                Cancel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-form-section title="Update Details" description="Modify the parameters of this assessment.">
                <form action="{{ route('assessments.update', $assessment['Assessment_ID'] ?? 1) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment Code</label>
                            <input type="text" name="Assessment_Code" class="w-full bg-slate-100 border border-slate-200 text-slate-500 text-sm rounded-xl block p-3 font-semibold" value="{{ $assessment['Assessment_Code'] ?? 'ASM-001' }}" readonly>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Assessment Name</label>
                            <input type="text" name="Assessment_Name" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors" required value="{{ $assessment['Assessment_Name'] ?? 'Midterm Exam' }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Category</label>
                            <select name="Category" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                @foreach(config('assessment.categories', ['Daily Quiz', 'Mid Test', 'Final Test']) as $cat)
                                    <option value="{{ $cat }}" {{ ($assessment['Category'] ?? 'Mid Test') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Status</label>
                            <select name="Status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors font-medium">
                                <option value="Draft" {{ ($assessment['Status'] ?? '') == 'Draft' ? 'selected' : '' }}>Draft</option>
                                <option value="Published" {{ ($assessment['Status'] ?? 'Published') == 'Published' ? 'selected' : '' }}>Published</option>
                                <option value="Closed" {{ ($assessment['Status'] ?? '') == 'Closed' ? 'selected' : '' }}>Closed</option>
                                <option value="Archived" {{ ($assessment['Status'] ?? '') == 'Archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Description</label>
                        <textarea name="Description" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 transition-colors">{{ $assessment['Description'] ?? '' }}</textarea>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" class="px-6 py-3 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all shadow-md shadow-blue-200">
                            Update Assessment
                        </button>
                    </div>
                </form>
            </x-form-section>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/edit.blade.php", $edit);

// 4. SHOW BLADE
$show = <<<'EOT'
@extends('layouts.app')

@section('header', 'Assessment Detail')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Assessment Profile" 
        description="Comprehensive view of assessment configuration and related scores."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assessments' => route('assessments.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('assessments.index') }}" class="px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 focus:ring-4 focus:ring-slate-100 transition-colors shadow-sm mr-2">
                Back
            </a>
            <a href="{{ route('assessments.edit', $assessment['Assessment_ID'] ?? 1) }}" class="px-4 py-2 text-sm font-bold text-white bg-amber-500 rounded-xl hover:bg-amber-600 focus:ring-4 focus:ring-amber-300 transition-colors shadow-sm shadow-amber-200">
                Edit Assessment
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $cat = $assessment['Category'] ?? 'Mid Test';
        $catBadge = match($cat) {
            'Placement Test' => 'bg-indigo-100 text-indigo-700',
            'Daily Quiz' => 'bg-emerald-100 text-emerald-700',
            'Assignment' => 'bg-cyan-100 text-cyan-700',
            'Mid Test' => 'bg-purple-100 text-purple-700',
            'Final Test' => 'bg-rose-100 text-rose-700',
            'Interview' => 'bg-amber-100 text-amber-700',
            default => 'bg-blue-100 text-blue-700',
        };
        $status = $assessment['Status'] ?? 'Published';
        $statusBadge = match($status) {
            'Published' => 'bg-blue-100 text-blue-700',
            'Closed' => 'bg-emerald-100 text-emerald-700',
            'Archived' => 'bg-orange-100 text-orange-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Card 1: Assessment Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden text-center p-8 relative">
                <span class="{{ $statusBadge }} absolute top-4 right-4 px-2 py-1 text-[10px] font-extrabold rounded-md uppercase tracking-wide">{{ $status }}</span>
                
                <div class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 mx-auto mb-4 flex items-center justify-center text-slate-400">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                </div>
                <h3 class="text-xl font-extrabold text-slate-800">{{ $assessment['Assessment_Name'] ?? 'Midterm Exam' }}</h3>
                <p class="text-xs font-bold text-slate-500 mt-1 mb-4">{{ $assessment['Assessment_Code'] ?? 'ASM-001' }}</p>
                
                <span class="{{ $catBadge }} inline-block px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide">
                    {{ $cat }}
                </span>
            </div>

            <!-- Card 2 & 3: Subject & Teacher Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">Target Integration</h4>
                <ul class="space-y-4">
                    <li class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Subject</p>
                            <p class="text-sm font-semibold text-slate-800">{{ $assessment['Subject_ID'] ?? 'Basic Japanese N4' }}</p>
                        </div>
                    </li>
                    <li class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Teacher</p>
                            <p class="text-sm font-semibold text-slate-800">{{ $assessment['Teacher_ID'] ?? 'Sensei Tanaka' }}</p>
                        </div>
                    </li>
                    <li class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Class / Batch</p>
                            <p class="text-sm font-semibold text-slate-800">{{ $assessment['Class_ID'] ?? 'Batch 5 - Class A' }}</p>
                        </div>
                    </li>
                </ul>
            </div>
            
            <!-- Card 5: Timeline -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">Event Timeline</h4>
                <div class="relative border-l-2 border-slate-100 ml-3 space-y-6">
                    <div class="relative">
                        <div class="absolute -left-[29px] bg-white border-4 border-slate-200 text-slate-500 w-14 h-14 rounded-full flex items-center justify-center font-bold text-[10px]">CREATED</div>
                        <div class="ml-12 pt-1">
                            <p class="text-sm font-bold text-slate-800">{{ $assessment['Created_At'] ?? date('d M Y') }}</p>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[29px] bg-white border-4 border-blue-100 text-blue-500 w-14 h-14 rounded-full flex items-center justify-center font-bold text-[10px]">HELD ON</div>
                        <div class="ml-12 pt-1">
                            <p class="text-sm font-bold text-blue-600">{{ $assessment['Assessment_Date'] ?? date('d M Y', strtotime('+2 days')) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Card 4: Statistics Placeholder -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Max Score</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2">{{ $assessment['Max_Score'] ?? 100 }}</h3>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pass Rate</p>
                    <h3 class="text-3xl font-extrabold text-emerald-500 mt-2">85%</h3>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg Score</p>
                    <h3 class="text-3xl font-extrabold text-blue-500 mt-2">78</h3>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Participants</p>
                    <h3 class="text-3xl font-extrabold text-purple-500 mt-2">24</h3>
                </div>
            </div>

            <!-- Card 6: Related Scores Preview -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Student Score Summary
                    </h3>
                    <a href="{{ route('scores.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700">View All Scores &rarr;</a>
                </div>
                <div class="p-6">
                    <div class="bg-slate-50 rounded-xl border border-slate-200 border-dashed p-8 text-center">
                        <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <h4 class="text-sm font-bold text-slate-600 mb-1">Score Data Pending</h4>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">This section will display real-time student scores, passing status, and grade distribution once the Score Module is fully integrated in Phase 9.1.3.</p>
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Description / Guidelines</h4>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $assessment['Description'] ?? 'No special instructions or descriptions provided for this assessment.' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/show.blade.php", $show);

echo "Created 4 views in academic/assessments.\n";
?>
