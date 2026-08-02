@extends('layouts.app')
@section('header', 'Manajemen Nilai')
@section('content')

<x-universal.index-layout 
    title="Data Nilai" 
    description="Pantau nilai siswa."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Nilai' => route('scores.index')]"
    add-action="{{ route('scores.create') }}"
    add-text="Input Nilai"
>
    
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="scores" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('scores.index') }}" 
            refresh-url="{{ route('scores.index') }}"
            export-url="#"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($scores) === 0" empty-title="Data Score Kosong" empty-description="Belum ada data nilai.">
        <x-slot:header>
            <th class="px-6 py-4">Siswa</th>
            <th class="px-6 py-4 text-center">Nilai</th>
            <th class="px-6 py-4 text-center">Grade</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($scores as $item)
            @php
                $val = $item['Score_Value'] ?? 0;
                $result = \App\Helpers\GradeHelper::calculate($val);
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Student_ID'] ?? 'Unknown' }}</td>
                <td class="px-6 py-4 text-center font-black text-slate-800 text-lg">{{ $val }}</td>
                <td class="px-6 py-4 text-center font-bold">{{ $result['grade'] }}</td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $result['pass'] ? 'green' : 'red' }}">
                        {{ $result['pass'] ? 'PASS' : 'FAIL' }}
                    </x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="detail" url="{{ route('scores.show', $item['Score_ID'] ?? '1') }}" />
                        <x-universal.action-button action="edit" url="{{ route('scores.edit', $item['Score_ID'] ?? '1') }}" />
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



