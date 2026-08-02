<?php
$file = 'resources/views/academic/admin/reports.blade.php';

$bladeContent = <<<'EOT'
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
        <button type="button" class="px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
            Apply Filters
        </button>
    </div>
    <form action="#" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Program</label>
            <select class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <option>All Programs</option>
                <option>Software Engineering</option>
                <option>Caregiver (Kaigo)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Batch</label>
            <select class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <option>All Batches</option>
                <option>Batch 2026-A</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Class</label>
            <select class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <option>All Classes</option>
                <option>SE-Alpha</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Teacher / Evaluator</label>
            <select class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <option>All Teachers</option>
                <option>Tanaka Sensei</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Assessment Category</label>
            <select class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <option>All Categories</option>
                @foreach(config('assessment.categories', ['Placement Test', 'Mid Test', 'JLPT']) as $cat)
                    <option>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Student</label>
            <input type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Search by Name/ID...">
        </div>
        <div class="lg:col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Date Range</label>
            <div class="flex items-center gap-2">
                <input type="date" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                <span class="text-slate-400 font-bold">to</span>
                <input type="date" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl p-2.5 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
        </div>
    </form>
</div>

<!-- Enterprise KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    @php
        $kpis = [
            ['title' => 'Total Assessment', 'value' => '142', 'color' => 'blue', 'route' => route('assessments.index')],
            ['title' => 'Total Student', 'value' => '85', 'color' => 'indigo', 'route' => '#'],
            ['title' => 'Average Score', 'value' => '78.5', 'color' => 'emerald', 'route' => route('scores.index')],
            ['title' => 'Avg Attendance', 'value' => '94%', 'color' => 'purple', 'route' => route('attendances.index')],
            ['title' => 'JLPT Progress', 'value' => '65%', 'color' => 'amber', 'route' => '#'],
            ['title' => 'Passing Rate', 'value' => '88%', 'color' => 'emerald', 'route' => route('scores.index')],
            ['title' => 'Failed Student', 'value' => '12', 'color' => 'red', 'route' => route('scores.index')],
            ['title' => 'Upcoming Exam', 'value' => '5', 'color' => 'cyan', 'route' => route('assessments.index')],
            ['title' => 'Pending Task', 'value' => '24', 'color' => 'orange', 'route' => '#'],
            ['title' => 'Ready to Depart', 'value' => '18', 'color' => 'emerald', 'route' => '#'],
        ];
    @endphp

    @foreach($kpis as $k)
    <a href="{{ $k['route'] }}" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:-translate-y-1 transition-all duration-200 group relative overflow-hidden">
        <div class="absolute right-0 top-0 w-16 h-16 bg-{{ $k['color'] }}-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-125"></div>
        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative z-10">{{ $k['title'] }}</h4>
        <p class="text-2xl font-black text-{{ $k['color'] }}-600 mt-1 relative z-10">{{ $k['value'] }}</p>
    </a>
    @endforeach
</div>

<!-- Chart Section -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Chart 1: Average Score Trend (Line) -->
    <a href="{{ route('scores.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Average Score Trend</h3>
        <div class="relative h-48 w-full">
            <canvas id="scoreTrendChart"></canvas>
        </div>
    </a>

    <!-- Chart 2: Category Distribution (Doughnut) -->
    <a href="{{ route('assessments.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Assessment Categories</h3>
        <div class="relative h-48 w-full flex justify-center">
            <canvas id="categoryChart"></canvas>
        </div>
    </a>

    <!-- Chart 3: Pass vs Fail (Pie) -->
    <a href="{{ route('scores.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Pass vs Fail Ratio</h3>
        <div class="relative h-48 w-full flex justify-center">
            <canvas id="passFailChart"></canvas>
        </div>
    </a>

    <!-- Chart 4: Attendance Contribution (Bar) -->
    <a href="{{ route('attendances.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Attendance Rate / Class</h3>
        <div class="relative h-48 w-full">
            <canvas id="attendanceChart"></canvas>
        </div>
    </a>

    <!-- Chart 5: Japanese Exam Progress (Horizontal Bar) -->
    <a href="#" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 block">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Japan Exam Completion</h3>
        <div class="relative h-48 w-full">
            <canvas id="japanExamChart"></canvas>
        </div>
    </a>

    <!-- Chart 6: Student Readiness Pipeline (Progress) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300">
        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Readiness Pipeline</h3>
        <div class="space-y-4">
            @php
                $pipeline = [
                    ['name' => 'Language', 'p' => 85, 'c' => 'blue'],
                    ['name' => 'Documents', 'p' => 70, 'c' => 'emerald'],
                    ['name' => 'Medical', 'p' => 90, 'c' => 'purple'],
                    ['name' => 'Visa & COE', 'p' => 45, 'c' => 'amber'],
                ];
            @endphp
            @foreach($pipeline as $pipe)
            <div>
                <div class="flex justify-between text-[11px] font-bold uppercase tracking-wider mb-1">
                    <span class="text-slate-500">{{ $pipe['name'] }}</span>
                    <span class="text-{{ $pipe['c'] }}-600">{{ $pipe['p'] }}%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="bg-{{ $pipe['c'] }}-500 h-2 rounded-full" style="width: {{ $pipe['p'] }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- LPK Analytics (Monitoring Checklist) -->
<h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4 mt-8">LPK Operation Monitor (Macro View)</h2>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 overflow-x-auto">
    <table class="w-full text-left text-sm text-slate-600 min-w-[800px]">
        <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider border-b border-slate-100">
            <tr>
                <th class="px-6 py-4">Milestone</th>
                <th class="px-6 py-4">Total Students</th>
                <th class="px-6 py-4 w-1/3">Overall Progress</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-6 py-4 text-right">Target Completion</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @php
                $lpk = [
                    ['m' => 'JLPT Registration', 'ts' => 85, 'p' => 100, 's' => 'Completed', 'c' => 'emerald', 'd' => 'Sep 2026'],
                    ['m' => 'JFT-Basic Examination', 'ts' => 85, 'p' => 80, 's' => 'In Progress', 'c' => 'blue', 'd' => 'Oct 2026'],
                    ['m' => 'SSW Skill Test', 'ts' => 85, 'p' => 40, 's' => 'Waiting', 'c' => 'amber', 'd' => 'Nov 2026'],
                    ['m' => 'User Interview', 'ts' => 40, 'p' => 10, 's' => 'Scheduling', 'c' => 'purple', 'd' => 'Dec 2026'],
                    ['m' => 'Medical Check-Up', 'ts' => 85, 'p' => 95, 's' => 'Completed', 'c' => 'emerald', 'd' => 'Aug 2026'],
                    ['m' => 'Passport Processing', 'ts' => 85, 'p' => 100, 's' => 'Completed', 'c' => 'emerald', 'd' => 'Jul 2026'],
                    ['m' => 'COE Application', 'ts' => 40, 'p' => 25, 's' => 'Processing', 'c' => 'blue', 'd' => 'Jan 2027'],
                    ['m' => 'Visa Stamping', 'ts' => 40, 'p' => 0, 's' => 'Waiting COE', 'c' => 'slate', 'd' => 'Feb 2027'],
                    ['m' => 'Departure to Japan', 'ts' => 40, 'p' => 0, 's' => 'Scheduled', 'c' => 'slate', 'd' => 'Mar 2027'],
                ];
            @endphp
            @foreach($lpk as $item)
            <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $item['m'] }}</td>
                <td class="px-6 py-4 font-semibold">{{ $item['ts'] }} Pax</td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="bg-{{ $item['c'] }}-500 h-2 rounded-full" style="width: {{ $item['p'] }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-slate-500 w-8">{{ $item['p'] }}%</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2.5 py-1 bg-{{ $item['c'] }}-100 text-{{ $item['c'] }}-700 text-[10px] font-bold rounded-lg uppercase whitespace-nowrap">{{ $item['s'] }}</span>
                </td>
                <td class="px-6 py-4 text-right text-xs font-semibold text-slate-500">{{ $item['d'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#64748b'; // slate-500
    
    // Common options for clean enterprise look
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { border: { display: false }, grid: { color: '#f1f5f9' } },
            x: { border: { display: false }, grid: { display: false } }
        },
        animation: { duration: 300 }
    };

    // 1. Average Score Trend
    new Chart(document.getElementById('scoreTrendChart'), {
        type: 'line',
        data: {
            labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                data: [65, 70, 72, 78, 85, 88],
                borderColor: '#3b82f6', // blue-500
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: commonOptions
    });

    // 2. Category Distribution
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: ['Placement', 'Daily Quiz', 'Mid Test', 'Kaiwa', 'JLPT'],
            datasets: [{
                data: [15, 45, 10, 20, 10],
                backgroundColor: ['#6366f1', '#10b981', '#a855f7', '#06b6d4', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
            cutout: '70%', animation: { duration: 300 }
        }
    });

    // 3. Pass vs Fail
    new Chart(document.getElementById('passFailChart'), {
        type: 'pie',
        data: {
            labels: ['Pass (88%)', 'Fail (12%)'],
            datasets: [{
                data: [88, 12],
                backgroundColor: ['#10b981', '#ef4444'], // emerald, red
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            animation: { duration: 300 }
        }
    });

    // 4. Attendance
    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: {
            labels: ['Class A', 'Class B', 'Class C', 'Class D'],
            datasets: [{
                data: [95, 92, 88, 97],
                backgroundColor: '#8b5cf6', // purple
                borderRadius: 4
            }]
        },
        options: commonOptions
    });

    // 5. Japanese Exam Progress
    new Chart(document.getElementById('japanExamChart'), {
        type: 'bar',
        data: {
            labels: ['JLPT N4', 'JFT-Basic', 'SSW Kaigo'],
            datasets: [{
                data: [100, 80, 40],
                backgroundColor: '#f59e0b', // amber
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { border: { display: false }, grid: { color: '#f1f5f9' }, max: 100 },
                y: { border: { display: false }, grid: { display: false } }
            },
            animation: { duration: 300 }
        }
    });
});
</script>
@endsection
EOT;
file_put_contents($file, $bladeContent);
echo "Phase 9.1.5 analytics dashboard written to $file.\n";
?>
