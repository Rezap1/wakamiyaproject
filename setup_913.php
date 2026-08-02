<?php
$bladeContent = <<<'EOT'
@extends('layouts.app')
@section('header', 'Student Portal - LPK Japan')
@section('content')

    <!-- Page Header -->
    <x-page-header 
        title="Student Portal" 
        description="Pantau progres pendidikan, ujian, dokumen, dan keberangkatan Anda." 
        :breadcrumbs="['Dashboard' => '#']"
    />

    <!-- STUDENT PROGRESS -->
    <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4 mt-6">Student Progress</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Japanese Language -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 relative z-10">Japanese Language</h4>
            <div class="flex items-end gap-2 mb-3 relative z-10">
                <span class="text-3xl font-black text-slate-800">80</span>
                <span class="text-sm font-bold text-slate-500 mb-1">%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 mb-2 relative z-10">
                <div class="bg-blue-600 h-2 rounded-full" style="width: 80%"></div>
            </div>
            <p class="text-[10px] font-semibold text-slate-500 relative z-10">Target: JLPT N4 / JFT-Basic</p>
        </div>
        
        <!-- Documents -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 relative z-10">Documents</h4>
            <div class="flex items-end gap-2 mb-3 relative z-10">
                <span class="text-3xl font-black text-slate-800">60</span>
                <span class="text-sm font-bold text-slate-500 mb-1">%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 mb-2 relative z-10">
                <div class="bg-emerald-500 h-2 rounded-full" style="width: 60%"></div>
            </div>
            <p class="text-[10px] font-semibold text-slate-500 relative z-10">Processing COE</p>
        </div>

        <!-- Payment -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-purple-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 relative z-10">Payment</h4>
            <div class="flex items-end gap-2 mb-3 relative z-10">
                <span class="text-3xl font-black text-slate-800">100</span>
                <span class="text-sm font-bold text-slate-500 mb-1">%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 mb-2 relative z-10">
                <div class="bg-purple-500 h-2 rounded-full" style="width: 100%"></div>
            </div>
            <p class="text-[10px] font-semibold text-slate-500 relative z-10">Fully Paid</p>
        </div>

        <!-- Departure -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-amber-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 relative z-10">Departure</h4>
            <div class="flex items-end gap-2 mb-3 relative z-10">
                <span class="text-3xl font-black text-slate-800">30</span>
                <span class="text-sm font-bold text-slate-500 mb-1">%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 mb-2 relative z-10">
                <div class="bg-amber-500 h-2 rounded-full" style="width: 30%"></div>
            </div>
            <p class="text-[10px] font-semibold text-slate-500 relative z-10">Waiting for Visa</p>
        </div>
    </div>

    <!-- MAIN DASHBOARD GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- LEFT COLUMN: EXAMINATIONS -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- JAPANESE EXAMINATION CENTER -->
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Japanese Examination Center</h2>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="divide-y divide-slate-100">
                    
                    <!-- JLPT -->
                    <div class="p-6 hover:bg-slate-50/50 transition-colors">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0 border border-indigo-100">
                                    <span class="text-indigo-600 font-black">JLPT</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Japanese-Language Proficiency Test</h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase">Level: N4</span>
                                        <span class="text-xs text-slate-500">Dec 2026</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <div class="hidden md:block">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Score / Result</p>
                                    <p class="font-bold text-slate-800">- / -</p>
                                </div>
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-[11px] font-bold rounded-lg whitespace-nowrap">Scheduled</span>
                            </div>
                        </div>
                    </div>

                    <!-- JFT-Basic -->
                    <div class="p-6 hover:bg-slate-50/50 transition-colors">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0 border border-emerald-100">
                                    <span class="text-emerald-600 font-black">JFT</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">JFT-Basic (A2)</h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-semibold text-emerald-600">ID-24A-9921</span>
                                        <span class="text-xs text-slate-500">15 Oct 2026</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <div class="hidden md:block">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Score / Result</p>
                                    <p class="font-bold text-slate-800">220 <span class="text-emerald-500 text-sm">(Pass)</span></p>
                                </div>
                                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[11px] font-bold rounded-lg whitespace-nowrap">Passed</span>
                            </div>
                        </div>
                    </div>

                    <!-- SSW Examination -->
                    <div class="p-6 hover:bg-slate-50/50 transition-colors">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center shrink-0 border border-purple-100">
                                    <span class="text-purple-600 font-black">SSW</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Specified Skilled Worker Exam</h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-bold rounded uppercase">Kaigo (Caregiver)</span>
                                        <span class="text-xs text-slate-500">TBD</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <div class="hidden md:block">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Score / Result</p>
                                    <p class="font-bold text-slate-400">- / -</p>
                                </div>
                                <span class="px-3 py-1 bg-slate-100 text-slate-600 text-[11px] font-bold rounded-lg whitespace-nowrap">Waiting</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- INTERNAL EXAMINATION -->
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mt-8">Internal Examination</h2>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @php
                        $internals = [
                            ['name' => 'Placement Test', 'status' => 'Completed', 'score' => 85, 'color' => 'emerald'],
                            ['name' => 'Kaiwa Test 1', 'status' => 'Completed', 'score' => 90, 'color' => 'emerald'],
                            ['name' => 'Choukai Test 1', 'status' => 'Completed', 'score' => 75, 'color' => 'emerald'],
                            ['name' => 'Mid Test', 'status' => 'Scheduled', 'score' => null, 'color' => 'amber'],
                            ['name' => 'Dokkai Test 1', 'status' => 'Waiting', 'score' => null, 'color' => 'slate'],
                            ['name' => 'Sakubun Test', 'status' => 'Waiting', 'score' => null, 'color' => 'slate'],
                            ['name' => 'Kanji Test (N5)', 'status' => 'Waiting', 'score' => null, 'color' => 'slate'],
                            ['name' => 'Final Test', 'status' => 'Waiting', 'score' => null, 'color' => 'slate'],
                        ];
                    @endphp
                    
                    @foreach($internals as $exam)
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors">
                        <div>
                            <h4 class="font-bold text-sm text-slate-800">{{ $exam['name'] }}</h4>
                            <p class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ $exam['status'] }}</p>
                        </div>
                        <div class="text-right">
                            @if($exam['score'])
                                <span class="text-lg font-black text-{{ $exam['color'] }}-600">{{ $exam['score'] }}</span>
                            @else
                                <span class="text-sm font-bold text-slate-300">-</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: STATUS & NOTIFICATIONS -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- NOTIFICATION CENTER -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50/50 rounded-bl-full -mr-8 -mt-8"></div>
                <h3 class="font-bold text-lg text-slate-800 mb-4 relative z-10 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Notification Center
                </h3>
                <div class="space-y-4 relative z-10">
                    <div class="flex gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Jadwal JFT minggu depan</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Medical Check dijadwalkan</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Yesterday</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Tagihan JFT tersedia</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">2 days ago</p>
                        </div>
                    </div>
                </div>
                <button class="w-full mt-4 py-2 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">View All Notifications &rarr;</button>
            </div>

            <!-- FINANCIAL STATUS -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Financial Status</h3>
                <ul class="space-y-3">
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Registration</span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Paid</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Tuition Fee</span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Paid</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">JFT Fee</span>
                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded uppercase">Overdue</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Medical</span>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase">Waiting</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Visa & COE</span>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase">Waiting</span>
                    </li>
                </ul>
            </div>

            <!-- DOCUMENT PROGRESS -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-sm text-slate-800 uppercase tracking-widest mb-4">Document Progress</h3>
                <ul class="space-y-3">
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Passport</span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Completed</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Medical Check</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase">Processing</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">COE</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase">Processing</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Visa</span>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase">Waiting</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Contract</span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Completed</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600">Residence Card</span>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase">Waiting</span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
@endsection
EOT;
file_put_contents('resources/views/dashboard/student.blade.php', $bladeContent);
echo "Phase 9.1.3 student dashboard updated.\n";
?>
