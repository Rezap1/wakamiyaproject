<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    Direktori Alumni LPK Wakamiya
                </h2>
                <p class="text-sm text-slate-500 mt-1">Daftar siswa yang telah menyelesaikan pendidikan (Lulus) di LPK Wakamiya.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-sm">
                    Total Alumni: {{ $totalAlumni }} Siswa
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 space-y-6">
        <!-- Search & Filter Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <form method="GET" action="{{ route('alumni.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Pencarian Alumni</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari NIS, Nama, Email..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Program Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Program Pelatihan</label>
                    <select name="program" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Semua Program</option>
                        @foreach($programs as $p)
                            <option value="{{ $p['Program_ID'] }}" {{ request('program') == $p['Program_ID'] ? 'selected' : '' }}>
                                {{ $p['Program_Name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Batch Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Angkatan (Batch)</label>
                    <select name="batch" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Semua Angkatan</option>
                        @foreach($batches as $b)
                            <option value="{{ $b['Batch_ID'] }}" {{ request('batch') == $b['Batch_ID'] ? 'selected' : '' }}>
                                {{ $b['Batch_Name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Submit & Reset Actions -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors shadow-sm flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </button>
                    @if(request()->anyFilled(['search', 'program', 'batch', 'grad_year']))
                        <a href="{{ route('alumni.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium py-2 px-3 rounded-lg text-sm transition-colors flex items-center justify-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4">ID / NIS</th>
                            <th class="py-3.5 px-4">Nama Lengkap</th>
                            <th class="py-3.5 px-4">Program & Angkatan</th>
                            <th class="py-3.5 px-4">Status Kelulusan</th>
                            <th class="py-3.5 px-4">Tahun Lulus</th>
                            <th class="py-3.5 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($alumni as $student)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 font-mono font-medium text-slate-800">
                                    {{ $student['Student_Number'] ?? $student['Student_ID'] }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">{{ $student['Full_Name'] }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $student['Email'] ?? '-' }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-700">{{ $student['Program_Name'] }}</div>
                                    <div class="text-xs text-slate-400">{{ $student['Batch_Name'] }} ({{ $student['Class_Name'] }})</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ $student['Graduation_Status'] ?? 'Lulus' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-600">
                                    {{ $student['Graduation_Year'] }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="{{ route('alumni.show', $student['Student_ID']) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg transition-colors border border-indigo-200">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Detail Alumni
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                        </svg>
                                        <span class="text-sm font-medium">Belum ada data Alumni yang memenuhi kriteria pencarian.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            @if($alumni->hasPages())
                <div class="px-5 py-4 bg-slate-50 border-t border-slate-200">
                    {{ $alumni->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
