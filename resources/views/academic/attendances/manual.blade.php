@extends('layouts.app')

@section('header', 'Absensi Manual MASTER')

@section('content')
<div
    class="mx-auto max-w-6xl space-y-6"
    x-data="masterManualAttendance({
        classes: @js($classes ?? []),
        batchId: @js(old('Batch_ID', '')),
        classId: @js(old('Class_ID', '')),
        attendanceDate: @js(old('Attendance_Date', now('Asia/Jakarta')->toDateString())),
        selected: @js(old('Student_IDs', [])),
    })"
>
    <x-page-header
        title="Absensi Manual MASTER"
        description="Jalur cadangan presensi harian ketika scan QR siswa mengalami kendala. Hanya status Hadir yang dapat dicatat; Izin dan Sakit tetap melalui pengajuan dan approval."
        :breadcrumbs="['Dasbor' => route('dashboard'), 'Presensi Akademik' => route('attendances.index'), 'Absensi Manual' => '#']"
    />

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700" role="status">
            {{ session('success') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('attendances.manual.store') }}"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        @submit="if (!window.confirm(`Catat ${selected.length} siswa sebagai Hadir melalui jalur manual?`)) { $event.preventDefault(); }"
    >
        @csrf
        <input type="hidden" name="Attendance_Type" value="CLASS_QR">
        <input type="hidden" name="Status" value="PRESENT">

        <div class="border-b border-slate-100 bg-slate-50/70 p-5 sm:p-6">
            <h2 class="text-lg font-black text-slate-800">Konteks Sesi</h2>
            <p class="mt-1 text-sm text-slate-600">
                Kontrak QR siswa WMS memakai sesi harian kelas (<code>CLASS_QR</code>), bukan jadwal mata pelajaran. Batch, kelas, dan tanggal diverifikasi ulang oleh server.
            </p>
        </div>

        <div class="space-y-6 p-5 sm:p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="Batch_ID" class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-600">Batch *</label>
                    <select id="Batch_ID" name="Batch_ID" x-model="batchId" @change="classId = ''; resetRoster()" required class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Pilih batch</option>
                        @foreach(($batches ?? []) as $batch)
                            <option value="{{ $batch['Batch_ID'] }}">{{ $batch['Batch_Name'] ?? $batch['Batch_Code'] ?? $batch['Batch_ID'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="Class_ID" class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-600">Kelas *</label>
                    <select id="Class_ID" name="Class_ID" x-model="classId" @change="fetchRoster" required class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Pilih kelas</option>
                        <template x-for="row in filteredClasses" :key="row.Class_ID">
                            <option :value="row.Class_ID" x-text="row.Class_Name || row.Class_Code || row.Class_ID"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="Attendance_Date" class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-600">Tanggal *</label>
                    <input id="Attendance_Date" name="Attendance_Date" type="date" x-model="attendanceDate" @change="fetchRoster" max="{{ now('Asia/Jakarta')->toDateString() }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-600">Sesi</label>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-800">Presensi Harian / QR Kelas</div>
                </div>
            </div>

            <div>
                <label for="Manual_Reason" class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-600">Alasan Pencatatan Manual *</label>
                <textarea id="Manual_Reason" name="Manual_Reason" rows="3" minlength="5" maxlength="500" required class="w-full rounded-xl border-slate-200 text-sm" placeholder="Contoh: Kamera perangkat siswa bermasalah saat QR aktif.">{{ old('Manual_Reason') }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Alasan, akun MASTER, waktu, dan sumber manual disimpan pada audit trail.</p>
            </div>

            <div x-show="loading" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-sm font-semibold text-slate-500">
                Memeriksa status resmi dan pengajuan izin/sakit…
            </div>

            <div x-show="errorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700" x-text="errorMessage"></div>

            <section x-show="eligible.length > 0 && !loading" x-cloak class="space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-black text-slate-800">Belum Memiliki Status</h3>
                        <p class="text-xs text-slate-500">Pilih hanya siswa yang benar-benar hadir.</p>
                    </div>
                    <button type="button" @click="toggleAll" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50" x-text="allSelected ? 'Batalkan semua' : 'Pilih semua'"></button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-4 py-3">Pilih</th><th class="px-4 py-3">Nomor Siswa</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Status yang dicatat</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="student in eligible" :key="student.Student_ID">
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="Student_IDs[]" :value="student.Student_ID" x-model="selected" class="rounded border-slate-300 text-blue-600"></td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500" x-text="student.Student_Number || '-' "></td>
                                    <td class="px-4 py-3 font-bold text-slate-800" x-text="student.Full_Name"></td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">Hadir</span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <section x-show="blocked.length > 0 && !loading" x-cloak class="space-y-3">
                <div>
                    <h3 class="font-black text-slate-800">Tidak Dapat Dicatat Manual</h3>
                    <p class="text-xs text-slate-500">Siswa berikut sengaja dikeluarkan dari pilihan dan akan diverifikasi ulang saat submit.</p>
                </div>
                <div class="overflow-x-auto rounded-xl border border-amber-200">
                    <table class="min-w-full divide-y divide-amber-100 text-sm">
                        <thead class="bg-amber-50 text-left text-xs uppercase tracking-wide text-amber-700">
                            <tr><th class="px-4 py-3">Siswa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Alasan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            <template x-for="student in blocked" :key="`${student.Student_ID}-${student.source}`">
                                <tr>
                                    <td class="px-4 py-3 font-bold text-slate-800" x-text="student.Full_Name"></td>
                                    <td class="px-4 py-3 text-amber-800" x-text="student.status_label"></td>
                                    <td class="px-4 py-3 text-slate-600" x-text="student.reason"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <div x-show="loaded && eligible.length === 0 && !loading" x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-600">
                Tidak ada siswa yang dapat dicatat manual untuk konteks ini.
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 p-5 sm:flex-row sm:justify-end">
            <a href="{{ route('attendances.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-center text-sm font-bold text-slate-600">Kembali</a>
            <button type="submit" :disabled="selected.length === 0 || loading" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">
                Simpan <span x-text="selected.length"></span> Kehadiran
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('masterManualAttendance', (config) => ({
        classes: config.classes || [],
        batchId: config.batchId || '',
        classId: config.classId || '',
        attendanceDate: config.attendanceDate,
        selected: (config.selected || []).map(String),
        eligible: [],
        blocked: [],
        loading: false,
        loaded: false,
        errorMessage: '',

        init() {
            if (this.batchId && this.classId && this.attendanceDate) this.fetchRoster();
        },

        get filteredClasses() {
            return this.classes.filter(row => String(row.Batch_ID || '') === String(this.batchId));
        },

        get allSelected() {
            return this.eligible.length > 0 && this.eligible.every(row => this.selected.includes(String(row.Student_ID)));
        },

        resetRoster() {
            this.eligible = [];
            this.blocked = [];
            this.selected = [];
            this.loaded = false;
            this.errorMessage = '';
        },

        toggleAll() {
            this.selected = this.allSelected ? [] : this.eligible.map(row => String(row.Student_ID));
        },

        async fetchRoster() {
            const previousSelection = [...this.selected];
            this.resetRoster();
            if (!this.batchId || !this.classId || !this.attendanceDate) return;
            this.loading = true;
            try {
                const query = new URLSearchParams({
                    batch_id: this.batchId,
                    class_id: this.classId,
                    attendance_date: this.attendanceDate,
                });
                const response = await fetch(`{{ route('attendances.manual.roster') }}?${query}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'Daftar siswa tidak dapat dimuat.');
                this.eligible = body.eligible || [];
                this.blocked = body.blocked || [];
                this.selected = previousSelection.filter(id => this.eligible.some(row => String(row.Student_ID) === String(id)));
                this.loaded = true;
            } catch (error) {
                this.errorMessage = error.message || 'Daftar siswa tidak dapat dimuat.';
            } finally {
                this.loading = false;
            }
        },
    }));
});
</script>
@endsection
