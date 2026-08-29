@extends('layouts.app')
@section('header', 'Manajemen Penilaian')
@section('content')

<x-universal.index-layout 
    title="Data Penilaian" 
    description="Kelola semua penilaian, kuis, dan ujian."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Penilaian' => route('assessments.index')]"
    add-action="{{ route('assessments.create') }}"
    add-text="Buat Penilaian"
>

    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="assessments" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('assessments.index') }}" 
            refresh-url="{{ route('assessments.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="category" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <option value="Placement Test" {{ request('category') == 'Placement Test' ? 'selected' : '' }}>Placement Test</option>
                    <option value="Daily Quiz" {{ request('category') == 'Daily Quiz' ? 'selected' : '' }}>Daily Quiz</option>
                    <option value="Assignment" {{ request('category') == 'Assignment' ? 'selected' : '' }}>Assignment</option>
                    <option value="Mid Test" {{ request('category') == 'Mid Test' ? 'selected' : '' }}>Mid Test</option>
                    <option value="Final Test" {{ request('category') == 'Final Test' ? 'selected' : '' }}>Final Test</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($assessments) === 0" empty-title="Data Assessment Kosong" empty-description="Belum ada data assessment.">
        <x-slot:header>
            <th class="px-6 py-4">Info Penilaian</th>
            <th class="px-6 py-4">Kategori & Mata Pelajaran</th>
            <th class="px-6 py-4">Target Kelas</th>
            <th class="px-6 py-4 text-center">Nilai / Tanggal</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($assessments as $item)
            @php
                $cat = $item['Category'] ?? 'Uncategorized';
                $status = $item['Status'] ?? 'Draft';
                
                $statusColor = match($status) {
                    'Published' => 'blue',
                    'Closed' => 'green',
                    'Archived' => 'slate',
                    default => 'yellow',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <span class="font-bold text-slate-800 block">{{ $item['Assessment_ID'] ?? '' }}</span>
                    <span class="text-xs text-slate-500 font-medium mt-1">{{ $item['Name'] ?? '' }}</span>
                </td>
                <td class="px-6 py-4">
                    <x-badge color="blue">{{ $cat }}</x-badge>
                    <span class="block text-xs font-semibold text-slate-600 mt-1">{{ $item['Subject_ID'] ?? '' }}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="font-semibold text-slate-800 text-sm block">{{ $item['Class_ID'] ?? '' }}</span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase">{{ $item['Program_ID'] ?? '' }}</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="font-bold text-slate-800 block text-lg">{{ $item['Max_Score'] ?? 100 }}</span>
                    <span class="text-[11px] text-slate-500 font-medium mt-1">{{ $item['Exam_Date'] ?? '' }}</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $statusColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if(!empty($item['Assessment_ID']))
                            <x-universal.action-button action="detail" url="{{ route('assessments.show', $item['Assessment_ID']) }}" />
                            <x-universal.action-button action="edit" url="{{ route('assessments.edit', $item['Assessment_ID']) }}" />
                        @else
                            <span class="text-xs font-semibold text-slate-400">Tidak tersedia</span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($assessments, 'links'))
                <x-universal.pagination :paginator="$assessments" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection



