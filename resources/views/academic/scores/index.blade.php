@extends('layouts.app')
@section('header', 'Rekap Nilai & Evaluasi')
@section('content')

<x-universal.index-layout 
    title="Rekap Nilai & Evaluasi Siswa"
    description="Pantau nilai akademik umum, evaluasi olahraga, dan rubrik kemampuan bahasa secara terpadu."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Akademik' => '#', 'Nilai' => route('scores.index')]"
    add-action="{{ route('scores.create') }}"
    add-text="Input Nilai Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="scores" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        @if($errors->has('date_from') || $errors->has('date_to'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">
                {{ $errors->first('date_from') ?: $errors->first('date_to') }}
            </div>
        @endif
        <x-universal.toolbar 
            search-url="{{ route('scores.index') }}" 
            refresh-url="{{ route('scores.index') }}"
            export-url="{{ route('scores.export-pdf', request()->except('page')) }}"
            :show-status-filter="false"
            :date-filter-labels="true"
            :date-auto-submit="false"
            :show-filter-buttons="true"
        >
            <div class="w-full md:w-auto">
                <label class="mb-1 block text-xs font-bold text-slate-600" for="score-category">Kategori</label>
                <select id="score-category" name="category" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                    <option value="">Semua Kategori Penilaian</option>
                    @foreach($assessmentConfigs as $config)
                        <option value="{{ $config['Category_ID'] }}" {{ request('category') == $config['Category_ID'] ? 'selected' : '' }}>{{ $config['Category_Name'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
        @if(request()->filled('date_from') || request()->filled('date_to'))
            <p class="-mt-3 mb-5 text-xs font-semibold text-slate-500">
                Periode penilaian:
                {{ request()->filled('date_from') ? \App\Helpers\DateHelper::format(request('date_from'), 'd F Y') : 'awal data' }}
                s.d.
                {{ request()->filled('date_to') ? \App\Helpers\DateHelper::format(request('date_to'), 'd F Y') : 'akhir data' }}
            </p>
        @endif
    </x-slot:toolbar>

    @if(($scoreGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
            @foreach($scoreGroups as $group)
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} nilai @if($group['average'] !== null) | Rata-rata {{ \App\Support\Academic\ScoreValueFormatter::format($group['average']) }} @endif</p>
                        </div>
                        <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $score)
                            <a href="{{ route('scores.show', $score['Score_ID']) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $score['Student_Display'] ?? 'Data siswa tidak ditemukan' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $score['Assessment_Title'] ?? 'Penilaian tidak ditemukan' }}</p>
                                </div>
                                <span class="text-sm font-black text-slate-800 shrink-0">{{ \App\Support\Academic\ScoreValueFormatter::format($score['Score'] ?? $score['Score_Value'] ?? null) }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <x-universal.data-table :empty="count($scores) === 0" empty-title="Data Nilai Kosong" empty-description="Belum ada data nilai atau evaluasi yang tercatat.">
        <x-slot:header>
            <th class="px-6 py-4">Siswa</th>
            <th class="px-6 py-4">Kategori & Assessment</th>
            <th class="px-6 py-4 text-center">Nilai Komposit</th>
            <th class="px-6 py-4 text-center">Grade</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($scores as $item)
            @php
                $rawVal = $item['Score'] ?? $item['Score_Value'] ?? 0;
                $result = \App\Helpers\GradeHelper::calculate(is_numeric($rawVal) ? (float) $rawVal : 0);
                $val = \App\Support\Academic\ScoreValueFormatter::format($rawVal, '0');
                $category = strtoupper($item['Assessment_Category'] ?? 'GENERAL');
                $categoryLabel = $item['Assessment_Category_Label'] ?? $category;
                $details = $item['Parsed_Details'] ?? [];
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $item['Student_Display'] ?? 'Data siswa tidak ditemukan' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800">{{ $categoryLabel }}</span>
                        <span class="font-bold text-slate-700 text-xs">{{ $item['Assessment_Title'] ?? 'Penilaian tidak ditemukan' }}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1 truncate max-w-xs">
                        @php
                            $metricSummary = [];
                            if (!empty($details) && is_array($details)) {
                                foreach ($details as $k => $v) {
                                    if (in_array(strtolower($k), ['category', 'notes', 'subject_id'])) continue;
                                    $metricSummary[] = ucfirst(str_replace('_', ' ', $k)) . ": " . $v;
                                }
                            }
                        @endphp
                        @if(!empty($metricSummary))
                            {!! implode(' &bull; ', $metricSummary) !!}
                        @else
                            {{ $details['notes'] ?? $item['Remarks'] ?? 'Tidak ada catatan.' }}
                        @endif
                    </div>
                </td>
                <td class="px-6 py-4 text-center font-black text-slate-800 text-lg">{{ $val }}</td>
                <td class="px-6 py-4 text-center font-bold text-slate-700">{{ $result['grade'] }}</td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $result['pass'] ? 'green' : 'red' }}">
                        {{ $result['pass'] ? 'PASS' : 'FAIL' }}
                    </x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if(!empty($item['Score_ID']))
                            <x-universal.action-button action="detail" url="{{ route('scores.show', $item['Score_ID']) }}" />
                            <x-universal.action-button action="edit" url="{{ route('scores.edit', $item['Score_ID']) }}" />
                            <x-universal.action-button action="delete" url="{{ route('scores.destroy', $item['Score_ID']) }}" />
                        @else
                            <span class="text-xs font-semibold text-slate-400">Tidak tersedia</span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($scores, 'links'))
                <x-universal.pagination :paginator="$scores" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection
