@extends('layouts.app')
@section('header', 'Academic Progress')
@section('content')
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
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
                $catRaw = strtoupper($score['Assessment_Category'] ?? '');
                $config = $assessmentConfigs[$catRaw] ?? null;
                $catLabel = $config ? $config['Category_Name'] : ($catRaw ?: 'Tidak dikategorikan');
                $detailsRaw = $score['Evaluation_Details'] ?? null;
            @endphp
            <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-white" x-data="{ open: false }">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Penilaian {{ $catLabel }}</h4>
                        <p class="text-xs text-slate-500">{{ date('d F Y', strtotime($score['Created_At'] ?? now())) }}</p>
                    </div>
                </div>

                @if(!empty($detailsRaw))
                    <div class="mt-3">
                        <button @click="open = !open" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-semibold transition-colors flex items-center gap-1 w-full justify-center">
                            <span x-show="!open">Lihat Detail Penilaian Aspektual</span>
                            <span x-show="open" style="display: none;">Tutup Detail</span>
                        </button>

                        <div x-show="open" style="display: none;" class="mt-4 space-y-4">
                            <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Hasil Penilaian</h5>
                            @php
                                $details = json_decode($detailsRaw, true) ?? [];
                                $aspects = $config && !empty($config['Aspects_JSON']) ? json_decode($config['Aspects_JSON'], true) : [];
                                $aspectMap = collect($aspects)->pluck('label', 'id')->toArray();
                                $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                                $validAspectsCount = 0;
                            @endphp
                            @foreach($details as $key => $val)
                                @php
                                    if(in_array(strtolower($key), ['category', 'notes', 'comment', 'title', 'score', 'feedback', 'metadata'])) continue;
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
                    @php $scoreVal = $score['Score'] ?? $score['Score_Value'] ?? 0; @endphp
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

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Riwayat Kehadiran</h3>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('student.export-attendances-pdf') }}" class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-bold">PDF</a>
                <a href="{{ route('student.print-attendances') }}" target="_blank" class="text-xs px-3 py-1 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors font-bold">Cetak</a>
                <a href="{{ route('student.export-attendances') }}" class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors font-bold">CSV</a>
            </div>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($myAttendances as $att)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-slate-600">{{ $att['Attendance_Date'] ?? $att['Date'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusRaw = strtoupper(trim($att['Resolved_Status'] ?? ''));
                                $translated = match($statusRaw) {
                                    'PRESENT', 'HADIR' => ['Hadir', 'bg-emerald-100 text-emerald-700'],
                                    'LATE', 'TERLAMBAT' => ['Terlambat', 'bg-orange-100 text-orange-700'],
                                    'SICK', 'SAKIT' => ['Sakit', 'bg-amber-100 text-amber-700'],
                                    'PERMITTED', 'IZIN' => ['Izin', 'bg-blue-100 text-blue-700'],
                                    'ABSENT', 'ALPHA', 'ALPA' => ['Alpa', 'bg-rose-100 text-rose-700'],
                                    default => ['Status tidak diketahui', 'bg-slate-100 text-slate-700']
                                };
                            @endphp
                            <span class="px-2 py-1 text-[10px] font-bold rounded-full {{ $translated[1] }}">
                                {{ $translated[0] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-4 py-8 text-center text-slate-500">Belum ada riwayat kehadiran.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection



