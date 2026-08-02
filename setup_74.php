<?php
$baseDir = __DIR__;

// --- 1. TeacherControllers ---
$teacherClassesCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeacherWorkspaceController extends Controller
{
    // Helper to get Teacher ID from logged in user (Mock implementation for Phase 7.4)
    private function getTeacherId()
    {
        \$user = auth()->user();
        // Here we would ideally query MASTER_TEACHER where Employee_ID = \$user->Employee_ID
        // For now, we mock the return or return the Employee_ID
        return \$user->Employee_ID ?? 'TCH-001';
    }

    public function myClasses()
    {
        \$teacherId = \$this->getTeacherId();
        // Mock data for My Classes
        \$classes = [
            ['Class_Name' => 'Software Engineering A', 'Program' => 'IT', 'Batch' => '2026', 'Students' => 25],
            ['Class_Name' => 'Database Design B', 'Program' => 'IT', 'Batch' => '2026', 'Students' => 30],
        ];
        return view('academic.teacher.classes', compact('classes', 'teacherId'));
    }

    public function reports()
    {
        \$teacherId = \$this->getTeacherId();
        return view('academic.teacher.reports', compact('teacherId'));
    }

    public function calendar()
    {
        \$teacherId = \$this->getTeacherId();
        // Mock timeline events
        \$events = [
            ['date' => '2026-07-21', 'title' => 'Midterm Exam - SE A', 'type' => 'Exam'],
            ['date' => '2026-07-22', 'title' => 'Assignment Deadline - DB Design', 'type' => 'Deadline'],
            ['date' => '2026-07-25', 'title' => 'School Event: IT Seminar', 'type' => 'Event'],
        ];
        return view('academic.teacher.calendar', compact('events', 'teacherId'));
    }
}
PHP;
@mkdir("$baseDir/app/Http/Controllers/Academic", 0777, true);
file_put_contents("$baseDir/app/Http/Controllers/Academic/TeacherWorkspaceController.php", $teacherClassesCtrl);

// --- 2. Views ---
@mkdir("$baseDir/resources/views/academic/teacher", 0777, true);

$classesView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'My Classes')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Kelas Yang Saya Ampu ({{ $teacherId }})</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($classes as $c)
        <div class="p-4 border border-gray-200 rounded-xl hover:shadow-md transition">
            <h3 class="text-lg font-bold text-blue-700">{{ $c['Class_Name'] }}</h3>
            <p class="text-sm text-gray-500">{{ $c['Program'] }} - Batch {{ $c['Batch'] }}</p>
            <div class="mt-4 flex justify-between items-center">
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $c['Students'] }} Students</span>
                <a href="#" class="text-xs bg-blue-600 text-white px-3 py-1 rounded">Buka Kelas</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/teacher/classes.blade.php", $classesView);

$reportsView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Teacher Reports')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Export Data Akademik</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Attendance Report</h3>
            <a href="{{ route('attendances.export') }}" class="px-4 py-2 bg-blue-600 text-white rounded text-sm inline-block">Download CSV</a>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Score Report</h3>
            <a href="{{ route('scores.export') }}" class="px-4 py-2 bg-green-600 text-white rounded text-sm inline-block">Download CSV</a>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Student Progress</h3>
            <button class="px-4 py-2 bg-gray-400 text-white rounded text-sm inline-block cursor-not-allowed">PDF (Soon)</button>
        </div>
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/teacher/reports.blade.php", $reportsView);

$calendarView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Academic Calendar')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-6">Upcoming Events & Deadlines</h2>
    <div class="relative border-l-2 border-blue-500 pl-4 space-y-6">
        @foreach($events as $e)
        <div class="relative">
            <div class="absolute -left-5 top-1 w-3 h-3 rounded-full {{ $e['type']=='Exam'?'bg-red-500':($e['type']=='Deadline'?'bg-yellow-500':'bg-blue-500') }}"></div>
            <p class="text-sm text-gray-500 font-bold">{{ $e['date'] }}</p>
            <p class="font-medium">{{ $e['title'] }}</p>
            <span class="text-[10px] uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $e['type'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/teacher/calendar.blade.php", $calendarView);

echo "Teacher Workspace files created.\\n";
