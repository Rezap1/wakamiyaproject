<?php
$baseDir = __DIR__;

// --- Update AcademicDashboardController ---
$ctrl = <<<PHP
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AcademicDashboardController extends Controller
{
    public function index()
    {
        \$kpi = [
            'programs' => 4,
            'classes' => 12,
            'teachers' => 25,
            'students' => 350,
            'attendance_rate' => '94%',
            'average_score' => '82.5',
            'submission_rate' => '88%',
        ];

        \$todayClasses = [
            ['time' => '08:00 - 09:30', 'subject' => 'Software Engineering A', 'teacher' => 'TCH-001 - Budi'],
            ['time' => '10:00 - 11:30', 'subject' => 'Database Design B', 'teacher' => 'TCH-002 - Siti'],
        ];

        \$pendingReviews = [
            ['title' => 'UML Diagram Task', 'class' => 'SE A', 'count' => 25],
            ['title' => 'ERD Quiz', 'class' => 'DB Design B', 'count' => 30],
        ];

        \$activities = [
            ['time' => '10 mins ago', 'message' => 'Teacher Budi published scores for SE A'],
            ['time' => '1 hour ago', 'message' => 'New student enrolled in Batch 2026'],
        ];

        return view('dashboard.academic', compact('kpi', 'todayClasses', 'pendingReviews', 'activities'));
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Core/AcademicDashboardController.php", $ctrl);

// --- Update Academic Dashboard View ---
$view = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Academic Operations Center')
@section('content')

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Programs</h4>
        <p class="text-xl font-black text-blue-700 mt-1">{{ $kpi['programs'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-indigo-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Classes</h4>
        <p class="text-xl font-black text-indigo-700 mt-1">{{ $kpi['classes'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Teachers</h4>
        <p class="text-xl font-black text-green-700 mt-1">{{ $kpi['teachers'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-teal-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Students</h4>
        <p class="text-xl font-black text-teal-700 mt-1">{{ $kpi['students'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Attendance</h4>
        <p class="text-xl font-black text-purple-700 mt-1">{{ $kpi['attendance_rate'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-orange-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Avg Score</h4>
        <p class="text-xl font-black text-orange-700 mt-1">{{ $kpi['average_score'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-slate-800">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Submission</h4>
        <p class="text-xl font-black text-slate-800 mt-1">{{ $kpi['submission_rate'] }}</p>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white p-6 rounded-2xl shadow mb-6">
    <h3 class="font-bold text-lg mb-4">Quick Actions</h3>
    <div class="flex flex-wrap gap-4">
        <a href="{{ route('subjects.create') }}" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition font-bold text-sm">Create Subject</a>
        <a href="{{ route('schedules.create') }}" class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition font-bold text-sm">Create Schedule</a>
        <a href="{{ route('announcements.create') }}" class="px-4 py-2 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition font-bold text-sm">Create Announcement</a>
        <a href="{{ route('attendances.index') }}" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition font-bold text-sm">Open Attendance</a>
        <a href="{{ route('academic.reports') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition font-bold text-sm">Open Reports</a>
    </div>
</div>

<!-- Widgets -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Today's Classes -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2">Today's Classes</h3>
        <ul class="space-y-4">
            @foreach($todayClasses as $c)
            <li class="flex items-start gap-4">
                <div class="bg-blue-50 text-blue-700 p-2 rounded-lg font-bold text-xs shrink-0">{{ $c['time'] }}</div>
                <div>
                    <p class="font-bold text-slate-700 text-sm">{{ $c['subject'] }}</p>
                    <p class="text-xs text-slate-500">{{ $c['teacher'] }}</p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    
    <!-- Pending Submission Review -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2 flex items-center justify-between">
            Pending Review
            <span class="bg-orange-100 text-orange-600 text-xs px-2 py-1 rounded font-bold">{{ count($pendingReviews) }} Batches</span>
        </h3>
        <ul class="space-y-4">
            @foreach($pendingReviews as $p)
            <li class="flex items-start gap-3 bg-orange-50 p-3 rounded-xl border border-orange-100">
                <div class="w-8 h-8 rounded bg-orange-200 text-orange-700 flex items-center justify-center font-bold text-xs shrink-0">{{ $p['count'] }}</div>
                <div>
                    <p class="font-bold text-slate-800 text-sm">{{ $p['title'] }}</p>
                    <p class="text-xs text-slate-500">Class: {{ $p['class'] }}</p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2">Recent Activity</h3>
        <ul class="space-y-4">
            @foreach($activities as $a)
            <li class="flex items-start gap-3">
                <div class="mt-1.5 w-2 h-2 rounded-full bg-blue-500 shrink-0"></div>
                <div>
                    <p class="text-sm font-medium text-slate-700">{{ $a['message'] }}</p>
                    <p class="text-[10px] text-gray-400 mt-1">{{ $a['time'] }}</p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>

</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/dashboard/academic.blade.php", $view);

echo "Dashboard Academic updated.\\n";
