@extends('layouts.app')
@section('header', 'Academic Progress')
@section('content')
@php
    $assessmentConfigMap = collect($assessmentConfigs ?? []);
@endphp
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-sm text-gray-500 font-bold">Rata-Rata Nilai</h4>
        <p class="text-2xl font-black text-blue-700">{{ is_numeric($progress['gpa']) ? $progress['gpa'] : 'N/A' }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-sm text-gray-500 font-bold">Kehadiran</h4>
        <p class="text-2xl font-black text-green-700">{{ $progress['attendance'] }}%</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-sm text-gray-500 font-bold">Total Penilaian</h4>
        <p class="text-2xl font-black text-purple-700">{{ $progress['total_assessments'] }}</p>
    </div>
</div>

<div class="mt-6">
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Riwayat Nilai</h3>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('student.export-scores-pdf') }}" class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-bold">PDF</a>
                <a href="{{ route('student.print-scores') }}" target="_blank" class="text-xs px-3 py-1 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors font-bold">Cetak</a>
                <a href="{{ route('student.export-scores') }}" class="text-xs px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors font-bold">CSV</a>
            </div>
        </div>
        <div class="p-4 max-h-[600px] overflow-y-auto space-y-4">
            @forelse($myScores as $score)
            @php
                $catRaw = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
                $config = $assessmentConfigMap[$catRaw] ?? null;
                $catLabel = $config ? ($config['Category_Name'] ?? $catRaw) : ($catRaw ?: 'Tidak dikategorikan');
                $type = strtoupper(trim((string) ($config['Score_Type'] ?? $config['ScoreType'] ?? $config['Input_Type'] ?? '')));
                $isNumeric = in_array($type, ['NUMERIC', 'NUMBER', 'SCORE', 'NUMERIC_0_100', '0-100'], true) || $catRaw === 'UJIAN_BAB';
                $details = json_decode((string) ($score['Evaluation_Details'] ?? ''), true);
                $details = is_array($details) ? $details : [];
                $scoreVal = $score['Score'] ?? $score['Score_Value'] ?? 0;
                $notes = trim((string) ($details['notes'] ?? ''));
                $aspects = $config && !empty($config['Aspects_JSON']) ? json_decode($config['Aspects_JSON'], true) : [];
                $aspectMap = collect(is_array($aspects) ? $aspects : [])->pluck('label', 'id')->toArray();
                $hasAspectDetails = false;
                foreach ($details as $key => $val) {
                    if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                        continue;
                    }
                    if (isset($aspectMap[$key])) {
                        $hasAspectDetails = true;
                        break;
                    }
                }
            @endphp
            <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-white" x-data="{ open: false }">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Penilaian {{ $catLabel }}</h4>
                        <p class="text-xs text-slate-500">{{ date('d F Y', strtotime($score['Created_At'] ?? now())) }}</p>
                    </div>
                </div>

                @if($isNumeric)
                    <div class="mt-3 bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <span class="text-sm text-slate-600 font-semibold">Hasil:</span>
                        <span class="font-black text-lg text-slate-800">{{ $scoreVal }}</span>
                    </div>
                    @if($notes !== '')
                        <div class="mt-3">
                            <button @click="open = !open" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-semibold transition-colors flex items-center gap-1 w-full justify-center">
                                <span x-show="!open">Lihat Catatan</span>
                                <span x-show="open" style="display: none;">Tutup Catatan</span>
                            </button>
                            <div x-show="open" style="display: none;" class="mt-4 space-y-4">
                                <div class="mt-4 pt-4 border-t border-slate-100">
                                    <h6 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Catatan Guru</h6>
                                    <p class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg">{{ $notes }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @elseif($hasAspectDetails)
                    <div class="mt-3">
                        <button @click="open = !open" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-semibold transition-colors flex items-center gap-1 w-full justify-center">
                            <span x-show="!open">Lihat Detail Penilaian Aspektual</span>
                            <span x-show="open" style="display: none;">Tutup Detail</span>
                        </button>

                        <div x-show="open" style="display: none;" class="mt-4 space-y-4">
                            <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Hasil Penilaian</h5>
                            @php
                                $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                                $validAspectsCount = 0;
                            @endphp
                            @foreach($details as $key => $val)
                                @php
                                    if(in_array(strtolower((string) $key), ['category', 'notes', 'comment', 'title', 'score', 'feedback', 'metadata', 'subject_id'])) continue;
                                    if(!is_numeric($val) || $val < 1 || $val > 5) continue;

                                    $validAspectsCount++;
                                    $label = $aspectMap[$key] ?? 'Aspek tidak dikenali';
                                    if ($label === 'Aspek tidak dikenali') \Log::warning("Unrecognized aspect key: $key for category: $catRaw");

                                    $scoreNum = (int)$val;
                                    $desc = $labels[$scoreNum] ?? 'Nilai tidak valid';
                                    $percent = $scoreNum * 20;
                                @endphp
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-semibold text-slate-700">{{ $label }}</span>
                                        <span class="text-xs font-bold text-slate-500">{{ $scoreNum }} &middot; {{ $desc }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                            @if($validAspectsCount === 0)
                                <div class="text-center text-sm text-slate-500 italic py-2">Detail aspek belum tersedia.</div>
                            @endif

                            @if(!empty($details['notes']))
                                <div class="mt-4 pt-4 border-t border-slate-100">
                                    <h6 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Catatan Guru</h6>
                                    <p class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg">{{ $details['notes'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="mt-3 bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <span class="text-sm text-slate-600 font-semibold">Hasil:</span>
                        <span class="font-black text-lg text-slate-800">{{ $scoreVal }}</span>
                    </div>
                @endif
            </div>
            @empty
            <div class="p-8 text-center text-slate-500 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada penilaian yang tersedia.
            </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
