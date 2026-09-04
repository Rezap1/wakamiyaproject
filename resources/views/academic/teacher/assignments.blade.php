@extends('layouts.app')
@section('header', 'Tugas Harian')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Tugas Harian" 
        description="Kelola tugas untuk kelas yang Anda ajar."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Tugas Harian' => '#']"
    >
        <x-slot:actions>
            <x-button as="a" href="{{ route('assignments.create') }}" variant="primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Tambah Tugas
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Daftar Tugas</h3>
                <p class="text-sm text-slate-500 mt-1">Tugas dari kelas yang Anda ajar.</p>
            </div>
        </div>

        <div class="md:hidden divide-y divide-slate-100">
            @forelse($assignments as $assignment)
                <div class="p-4 bg-white space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-extrabold text-slate-900">{{ $assignment['Title'] ?? 'No Title' }}</p>
                            <p class="text-xs font-semibold text-blue-600">Kelas: {{ $assignment['Class_Name'] ?? 'Kelas tidak ditemukan' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ strtoupper(!empty($assignment['Status']) ? $assignment['Status'] : 'PUBLISHED') === 'PUBLISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                            {{ !empty($assignment['Status']) ? $assignment['Status'] : 'Published' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-500">
                        <p><span class="font-semibold">Deadline:</span> {{ $assignment['Deadline'] ?? '-' }}</p>
                    </div>
                    <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                        <a href="{{ route('assignments.edit', $assignment['Assignment_ID']) }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 py-1 px-2 rounded-lg hover:bg-blue-50 transition-colors">Edit</a>
                        <form action="{{ route('assignments.destroy', $assignment['Assignment_ID']) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700 py-1 px-2 rounded-lg hover:bg-red-50 transition-colors">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <x-empty-state icon="document-text" title="Belum ada tugas harian." message="Saat ini tidak ada data yang dapat ditampilkan di bagian ini." />
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Judul Tugas</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Kelas</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Deskripsi</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Tenggat Waktu</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Status</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($assignments as $assignment)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-900">{{ $assignment['Title'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-semibold text-slate-600">{{ $assignment['Class_Name'] ?? 'Kelas tidak ditemukan' }}</td>
                        <td class="px-6 py-4 text-slate-500 italic max-w-xs truncate">{{ $assignment['Description'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $assignment['Deadline'] ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ strtoupper(!empty($assignment['Status']) ? $assignment['Status'] : 'PUBLISHED') === 'PUBLISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                {{ !empty($assignment['Status']) ? $assignment['Status'] : 'PUBLISHED' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('assignments.edit', $assignment['Assignment_ID']) }}" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('assignments.destroy', $assignment['Assignment_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="document-text" title="Belum ada tugas harian." message="Saat ini tidak ada data yang dapat ditampilkan di bagian ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
