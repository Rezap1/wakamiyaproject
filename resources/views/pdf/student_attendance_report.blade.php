@extends('pdf.' . (isset($isPrintMode) && $isPrintMode ? 'print_layout' : 'report_layout'))

@section('content')
    @php($attendanceSummary = $attendanceSummary ?? [])
    <table class="document-context">
        <tr>
            <td><strong>Identitas Siswa</strong><br>
                Nama: {{ $student['Full_Name'] ?? '-' }}<br>
                Nomor Siswa: {{ $student['Student_Number'] ?? $student['NIS'] ?? '-' }}
            </td>
            <td><strong>Periode & Ruang Lingkup</strong><br>
                {{ $scopeLabel ?? 'Siswa terautentikasi' }}<br>
                Periode: {{ \App\Helpers\DateHelper::format($attendanceSummary['from'] ?? null, 'd F Y') }}
                s.d. {{ \App\Helpers\DateHelper::format($attendanceSummary['to'] ?? null, 'd F Y') }}
            </td>
        </tr>
    </table>

    <table class="summary-cards">
        <tr>
            <td><span>Hadir</span><strong>{{ $attendanceSummary['present'] ?? 0 }}</strong></td>
            <td><span>Terlambat</span><strong>{{ $attendanceSummary['late'] ?? 0 }}</strong></td>
            <td><span>Izin/Sakit</span><strong>{{ $attendanceSummary['excused'] ?? 0 }}</strong></td>
            <td><span>Alpa</span><strong>{{ $attendanceSummary['absent'] ?? 0 }}</strong></td>
        </tr>
    </table>

    <table class="enterprise-table attendance-table">
        <thead><tr><th style="width: 35%;">Tanggal</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($records ?? [] as $record)
            <tr>
                <td>{{ \App\Helpers\DateHelper::format($record[0] ?? null, 'd F Y') }}</td>
                <td>{{ $record[1] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="empty-state">Belum ada riwayat kehadiran.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
