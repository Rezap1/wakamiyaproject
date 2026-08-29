@extends('layouts.app')
@section('header', 'Manajemen Rapor & Evaluasi Nilai')
@section('content')

<x-universal.index-layout 
    title="Data Nilai & Evaluasi Siswa" 
    description="Pantau nilai akademik umum, evaluasi olahraga, dan rubrik kemampuan bahasa secara terpadu."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Akademik' => '#', 'Nilai' => route('scores.index')]"
    add-action="{{ route('scores.create') }}"
    add-text="Input Nilai Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="scores" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('scores.index') }}" 
            refresh-url="{{ route('scores.index') }}"
            export-url="{{ route('scores.export-pdf') }}"
        >
            <div class="w-full md:w-auto">
                <select name="category" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Kategori Penilaian</option>
                    @foreach($assessmentConfigs as $config)
                        <option value="{{ $config['Category_ID'] }}" {{ request('category') == $config['Category_ID'] ? 'selected' : '' }}>{{ $config['Category_Name'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    @if(($scoreGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
            @foreach($scoreGroups as $group)
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} nilai @if($group['average'] !== null) | Rata-rata {{ $group['average'] }} @endif</p>
                        </div>
                        <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $score)
                            <a href="{{ route('scores.show', $score['Score_ID']) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $score['Student_Display'] ?? $score['Student_ID'] ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono">{{ $score['Score_ID'] ?? '-' }} | {{ $score['Assessment_Title'] ?? $score['Assessment_ID'] ?? '-' }}</p>
                                </div>
                                <span class="text-sm font-black text-slate-800 shrink-0">{{ $score['Score'] ?? $score['Score_Value'] ?? '-' }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <x-universal.data-table :empty="count($scores) === 0" empty-title="Data Nilai Kosong" empty-description="Belum ada data nilai atau evaluasi yang tercatat.">
        <x-slot:header>
            <th class="px-6 py-4">ID Nilai & Siswa</th>
            <th class="px-6 py-4">Kategori & Assessment</th>
            <th class="px-6 py-4 text-center">Nilai Komposit</th>
            <th class="px-6 py-4 text-center">Grade</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($scores as $item)
            @php
                $val = $item['Score'] ?? $item['Score_Value'] ?? 0;
                $val = is_numeric($val) ? (float) $val : 0;
                $result = \App\Helpers\GradeHelper::calculate($val);
                $category = strtoupper($item['Assessment_Category'] ?? 'GENERAL');
                $details = $item['Parsed_Details'] ?? [];
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $item['Student_Display'] ?? $item['Student_ID'] ?? 'Unknown' }}</div>
                    <div class="text-xs font-mono text-slate-400 mt-0.5">{{ $item['Score_ID'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800 uppercase">{{ $category }}</span>
                        <span class="font-bold text-slate-700 text-xs">{{ $item['Assessment_Title'] ?? $item['Assessment_ID'] ?? '-' }}</span>
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
