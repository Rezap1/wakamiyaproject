<?php
$baseDir = __DIR__;

// --- Update StudentDashboardController ---
$ctrl = <<<PHP
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index()
    {
        \$kpi = [
            'attendance' => '95%',
            'assignment_completed' => 12,
            'assignment_pending' => 2,
            'average_score' => 88.5,
            'current_grade' => 'B+',
        ];

        \$todayClasses = [
            ['time' => '08:00 - 09:30', 'subject' => 'Software Engineering A', 'room' => 'Lab 1'],
        ];
        
        \$deadlines = [
            ['date' => 'Today, 23:59', 'title' => 'UML Diagram Task', 'subject' => 'SE A'],
            ['date' => 'Tomorrow', 'title' => 'ERD Quiz', 'subject' => 'DB Design B'],
        ];

        \$activities = [
            ['time' => '2 hours ago', 'message' => 'Your submission for UML Task was marked as LATE.'],
            ['time' => 'Yesterday', 'message' => 'New assignment published for DB Design B.'],
        ];

        return view('dashboard.student', compact('kpi', 'todayClasses', 'deadlines', 'activities'));
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Core/StudentDashboardController.php", $ctrl);

// --- Update Student Dashboard View ---
$view = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Student Academic Portal')
@section('content')

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Attendance</h4>
        <p class="text-xl font-black text-blue-700 mt-1">{{ $kpi['attendance'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Done Task</h4>
        <p class="text-xl font-black text-green-700 mt-1">{{ $kpi['assignment_completed'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-orange-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Pending Task</h4>
        <p class="text-xl font-black text-orange-700 mt-1">{{ $kpi['assignment_pending'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Average Score</h4>
        <p class="text-xl font-black text-purple-700 mt-1">{{ $kpi['average_score'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-slate-800">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Current Grade</h4>
        <p class="text-xl font-black text-slate-800 mt-1">{{ $kpi['current_grade'] }}</p>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white p-6 rounded-2xl shadow mb-6">
    <h3 class="font-bold text-lg mb-4">Quick Actions</h3>
    <div class="flex gap-4">
        <a href="{{ route('assignments.index') }}" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition font-bold text-sm">Open Assignment</a>
        <a href="{{ route('submissions.create') }}" class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition font-bold text-sm">Upload Submission</a>
        <a href="{{ route('scores.index') }}" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition font-bold text-sm">View My Score</a>
    </div>
</div>

<!-- Widgets -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Today's Classes -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2">Today's Schedule</h3>
        @if(count($todayClasses) > 0)
        <ul class="space-y-4">
            @foreach($todayClasses as $c)
            <li class="flex items-center gap-4">
                <div class="bg-blue-50 text-blue-700 p-2 rounded-lg font-bold text-sm">{{ $c['time'] }}</div>
                <div>
                    <p class="font-bold text-slate-700">{{ $c['subject'] }}</p>
                    <p class="text-xs text-slate-500">{{ $c['room'] }}</p>
                </div>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-sm text-gray-500 italic">No classes today. Enjoy your day!</p>
        @endif
    </div>
    
    <!-- Upcoming Deadlines -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2 flex items-center justify-between">
            Upcoming Deadline
            <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded">{{ count($deadlines) }} Tasks</span>
        </h3>
        <ul class="space-y-4">
            @foreach($deadlines as $d)
            <li class="flex items-start gap-3 bg-red-50 p-3 rounded-xl border border-red-100">
                <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="font-bold text-slate-800">{{ $d['title'] }}</p>
                    <p class="text-xs font-bold text-red-600">{{ $d['date'] }} &bull; <span class="font-normal text-slate-500">{{ $d['subject'] }}</span></p>
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
file_put_contents("$baseDir/resources/views/dashboard/student.blade.php", $view);

echo "Dashboard Student updated.\\n";
