@extends('layouts.app')
@section('header', 'Manajemen Siswa')
@section('content')

<x-universal.index-layout 
    title="Daftar Induk Siswa" 
    description="Kelola data pendaftaran dan profil seluruh siswa aktif maupun alumni."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Siswa' => route('students.index')]"
    add-action="{{ route('students.create') }}"
    add-text="Daftarkan Siswa"
>

    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="students" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('students.index') }}" 
            refresh-url="{{ route('students.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="program" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Program</option>
                    @foreach($programs as $program)
                        <option value="{{ $program['Program_ID'] }}" {{ request('program') == $program['Program_ID'] ? 'selected' : '' }}>{{ $program['Program_Code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <select name="batch" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Angkatan</option>
                    @foreach($batches as $batch)
                        <option value="{{ $batch['Batch_ID'] }}" {{ request('batch') == $batch['Batch_ID'] ? 'selected' : '' }}>{{ $batch['Batch_Code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <select name="class" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    @foreach($classes as $cls)
                        <option value="{{ $cls['Class_ID'] }}" {{ request('class') == $cls['Class_ID'] ? 'selected' : '' }}>{{ $cls['Class_Code'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($students) === 0" empty-title="Data Siswa Kosong" empty-description="Belum ada siswa yang terdaftar.">
        <x-slot:header>
            <th class="px-6 py-4">Identitas Siswa</th>
            <th class="px-6 py-4">Program / Kelas</th>
            <th class="px-6 py-4">Kontak</th>
            <th class="px-6 py-4">Tgl Registrasi</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($students as $student)
        <tr class="hover:bg-slate-50 transition-colors {{ ($student['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full {{ $student['Gender'] == 'Laki-laki' ? 'bg-blue-100 text-blue-600 border-blue-200' : 'bg-pink-100 text-pink-600 border-pink-200' }} flex items-center justify-center font-bold text-sm border shrink-0">
                        {{ substr($student['Full_Name'], 0, 1) }}
                    </div>
                    <div>
                        <div class="font-bold text-slate-800">{{ $student['Full_Name'] }}</div>
                        <div class="text-[11px] font-bold text-slate-500 mt-0.5">{{ $student['Student_Number'] }}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="font-bold text-indigo-700 truncate max-w-xs" title="{{ $student['Program_Name'] }}">{{ $student['Program_Name'] }}</div>
                <div class="text-xs font-medium text-slate-500 mt-0.5 flex items-center gap-1">
                    {{ $student['Class_Name'] }}
                    <span class="text-slate-300">•</span>
                    {{ $student['Batch_Name'] }}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm font-medium text-slate-800 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    {{ $student['Phone_Number'] ?: '-' }}
                </div>
                <div class="text-xs font-medium text-slate-500 mt-1 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    {{ $student['Email'] ?: '-' }}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-xs text-slate-500">
                    {{ isset($student['Registration_Date']) && $student['Registration_Date'] ? \Carbon\Carbon::parse($student['Registration_Date'])->format('d M Y') : (isset($student['Created_At']) && $student['Created_At'] ? \Carbon\Carbon::parse($student['Created_At'])->format('d M Y') : '-') }}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex flex-col gap-1.5 items-start">
                    <x-badge status="{{ ($student['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                        {{ ($student['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Sistem Aktif' : 'Sistem Nonaktif' }}
                    </x-badge>
                    <x-badge color="blue">{{ $student['Enrollment_Status'] }}</x-badge>
                </div>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('students.show', $student['Student_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('students.edit', $student['Student_ID']) }}" />
                    @if(($student['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('students.destroy', $student['Student_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$students" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection



