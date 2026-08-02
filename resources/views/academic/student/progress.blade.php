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
        <a href="{{ route('scores.export') }}" class="px-4 py-2 bg-emerald-600 text-white rounded">Download Nilai (CSV)</a>
    </div>
</div>
@endsection



