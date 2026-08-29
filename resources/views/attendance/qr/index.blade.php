@extends('layouts.app')
@section('header', 'Manajemen QR Presensi Permanen')
@section('content')

<div class="max-w-7xl mx-auto space-y-6">
    @php
        $context = app(\App\Services\Dashboard\DashboardContextService::class)->getContext();
        $role = strtoupper($context['role'] ?? '');
        
        if ($role === 'ACADEMIC') {
            $qrCodes = collect($qrCodes)->filter(fn($qr) => ($qr['QR_TYPE'] ?? '') === 'STUDENT')->values();
        } elseif ($role === 'HR') {
            $qrCodes = collect($qrCodes)->filter(fn($qr) => ($qr['QR_TYPE'] ?? '') === 'EMPLOYEE')->values();
        }
    @endphp
    @if(session('success'))
        <div class="p-4 bg-emerald-50 text-emerald-800 rounded-xl font-medium border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-50 text-rose-800 rounded-xl font-medium border border-rose-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @if($role !== 'HR')
        <!-- Form Buat QR Siswa -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Buat QR Siswa</h3>
            <form action="{{ route('attendance.qr.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="QR_TYPE" value="STUDENT">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Nama/Lokasi QR <span class="text-rose-500">*</span></label>
                    <input type="text" name="LABEL" required class="w-full rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm" placeholder="Contoh: Presensi Utama LPK">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Aktif Mulai</label>
                        <input type="datetime-local" name="ACTIVE_FROM" value="{{ old('ACTIVE_FROM') }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nonaktif Setelah</label>
                        <input type="datetime-local" name="ACTIVE_UNTIL" value="{{ old('ACTIVE_UNTIL') }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl transition-colors">
                    + Buat QR Siswa
                </button>
            </form>
        </div>

        @endif

        @if($role !== 'ACADEMIC')
        <!-- Form Buat QR Pegawai -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Buat QR Pegawai</h3>
            <form action="{{ route('attendance.qr.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="QR_TYPE" value="EMPLOYEE">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Nama/Lokasi QR <span class="text-rose-500">*</span></label>
                    <input type="text" name="LABEL" required class="w-full rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm" placeholder="Contoh: Lobby LPK">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Aktif Mulai</label>
                        <input type="datetime-local" name="ACTIVE_FROM" value="{{ old('ACTIVE_FROM') }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nonaktif Setelah</label>
                        <input type="datetime-local" name="ACTIVE_UNTIL" value="{{ old('ACTIVE_UNTIL') }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-colors">
                    + Buat QR Pegawai
                </button>
            </form>
        </div>
        @endif
    </div>

    <!-- Daftar QR -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800">Daftar QR Presensi</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Label/Lokasi</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Identifier</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Jadwal Aktif</th>
                        <th class="px-6 py-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($qrCodes as $qr)
                    @php
                        $availability = app(\App\Services\Core\PermanentQrService::class)->getAvailabilityStatus($qr);
                        $state = $availability['state'] ?? 'INACTIVE';
                        $activeFrom = !empty($qr['ACTIVE_FROM']) ? \Carbon\Carbon::parse($qr['ACTIVE_FROM'])->format('Y-m-d\TH:i') : '';
                        $activeUntil = !empty($qr['ACTIVE_UNTIL']) ? \Carbon\Carbon::parse($qr['ACTIVE_UNTIL'])->format('Y-m-d\TH:i') : '';
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800">{{ $qr['LABEL'] ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if(($qr['QR_TYPE'] ?? '') === 'STUDENT')
                                <span class="px-2.5 py-1 rounded-full bg-sky-100 text-sky-700 font-bold text-[10px]">Siswa</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 font-bold text-[10px]">Pegawai</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono text-xs">{{ $qr['IDENTIFIER'] ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($state === 'ACTIVE')
                                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold text-[10px] flex items-center gap-1 w-max">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ACTIVE
                                </span>
                            @elseif($state === 'SCHEDULED')
                                <span class="px-2.5 py-1 rounded-full bg-sky-100 text-sky-700 font-bold text-[10px]">SCHEDULED</span>
                            @elseif($state === 'EXPIRED')
                                <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-bold text-[10px]">EXPIRED</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px]">INACTIVE</span>
                            @endif
                            <div class="text-[10px] text-slate-400 mt-1 max-w-[180px]">{{ $availability['message'] ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 min-w-[260px]">
                            <form action="{{ route('attendance.qr.availability', $qr['QR_ID']) }}" method="POST" class="space-y-2">
                                @csrf
                                <select name="STATUS" class="w-full rounded-lg border-slate-300 text-xs font-bold">
                                    <option value="ACTIVE" {{ strtoupper($qr['STATUS'] ?? '') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                    <option value="INACTIVE" {{ strtoupper($qr['STATUS'] ?? '') === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                                </select>
                                <input type="datetime-local" name="ACTIVE_FROM" value="{{ $activeFrom }}" class="w-full rounded-lg border-slate-300 text-xs" title="Aktif mulai">
                                <input type="datetime-local" name="ACTIVE_UNTIL" value="{{ $activeUntil }}" class="w-full rounded-lg border-slate-300 text-xs" title="Nonaktif setelah">
                                <button type="submit" class="w-full px-3 py-1.5 bg-slate-900 text-white hover:bg-slate-800 font-bold rounded-lg text-xs transition-colors">Simpan Jadwal</button>
                            </form>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('attendance.qr.preview', $qr['QR_ID']) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold rounded-lg text-xs transition-colors">Preview</a>
                                <a href="{{ route('attendance.qr.print', $qr['QR_ID']) }}" target="_blank" class="px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 font-bold rounded-lg text-xs transition-colors">Cetak</a>
                                <a href="{{ route('attendance.qr.pdf', $qr['QR_ID']) }}" class="px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 font-bold rounded-lg text-xs transition-colors">PDF</a>
                                @if(strtoupper($qr['STATUS'] ?? '') === 'ACTIVE')
                                <form action="{{ route('attendance.qr.deactivate', $qr['QR_ID'] ?? $qr['qr_id'] ?? $qr['IDENTIFIER'] ?? $qr['identifier'] ?? $qr['id'] ?? '') }}" 
                                      method="POST" 
                                      class="inline"
                                      data-confirm="true"
                                      data-confirm-title="Nonaktifkan QR Presensi"
                                      data-confirm-message="Apakah Anda yakin ingin menonaktifkan QR Presensi ini?"
                                      data-confirm-type="warning"
                                      data-confirm-text="Ya, Nonaktifkan">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold rounded-lg text-xs transition-colors">Nonaktifkan</button>
                                </form>
                                @endif
                                <form action="{{ route('attendance.qr.destroy', $qr['QR_ID'] ?? $qr['qr_id'] ?? $qr['IDENTIFIER'] ?? $qr['identifier'] ?? $qr['id'] ?? '') }}" 
                                      method="POST" 
                                      class="inline"
                                      data-confirm="true"
                                      data-confirm-title="Hapus QR Presensi"
                                      data-confirm-message="Apakah Anda yakin ingin menghapus QR Presensi {{ $qr['LABEL'] ?? $qr['IDENTIFIER'] ?? '' }} secara permanen? Data tidak dapat dikembalikan."
                                      data-confirm-type="danger"
                                      data-confirm-text="Ya, Hapus Permanen">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 font-bold rounded-lg text-xs transition-colors flex items-center gap-1 cursor-pointer" title="Hapus Permanen">
                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        <span class="pointer-events-none">Hapus</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400 font-medium">Belum ada QR Presensi Permanen</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
