@extends('pdf.' . (isset($isPrintMode) && $isPrintMode ? 'print_layout' : 'report_layout'))

@section('content')
    <table class="document-context">
        <tr>
            <td><strong>Identitas Siswa</strong><br>
                Nama: {{ $student['Full_Name'] ?? '-' }}<br>
                Nomor Siswa: {{ $student['Student_Number'] ?? $student['NIS'] ?? '-' }}
            </td>
            <td><strong>Ruang Lingkup</strong><br>
                {{ $scopeLabel ?? 'Siswa terautentikasi' }}<br>
                Rata-rata Nilai: <strong>{{ ($averageScore ?? null) !== null ? $averageScore : '-' }}</strong>
            </td>
        </tr>
    </table>

    <table class="enterprise-table score-table">
        <thead><tr>
            <th style="width: 12%;">Tanggal</th>
            <th style="width: 18%;">Kategori</th>
            <th style="width: 38%;">Penilaian</th>
            <th style="width: 32%;">Nilai / Hasil</th>
        </tr></thead>
        <tbody>
        @forelse($records ?? [] as $record)
            <tr>
                <td>{{ \App\Helpers\DateHelper::format($record[0] ?? null, 'd F Y') }}</td>
                <td>{{ $record[1] ?? '-' }}</td>
                <td>{{ $record[2] ?? 'Penilaian tidak ditemukan' }}</td>
                <td>{{ $record[3] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty-state">Belum ada riwayat nilai.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
