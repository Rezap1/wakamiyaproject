@extends('layouts.app')

@section('header', 'Laporan Absensi')

@section('content')
<div class="space-y-6 pb-24 md:pb-8" x-data="attendanceReportFilters()">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="mb-5">
            <h1 class="text-xl font-black text-slate-900 sm:text-2xl">Laporan Absensi</h1>
            <p class="mt-1 text-sm text-slate-500">Pilih kelas dan periode untuk menampilkan rekap sebelum mengunduh PDF.</p>
        </div>

        <form method="GET" action="{{ route('attendance.reports.index') }}" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Kelas</span>
                    <select name="class_id" required class="min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Pilih kelas</option>
                        @foreach($classes as $class)
                            @php
                                $classId = trim((string) ($class['Class_ID'] ?? ''));
                                $className = trim((string) ($class['Class_Name'] ?? $classId));
                                $classCode = trim((string) ($class['Class_Code'] ?? ''));
                            @endphp
                            <option value="{{ $classId }}" @selected(request('class_id') === $classId)>
                                {{ $className }}{{ $classCode !== '' ? ' (' . $classCode . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if($classes->isEmpty())
                        <span class="mt-1 block text-xs font-semibold text-amber-700">Tidak ada kelas aktif dalam ruang lingkup akun Anda.</span>
                    @endif
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Jenis Rekap</span>
                    <select name="report_type" x-model="type" required class="min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="harian">Harian</option>
                        <option value="mingguan">Mingguan</option>
                        <option value="bulanan">Bulanan</option>
                        <option value="rentang">Rentang Tanggal</option>
                    </select>
                </label>
            </div>

            <div x-show="type === 'harian'" x-cloak class="max-w-md">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal</span>
                    <input type="date" name="daily_date" value="{{ $defaults['daily_date'] }}" :required="type === 'harian'" class="min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
            </div>

            <div x-show="type === 'mingguan'" x-cloak class="max-w-md">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Acuan</span>
                    <input type="date" name="weekly_anchor" x-model="weeklyAnchor" :required="type === 'mingguan'" class="min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
                <p class="mt-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800" x-text="weeklyLabel"></p>
            </div>

            <div x-show="type === 'bulanan'" x-cloak class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Bulan</span>
                    <select name="month" :required="type === 'bulanan'" class="min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm focus:border-sky-500 focus:ring-sky-500">
                        @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $number => $month)
                            <option value="{{ $number + 1 }}" @selected((int) $defaults['month'] === $number + 1)>{{ $month }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Tahun</span>
                    <input type="number" name="year" min="2000" max="2100" value="{{ $defaults['year'] }}" :required="type === 'bulanan'" class="min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
            </div>

            <div x-show="type === 'rentang'" x-cloak class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Mulai</span>
                    <input type="date" name="date_start" value="{{ $defaults['date_start'] }}" :required="type === 'rentang'" class="min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Selesai</span>
                    <input type="date" name="date_end" value="{{ $defaults['date_end'] }}" :required="type === 'rentang'" class="min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-sky-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50" @disabled($classes->isEmpty())>
                    Tampilkan Rekap
                </button>
                <a href="{{ route('attendance.reports.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </div>

    @if($report)
        <section class="space-y-5" aria-label="Hasil laporan absensi">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-sky-600">{{ $report['title'] }}</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">{{ $report['class']['name'] }}</h2>
                        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                            <dt class="font-semibold text-slate-500">Jenis Rekap</dt><dd class="font-bold text-slate-800">{{ $report['report_type_label'] }}</dd>
                            <dt class="font-semibold text-slate-500">Periode</dt><dd class="font-bold text-slate-800">{{ $report['period']['label'] }}</dd>
                            <dt class="font-semibold text-slate-500">Jumlah Siswa</dt><dd class="font-bold text-slate-800">{{ $report['student_count'] }}</dd>
                        </dl>
                    </div>
                    <a href="{{ route('attendance.reports.pdf', request()->query()) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-rose-700">
                        Unduh PDF
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Data</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $report['total_records'] }}</p>
                </div>
                @foreach($report['summary'] as $status => $item)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $item['count'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-900">{{ $report['absence_note'] }}</p>

            @if($report['report_type'] !== 'harian')
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
                        <h3 class="font-black text-slate-900">Rekap Per Siswa</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[760px] w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">No</th><th class="px-4 py-3">Nama Siswa</th><th class="px-4 py-3">ID/NIS</th>
                                    @foreach($report['status_labels'] as $label)<th class="px-4 py-3 text-center">{{ $label }}</th>@endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($report['student_recap'] as $index => $student)
                                    <tr><td class="px-4 py-3">{{ $index + 1 }}</td><td class="px-4 py-3 font-bold text-slate-800">{{ $student['student_name'] }}</td><td class="px-4 py-3">{{ $student['student_number'] }}</td>
                                        @foreach($report['status_labels'] as $status => $label)<td class="px-4 py-3 text-center font-semibold">{{ $student['counts'][$status] }}</td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
                    <h3 class="font-black text-slate-900">Detail Absensi</h3>
                </div>
                @if($report['rows']->isEmpty())
                    <div class="px-6 py-12 text-center text-sm font-semibold text-slate-500">Tidak ada data absensi pada periode yang dipilih.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-[900px] w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                                <tr><th class="px-4 py-3">No</th><th class="px-4 py-3">Nama Siswa</th><th class="px-4 py-3">ID/NIS</th><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Jam Masuk</th><th class="px-4 py-3">Jam Keluar</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Keterangan</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($report['rows'] as $index => $row)
                                    <tr><td class="px-4 py-3">{{ $index + 1 }}</td><td class="px-4 py-3 font-bold text-slate-800">{{ $row['student_name'] }}</td><td class="px-4 py-3">{{ $row['student_number'] }}</td><td class="px-4 py-3 whitespace-nowrap">{{ \App\Helpers\DateHelper::format($row['date'], 'd M Y') }}</td><td class="px-4 py-3">{{ $row['check_in'] }}</td><td class="px-4 py-3">{{ $row['check_out'] }}</td><td class="px-4 py-3 font-semibold">{{ $row['status'] }}</td><td class="px-4 py-3">{{ $row['notes'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>

<script>
function attendanceReportFilters() {
    return {
        type: @js($defaults['report_type']),
        weeklyAnchor: @js($defaults['weekly_anchor']),
        get weeklyLabel() {
            if (!this.weeklyAnchor) return 'Pilih tanggal acuan untuk melihat rentang Senin-Minggu.';
            const anchor = new Date(this.weeklyAnchor + 'T12:00:00');
            const day = anchor.getDay();
            const monday = new Date(anchor);
            monday.setDate(anchor.getDate() - (day === 0 ? 6 : day - 1));
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            const format = date => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
            return 'Periode: Senin, ' + format(monday) + ' s.d. Minggu, ' + format(sunday);
        }
    };
}
</script>
@endsection
