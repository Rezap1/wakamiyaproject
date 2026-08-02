@extends('layouts.app')
@section('header', 'Teacher Reports')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Export Data Akademik</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
            <h3 class="font-bold mb-2">Attendance Report</h3>
            <a href="{{ route('attendances.export') }}" class="px-4 py-2 bg-emerald-600 text-white rounded text-sm inline-block">Download CSV</a>
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



