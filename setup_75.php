<?php
$baseDir = __DIR__;

// --- 1. StudentWorkspaceController ---
$studentWorkspaceCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StudentWorkspaceController extends Controller
{
    private function getStudentId()
    {
        \$user = auth()->user();
        return \$user->Employee_ID ?? 'STU-001'; // Employee_ID usually holds the Student ID for students in WMS logic
    }

    public function mySchedule()
    {
        \$studentId = \$this->getStudentId();
        \$schedules = [
            ['day' => 'Senin', 'time' => '08:00 - 09:30', 'subject' => 'Software Engineering A', 'room' => 'Lab 1'],
            ['day' => 'Selasa', 'time' => '10:00 - 11:30', 'subject' => 'Database Design B', 'room' => 'Room 204'],
        ];
        return view('academic.student.schedule', compact('schedules', 'studentId'));
    }

    public function mySubjects()
    {
        \$studentId = \$this->getStudentId();
        \$subjects = [
            ['code' => 'SE101', 'name' => 'Software Engineering A', 'credits' => 3],
            ['code' => 'DB201', 'name' => 'Database Design B', 'credits' => 3],
        ];
        return view('academic.student.subjects', compact('subjects', 'studentId'));
    }

    public function progress()
    {
        \$studentId = \$this->getStudentId();
        \$progress = [
            'gpa' => '3.8',
            'attendance' => 95,
            'completed_credits' => 24,
            'status' => 'Excellent'
        ];
        return view('academic.student.progress', compact('progress', 'studentId'));
    }

    public function calendar()
    {
        \$studentId = \$this->getStudentId();
        \$events = [
            ['date' => '2026-07-21', 'title' => 'Midterm Exam - SE A', 'type' => 'Exam'],
            ['date' => '2026-07-22', 'title' => 'Assignment Deadline - DB Design', 'type' => 'Deadline'],
            ['date' => '2026-07-25', 'title' => 'School Event: IT Seminar', 'type' => 'Event'],
        ];
        return view('academic.student.calendar', compact('events', 'studentId'));
    }
}
PHP;
@mkdir("$baseDir/app/Http/Controllers/Academic", 0777, true);
file_put_contents("$baseDir/app/Http/Controllers/Academic/StudentWorkspaceController.php", $studentWorkspaceCtrl);

// --- 2. Views ---
@mkdir("$baseDir/resources/views/academic/student", 0777, true);

$scheduleView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'My Schedule')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Jadwal Kuliah Saya ({{ $studentId }})</h2>
    <table class="min-w-full">
        <tr class="bg-slate-100">
            <th class="p-3 text-left">Hari</th><th class="p-3 text-left">Waktu</th><th class="p-3 text-left">Mata Kuliah</th><th class="p-3 text-left">Ruang</th>
        </tr>
        @foreach($schedules as $s)
        <tr class="border-b">
            <td class="p-3 font-bold">{{ $s['day'] }}</td>
            <td class="p-3">{{ $s['time'] }}</td>
            <td class="p-3 text-blue-600 font-bold">{{ $s['subject'] }}</td>
            <td class="p-3">{{ $s['room'] }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/student/schedule.blade.php", $scheduleView);

$subjectsView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'My Subjects')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Mata Kuliah Saya ({{ $studentId }})</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($subjects as $s)
        <div class="p-4 border border-slate-200 rounded-xl">
            <h3 class="text-lg font-bold text-blue-700">{{ $s['name'] }}</h3>
            <p class="text-sm text-slate-500">Kode: {{ $s['code'] }} &bull; SKS: {{ $s['credits'] }}</p>
        </div>
        @endforeach
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/student/subjects.blade.php", $subjectsView);

$progressView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Academic Progress')
@section('content')
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-sm text-gray-500 font-bold">Current GPA</h4>
        <p class="text-2xl font-black text-blue-700">{{ $progress['gpa'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-sm text-gray-500 font-bold">Attendance</h4>
        <p class="text-2xl font-black text-green-700">{{ $progress['attendance'] }}%</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-sm text-gray-500 font-bold">Credits Completed</h4>
        <p class="text-2xl font-black text-purple-700">{{ $progress['completed_credits'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-orange-500">
        <h4 class="text-sm text-gray-500 font-bold">Academic Status</h4>
        <p class="text-xl font-black text-orange-700 mt-1">{{ $progress['status'] }}</p>
    </div>
</div>
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Export Rekapitulasi ({{ $studentId }})</h2>
    <div class="flex gap-4">
        <a href="{{ route('attendances.export') }}" class="px-4 py-2 bg-slate-800 text-white rounded">Download Kehadiran (CSV)</a>
        <a href="{{ route('scores.export') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Download Nilai (CSV)</a>
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/student/progress.blade.php", $progressView);

$calendarView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Academic Calendar')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-6">Jadwal & Deadline Saya ({{ $studentId }})</h2>
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
file_put_contents("$baseDir/resources/views/academic/student/calendar.blade.php", $calendarView);

echo "Student Workspace controllers and views created.\\n";
