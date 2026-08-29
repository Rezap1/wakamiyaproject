@extends('layouts.app')
@section('header', 'Teacher Reports')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Export Data Akademik</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Attendance Report</h3>
            <div class="flex flex-wrap justify-center gap-2">
                <a href="{{ route('teacher.workspace.reports.attendances-pdf') }}" class="px-4 py-2 bg-red-600 text-white rounded text-sm inline-block">PDF</a>
                <a href="{{ route('teacher.workspace.reports.attendances-print') }}" target="_blank" class="px-4 py-2 bg-slate-700 text-white rounded text-sm inline-block">Cetak</a>
                <a href="{{ route('teacher.workspace.reports.attendances-csv') }}" class="px-4 py-2 bg-emerald-600 text-white rounded text-sm inline-block">CSV</a>
            </div>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Score Report</h3>
            <div class="flex flex-wrap justify-center gap-2">
                <a href="{{ route('teacher.workspace.reports.scores-pdf') }}" class="px-4 py-2 bg-red-600 text-white rounded text-sm inline-block">PDF</a>
                <a href="{{ route('teacher.workspace.reports.scores-print') }}" target="_blank" class="px-4 py-2 bg-slate-700 text-white rounded text-sm inline-block">Cetak</a>
                <a href="{{ route('teacher.workspace.reports.scores-csv') }}" class="px-4 py-2 bg-green-600 text-white rounded text-sm inline-block">CSV</a>
            </div>
        </div>
    </div>
</div>
@endsection



