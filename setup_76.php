<?php
$baseDir = __DIR__;

// --- 1. AcademicWorkspaceController ---
$academicWorkspaceCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AcademicWorkspaceController extends Controller
{
    public function reports()
    {
        // View for Academic Reports with filtering
        return view('academic.admin.reports');
    }

    public function calendar()
    {
        \$events = [
            ['date' => '2026-07-21', 'title' => 'Ujian Tengah Semester Ganjil', 'type' => 'Exam'],
            ['date' => '2026-07-28', 'title' => 'Deadline Input Nilai Guru', 'type' => 'Deadline'],
            ['date' => '2026-08-01', 'title' => 'Pembukaan Semester Baru', 'type' => 'Event'],
        ];
        return view('academic.admin.calendar', compact('events'));
    }
}
PHP;
@mkdir("$baseDir/app/Http/Controllers/Academic", 0777, true);
file_put_contents("$baseDir/app/Http/Controllers/Academic/AcademicWorkspaceController.php", $academicWorkspaceCtrl);

// --- 2. Views ---
@mkdir("$baseDir/resources/views/academic/admin", 0777, true);

$reportsView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Academic Reports')
@section('content')
<div class="bg-white rounded-2xl shadow p-6 mb-6">
    <h2 class="text-xl font-bold mb-4 border-b pb-2">Filter Laporan Akademik</h2>
    <form action="#" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Program</label>
            <select class="w-full border-gray-300 rounded-lg shadow-sm">
                <option>All Programs</option>
                <option>Software Engineering</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Batch</label>
            <select class="w-full border-gray-300 rounded-lg shadow-sm">
                <option>All Batches</option>
                <option>Batch 2026</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Class</label>
            <select class="w-full border-gray-300 rounded-lg shadow-sm">
                <option>All Classes</option>
                <option>SE-A</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Teacher</label>
            <select class="w-full border-gray-300 rounded-lg shadow-sm">
                <option>All Teachers</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Date Range</label>
            <input type="date" class="w-full border-gray-300 rounded-lg shadow-sm">
        </div>
        <div class="md:col-span-3 flex items-end">
            <button type="button" class="px-4 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 w-full md:w-auto">Apply Filter</button>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 text-center hover:shadow-md transition">
        <h3 class="font-bold text-lg text-slate-800 mb-2">Attendance Summary</h3>
        <p class="text-sm text-slate-500 mb-4">Rekap absen siswa dan guru berdasarkan filter.</p>
        <a href="{{ route('attendances.export') }}" class="px-4 py-2 bg-slate-800 text-white rounded-lg inline-block w-full">Export CSV</a>
    </div>
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 text-center hover:shadow-md transition">
        <h3 class="font-bold text-lg text-slate-800 mb-2">Score Summary</h3>
        <p class="text-sm text-slate-500 mb-4">Rekapitulasi nilai rata-rata dan grade.</p>
        <a href="{{ route('scores.export') }}" class="px-4 py-2 bg-slate-800 text-white rounded-lg inline-block w-full">Export CSV</a>
    </div>
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 text-center hover:shadow-md transition">
        <h3 class="font-bold text-lg text-slate-800 mb-2">Student Progress</h3>
        <p class="text-sm text-slate-500 mb-4">Transkrip ketuntasan akademik siswa (PDF).</p>
        <button class="px-4 py-2 bg-gray-400 text-white rounded-lg inline-block w-full cursor-not-allowed">Export PDF (Soon)</button>
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/admin/reports.blade.php", $reportsView);

$calendarView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Master Academic Calendar')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Jadwal Akademik Institusi</h2>
        <button class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm font-bold">+ Add Event</button>
    </div>
    <div class="relative border-l-2 border-blue-500 pl-4 space-y-6">
        @foreach($events as $e)
        <div class="relative group">
            <div class="absolute -left-5 top-1 w-3 h-3 rounded-full {{ $e['type']=='Exam'?'bg-red-500':($e['type']=='Deadline'?'bg-yellow-500':'bg-blue-500') }} ring-4 ring-white"></div>
            <p class="text-sm text-gray-500 font-bold">{{ $e['date'] }}</p>
            <p class="font-medium text-lg">{{ $e['title'] }}</p>
            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 mt-1 inline-block">{{ $e['type'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/admin/calendar.blade.php", $calendarView);

echo "Academic Workspace controllers and views created.\\n";
