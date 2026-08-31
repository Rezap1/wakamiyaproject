@extends('layouts.app')
@section('header', 'Kehadiran Siswa')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Kehadiran Siswa"
        description="Rekap kehadiran berbasis kelas dari kelas yang Anda ajar."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Kehadiran Siswa' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
        <form action="{{ route('teacher.workspace.attendances') }}" method="GET" class="flex flex-col md:flex-row md:items-end gap-3">
            <div>
                <label for="teacher-attendance-date" class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal</label>
                <input id="teacher-attendance-date" type="date" name="date" value="{{ $dateFilter ?? date('Y-m-d') }}" class="bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5">
            </div>
            <div>
                <label for="teacher-attendance-class" class="block text-xs font-bold text-slate-500 uppercase mb-1">Kelas</label>
                <select id="teacher-attendance-class" name="class_id" class="bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5">
                    <option value="">Semua Kelas</option>
                    @foreach($classOptions ?? [] as $id => $label)
                        <option value="{{ $id }}" @selected(($classFilter ?? '') === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700">Tampilkan</button>
        </form>
    </div>

    @include('academic.teacher._attendance_groups', ['attendanceGroups' => $attendanceGroups ?? collect(), 'dateFilter' => $dateFilter ?? null])
</div>
@endsection
