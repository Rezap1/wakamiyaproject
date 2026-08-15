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
                    <option value="GENERAL" {{ request('category') == 'GENERAL' ? 'selected' : '' }}>📚 Akademik Umum (GENERAL)</option>
                    <option value="SPORTS" {{ request('category') == 'SPORTS' ? 'selected' : '' }}>🏀 Olahraga (SPORTS)</option>
                    <option value="LANGUAGE" {{ request('category') == 'LANGUAGE' ? 'selected' : '' }}>🗣️ Bahasa (LANGUAGE)</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

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
                        @if($category === 'SPORTS')
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800 uppercase">SPORTS</span>
                        @elseif($category === 'LANGUAGE')
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-purple-100 text-purple-800 uppercase">LANGUAGE</span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800 uppercase">GENERAL</span>
                        @endif
                        <span class="font-bold text-slate-700 text-xs">{{ $item['Assessment_Title'] ?? $item['Assessment_ID'] ?? '-' }}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1 truncate max-w-xs">
                        @if($category === 'SPORTS')
                            Lari: {{ $details['running_distance'] ?? 0 }}km/{{ $details['running_time'] ?? 0 }}m &bull; PushUp: {{ $details['push_up'] ?? 0 }} &bull; SitUp: {{ $details['sit_up'] ?? 0 }}
                        @elseif($category === 'LANGUAGE')
                            Spk: {{ $details['speaking'] ?? 0 }} &bull; Wrt: {{ $details['writing'] ?? 0 }} &bull; Lst: {{ $details['listening'] ?? 0 }} &bull; Rdg: {{ $details['reading'] ?? 0 }}
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
                        <x-universal.action-button action="detail" url="{{ route('scores.show', $item['Score_ID'] ?? '1') }}" />
                        <x-universal.action-button action="edit" url="{{ route('scores.edit', $item['Score_ID'] ?? '1') }}" />
                        <x-universal.action-button action="delete" url="{{ route('scores.destroy', $item['Score_ID'] ?? '1') }}" />
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
