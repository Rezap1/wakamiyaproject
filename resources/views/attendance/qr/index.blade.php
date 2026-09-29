@extends('layouts.app')
@section('header', 'Manajemen QR Presensi Permanen')
@section('content')

@php
    $studentWindowStart = old('WORK_START_TIME', $studentWindow['start'] ?? '');
    $studentWindowEnd = old('WORK_END_TIME', $studentWindow['end'] ?? '');
    $studentWindowValid = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $studentWindowStart)
        && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $studentWindowEnd);
@endphp

<div class="mx-auto max-w-7xl min-w-0 space-y-6 overflow-x-clip px-0">
    <x-page-header
        title="Manajemen QR Presensi Permanen"
        description="Kelola masa berlaku QR dan jam absensi harian sebagai dua aturan yang terpisah."
        :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'QR Presensi' => '#']"
    />

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-sky-200 bg-white shadow-sm" aria-labelledby="student-attendance-settings-title">
        <div class="border-b border-sky-100 bg-sky-50/70 p-4 sm:p-6">
            <h2 id="student-attendance-settings-title" class="text-base font-black text-slate-900">Pengaturan Absensi Siswa</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-600">Jam ini berlaku setiap hari untuk QR siswa dinamis maupun permanen. Masa berlaku QR diatur terpisah pada kartu QR.</p>
        </div>
        <form action="{{ route('attendance.qr.student-settings.update') }}" method="POST" class="grid min-w-0 grid-cols-1 gap-4 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
            @csrf
            <div class="min-w-0">
                <label for="WORK_START_TIME" class="mb-1 block text-xs font-bold text-slate-700">Jam Buka Absensi Siswa</label>
                <input id="WORK_START_TIME" type="time" name="WORK_START_TIME" value="{{ $studentWindowStart }}" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </div>
            <div class="min-w-0">
                <label for="WORK_END_TIME" class="mb-1 block text-xs font-bold text-slate-700">Jam Tutup Absensi Siswa</label>
                <input id="WORK_END_TIME" type="time" name="WORK_END_TIME" value="{{ $studentWindowEnd }}" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </div>
            <button type="submit" class="min-h-[44px] w-full rounded-xl bg-sky-600 px-5 py-2.5 text-xs font-black text-white transition-colors hover:bg-sky-700 lg:w-auto">Simpan Pengaturan</button>
            <p class="text-xs font-semibold text-slate-500 sm:col-span-2 lg:col-span-3">
                Zona Waktu: <span class="text-slate-800">Asia/Jakarta (WIB)</span>
                @if($studentWindowValid)
                    · Aktif saat ini: <span class="text-sky-700">{{ $studentWindowStart }}–{{ $studentWindowEnd }} WIB</span>
                @endif
                · Absensi siswa di luar waktu ini ditolak.
            </p>
        </form>
    </section>

    <section class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-2" aria-label="Buat QR permanen">
        @foreach(['STUDENT' => ['title' => 'Buat QR Siswa', 'button' => 'Buat QR Siswa'], 'EMPLOYEE' => ['title' => 'Buat QR Pegawai', 'button' => 'Buat QR Pegawai']] as $createType => $createMeta)
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="mb-4 text-base font-black text-slate-900">{{ $createMeta['title'] }}</h2>
                <form action="{{ route('attendance.qr.store') }}" method="POST" class="min-w-0 space-y-4">
                    @csrf
                    <input type="hidden" name="QR_TYPE" value="{{ $createType }}">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Nama/Lokasi QR</label>
                        <input type="text" name="LABEL" required maxlength="100" class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Contoh: Presensi Utama LPK">
                    </div>
                    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label class="mb-1 block text-xs font-bold text-slate-700">Tanggal Mulai Berlaku</label>
                            <input type="date" name="ACTIVE_FROM_DATE" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm">
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1 block text-xs font-bold text-slate-700">Jam Mulai Berlaku</label>
                            <input type="time" name="ACTIVE_FROM_TIME" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm">
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1 block text-xs font-bold text-slate-700">Tanggal Berakhir</label>
                            <input type="date" name="ACTIVE_UNTIL_DATE" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm">
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1 block text-xs font-bold text-slate-700">Jam Berakhir</label>
                            <input type="time" name="ACTIVE_UNTIL_TIME" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm">
                        </div>
                    </div>
                    <button type="submit" class="min-h-[44px] w-full rounded-xl px-4 py-2.5 text-xs font-black text-white {{ $createType === 'STUDENT' ? 'bg-sky-600 hover:bg-sky-700' : 'bg-indigo-600 hover:bg-indigo-700' }}">+ {{ $createMeta['button'] }}</button>
                </form>
            </div>
        @endforeach
    </section>

    <section class="min-w-0 space-y-4" aria-labelledby="permanent-qr-list-title">
        <div>
            <h2 id="permanent-qr-list-title" class="text-lg font-black text-slate-900">Daftar QR Presensi</h2>
            <p class="mt-1 text-xs text-slate-500">Mengubah masa berlaku tidak mengganti kode QR atau mewajibkan cetak ulang.</p>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($qrCodes as $qr)
                @php
                    $availability = app(\App\Services\Core\PermanentQrService::class)->getAvailabilityStatus($qr);
                    $state = $availability['state'] ?? 'INVALID';
                    $from = null;
                    $until = null;
                    $rawFrom = trim((string) ($qr['ACTIVE_FROM'] ?? ''));
                    $rawUntil = trim((string) ($qr['ACTIVE_UNTIL'] ?? ''));
                    try {
                        $from = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $rawFrom, 'Asia/Jakarta');
                        $until = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $rawUntil, 'Asia/Jakarta');
                        if (! $from || ! $until
                            || $from->format('Y-m-d H:i:s') !== $rawFrom
                            || $until->format('Y-m-d H:i:s') !== $rawUntil
                            || $until->lessThanOrEqualTo($from)) {
                            $from = null;
                            $until = null;
                        }
                    } catch (\Throwable) {
                        $from = null;
                        $until = null;
                    }
                    $stateClasses = match ($state) {
                        'ACTIVE' => 'bg-emerald-100 text-emerald-700',
                        'UPCOMING' => 'bg-sky-100 text-sky-700',
                        'EXPIRED' => 'bg-amber-100 text-amber-700',
                        'INACTIVE' => 'bg-rose-100 text-rose-700',
                        default => 'bg-slate-200 text-slate-700',
                    };
                    $stateLabel = match ($state) {
                        'ACTIVE' => 'AKTIF',
                        'UPCOMING' => 'BELUM BERLAKU',
                        'EXPIRED' => 'KEDALUWARSA',
                        'INACTIVE' => 'TIDAK AKTIF',
                        default => 'KONFIGURASI TIDAK VALID',
                    };
                @endphp
                <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex min-w-0 items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/70 p-4">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Jenis</p>
                            <h3 class="truncate text-sm font-black text-slate-900">{{ ($qr['QR_TYPE'] ?? '') === 'STUDENT' ? 'QR Siswa' : 'QR Pegawai' }}</h3>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $qr['LABEL'] ?? '-' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="mb-1 text-[9px] font-black uppercase tracking-wider text-slate-400">Status QR</p>
                            <span class="inline-block rounded-full px-2.5 py-1 text-[9px] font-black {{ $stateClasses }}">{{ $stateLabel }}</span>
                        </div>
                    </div>

                    <div class="min-w-0 space-y-4 p-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Kode QR</p>
                            <p class="mt-1 break-all font-mono text-xs font-bold text-slate-800">{{ $qr['IDENTIFIER'] ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Masa Berlaku</p>
                            @if($from && $until)
                                <p class="mt-1 text-xs font-bold leading-relaxed text-slate-800">
                                    Mulai: {{ $from->locale('id')->translatedFormat('d F Y, H:i') }} WIB<br>
                                    Berakhir: {{ $until->locale('id')->translatedFormat('d F Y, H:i') }} WIB
                                </p>
                            @else
                                <p class="mt-1 text-xs font-bold text-rose-600">Konfigurasi tidak valid</p>
                            @endif
                        </div>
                        @if(($qr['QR_TYPE'] ?? '') === 'STUDENT')
                            <div class="rounded-xl border border-sky-100 bg-sky-50 p-3">
                                <p class="text-[10px] font-black uppercase tracking-wider text-sky-600">Jam Absensi Siswa</p>
                                <p class="mt-1 text-sm font-black text-sky-900">{{ $studentWindowStart }}–{{ $studentWindowEnd }} WIB</p>
                            </div>
                        @endif

                        <details class="group rounded-xl border border-slate-200 bg-white">
                            <summary class="cursor-pointer list-none rounded-xl px-3 py-3 text-xs font-black text-slate-700 hover:bg-slate-50">Edit masa berlaku dan status</summary>
                            <form action="{{ route('attendance.qr.update', $qr['QR_ID']) }}" method="POST" class="grid min-w-0 grid-cols-1 gap-3 border-t border-slate-100 p-3 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')
                                <div class="min-w-0 sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-bold text-slate-600">Status QR</label>
                                    <select name="STATUS" class="w-full min-w-0 rounded-lg border-slate-300 text-xs font-bold">
                                        <option value="ACTIVE" {{ strtoupper($qr['STATUS'] ?? '') === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                        <option value="INACTIVE" {{ strtoupper($qr['STATUS'] ?? '') === 'INACTIVE' ? 'selected' : '' }}>Tidak Aktif</option>
                                    </select>
                                </div>
                                <div class="min-w-0">
                                    <label class="mb-1 block text-[11px] font-bold text-slate-600">Tanggal Mulai</label>
                                    <input type="date" name="ACTIVE_FROM_DATE" value="{{ $from?->format('Y-m-d') }}" required class="w-full min-w-0 rounded-lg border-slate-300 text-xs">
                                </div>
                                <div class="min-w-0">
                                    <label class="mb-1 block text-[11px] font-bold text-slate-600">Jam Mulai</label>
                                    <input type="time" name="ACTIVE_FROM_TIME" value="{{ $from?->format('H:i') }}" required class="w-full min-w-0 rounded-lg border-slate-300 text-xs">
                                </div>
                                <div class="min-w-0">
                                    <label class="mb-1 block text-[11px] font-bold text-slate-600">Tanggal Berakhir</label>
                                    <input type="date" name="ACTIVE_UNTIL_DATE" value="{{ $until?->format('Y-m-d') }}" required class="w-full min-w-0 rounded-lg border-slate-300 text-xs">
                                </div>
                                <div class="min-w-0">
                                    <label class="mb-1 block text-[11px] font-bold text-slate-600">Jam Berakhir</label>
                                    <input type="time" name="ACTIVE_UNTIL_TIME" value="{{ $until?->format('H:i') }}" required class="w-full min-w-0 rounded-lg border-slate-300 text-xs">
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:col-span-2">
                                    <button type="submit" class="min-h-[44px] rounded-lg bg-slate-900 px-3 py-2 text-xs font-black text-white hover:bg-slate-800">Simpan</button>
                                    <button type="reset" class="min-h-[44px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700">Batal</button>
                                </div>
                            </form>
                        </details>

                        <div class="grid grid-cols-3 gap-2">
                            <a href="{{ route('attendance.qr.preview', $qr['QR_ID']) }}" class="min-w-0 rounded-lg bg-slate-100 px-2 py-2 text-center text-[10px] font-black text-slate-700 hover:bg-slate-200">Preview</a>
                            <a href="{{ route('attendance.qr.print', $qr['QR_ID']) }}" target="_blank" class="min-w-0 rounded-lg bg-blue-100 px-2 py-2 text-center text-[10px] font-black text-blue-700 hover:bg-blue-200">Cetak</a>
                            <a href="{{ route('attendance.qr.pdf', $qr['QR_ID']) }}" class="min-w-0 rounded-lg bg-red-100 px-2 py-2 text-center text-[10px] font-black text-red-700 hover:bg-red-200">PDF</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm font-bold text-slate-400 md:col-span-2 xl:col-span-3">Belum ada QR Presensi Permanen</div>
            @endforelse
        </div>
    </section>
</div>

@endsection
