<?php
$baseDir = __DIR__;

// --- Update TeacherDashboardController ---
$ctrl = <<<PHP
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        \$kpi = [
            'today_classes' => 3,
            'total_students' => 125,
            'pending_review' => 12,
            'attendance_rate' => '95%',
        ];

        \$todayClasses = [
            ['time' => '08:00 - 09:30', 'subject' => 'Software Engineering A', 'room' => 'Lab 1'],
            ['time' => '10:00 - 11:30', 'subject' => 'Database Design B', 'room' => 'Room 204'],
        ];

        \$activities = [
            ['time' => '10 mins ago', 'message' => 'John Doe submitted Assignment 1'],
            ['time' => '1 hour ago', 'message' => 'You published Midterm Scores for DB Design'],
        ];

        return view('dashboard.teacher', compact('kpi', 'todayClasses', 'activities'));
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Core/TeacherDashboardController.php", $ctrl);

// --- Update Teacher Dashboard View ---
$view = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Teacher Command Center')
@section('content')

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-sm text-gray-500 font-bold">Today's Classes</h4>
        <p class="text-2xl font-black text-blue-700">{{ $kpi['today_classes'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-sm text-gray-500 font-bold">Total Students</h4>
        <p class="text-2xl font-black text-green-700">{{ $kpi['total_students'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-orange-500">
        <h4 class="text-sm text-gray-500 font-bold">Pending Review</h4>
        <p class="text-2xl font-black text-orange-700">{{ $kpi['pending_review'] }} Submissions</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-sm text-gray-500 font-bold">Attendance Rate</h4>
        <p class="text-2xl font-black text-purple-700">{{ $kpi['attendance_rate'] }}</p>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white p-6 rounded-2xl shadow mb-6">
    <h3 class="font-bold text-lg mb-4">Quick Actions</h3>
    <div class="flex gap-4">
        <a href="{{ route('attendances.create') }}" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition font-bold">Open Attendance</a>
        <a href="{{ route('assignments.create') }}" class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition font-bold">Create Assignment</a>
        <a href="{{ route('scores.create') }}" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition font-bold">Input Score</a>
    </div>
</div>

<!-- Widgets -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Today's Classes -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2">Today's Schedule</h3>
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
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-bold text-lg mb-4 text-slate-800 border-b pb-2">Recent Activity</h3>
        <ul class="space-y-4">
            @foreach($activities as $a)
            <li class="flex items-start gap-3">
                <div class="mt-1 w-2 h-2 rounded-full bg-green-500"></div>
                <div>
                    <p class="text-sm font-medium">{{ $a['message'] }}</p>
                    <p class="text-[10px] text-gray-500">{{ $a['time'] }}</p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/dashboard/teacher.blade.php", $view);

echo "Dashboard Teacher updated.\\n";
