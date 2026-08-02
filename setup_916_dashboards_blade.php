<?php

$studentBlade = <<<'EOT'
@extends('layouts.app')
@section('header', 'Student Portal - LPK Japan')

@section('content')
<div class="space-y-6">

    <x-page-header title="Welcome, {{ auth()->user()->name ?? 'Student' }}" description="Track your Japan dispatch progress and academic performance." :breadcrumbs="['Dashboard' => '#']" />

    <!-- LPK Progress Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Language Progress -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Japanese Language</h4>
                <div class="mt-2 text-2xl font-black text-blue-600">{{ $langProgress ?? 0 }}%</div>
            </div>
            <div class="mt-4 w-full bg-slate-100 rounded-full h-1.5"><div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $langProgress ?? 0 }}%"></div></div>
        </div>
        <!-- Internal Exams -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg Score</h4>
                <div class="mt-2 text-2xl font-black text-emerald-600">{{ round($myScores->avg('Score_Value') ?? 0, 1) }}</div>
            </div>
        </div>
        <!-- Scores count -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Tests</h4>
                <div class="mt-2 text-2xl font-black text-amber-600">{{ $myScores->count() }}</div>
            </div>
        </div>
    </div>

    <!-- Official Examination Center -->
    <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mt-8 mb-4">Official Examination Center</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($internals as $i)
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition relative overflow-hidden group">
            <h3 class="font-bold text-slate-800 text-sm mb-1">{{ $i['name'] }}</h3>
            <p class="text-3xl font-black text-{{ $i['color'] }}-600 mt-2">{{ $i['score'] }}</p>
            <div class="mt-4"><span class="px-2.5 py-1 bg-{{ $i['color'] }}-100 text-{{ $i['color'] }}-700 text-[10px] font-bold rounded-lg uppercase">{{ $i['status'] }}</span></div>
        </div>
        @endforeach
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/dashboard/student.blade.php', $studentBlade);

$reportsBlade = <<<'EOT'
@extends('layouts.app')
@section('header', 'Analytics & Performance Dashboard')

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('content')

<!-- Global Filters -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
    <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4">
        <h2 class="text-lg font-black text-slate-800 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Analytics Filters
        </h2>
    </div>
</div>

<!-- Enterprise KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <a href="{{ route('assessments.index') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:-translate-y-1 transition-all duration-200">
        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative z-10">Total Assessment</h4>
        <p class="text-2xl font-black text-blue-600 mt-1 relative z-10">{{ $assessments->count() }}</p>
    </a>
    <a href="{{ route('scores.index') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:-translate-y-1 transition-all duration-200">
        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative z-10">Total Scores</h4>
        <p class="text-2xl font-black text-indigo-600 mt-1 relative z-10">{{ $scores->count() }}</p>
    </a>
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-all duration-200">
        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative z-10">Passing Students</h4>
        <p class="text-2xl font-black text-emerald-600 mt-1 relative z-10">{{ $passed ?? 0 }}</p>
    </div>
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-all duration-200">
        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative z-10">Failed Students</h4>
        <p class="text-2xl font-black text-red-600 mt-1 relative z-10">{{ $failed ?? 0 }}</p>
    </div>
</div>

<!-- Chart Section -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    
    <!-- Chart 2: Category Distribution (Doughnut) -->
    <a href="{{ route('assessments.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Assessment Categories</h3>
        <div class="relative h-48 w-full flex justify-center">
            <canvas id="categoryChart"></canvas>
        </div>
    </a>

    <!-- Chart 3: Pass vs Fail (Pie) -->
    <a href="{{ route('scores.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Pass vs Fail</h3>
        <div class="relative h-48 w-full flex justify-center">
            <canvas id="passFailChart"></canvas>
        </div>
    </a>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#64748b'; // slate-500
    
    const catData = @json($chartData['categories'] ?? []);
    const catLabels = @json($chartData['category_labels'] ?? []);
    if(catData.length > 0) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{ data: catData, backgroundColor: ['#6366f1', '#10b981', '#a855f7', '#06b6d4', '#f59e0b'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });
    }

    const pfData = @json($chartData['pass_fail'] ?? [0,0]);
    new Chart(document.getElementById('passFailChart'), {
        type: 'pie',
        data: {
            labels: ['Pass', 'Fail'],
            datasets: [{ data: pfData, backgroundColor: ['#10b981', '#ef4444'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

});
</script>
@endsection
EOT;
file_put_contents('resources/views/academic/admin/reports.blade.php', $reportsBlade);

echo "Dashboards blade templates updated.\n";
?>
