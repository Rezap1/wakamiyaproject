@extends(!empty($isPrintMode) ? 'pdf.print_layout' : 'pdf.report_layout')

@section('content')
<div class="document-context">
    <table><tr>
        <td><strong>Ruang Lingkup</strong><br>{{ $scopeLabel ?? '-' }}</td>
        <td><strong>Filter</strong><br>{{ $filterLabel ?? 'Semua filter halaman' }}</td>
    </tr></table>
</div>
<table class="enterprise-table">
    <thead><tr>
        <th style="width:9%">Tanggal</th><th style="width:8%">Hari</th><th style="width:16%">Siswa</th>
        <th style="width:8%">No. Siswa</th><th style="width:14%">Kelas</th><th style="width:18%">Jadwal</th>
        <th style="width:8%">Masuk</th><th style="width:8%">Pulang</th><th style="width:10%">Status</th><th style="width:14%">Catatan</th>
    </tr></thead>
    <tbody>
    @forelse($records ?? collect() as $row)
        <tr>
            <td>{{ $row['date'] ? \App\Helpers\DateHelper::format($row['date'], 'd M Y') : '-' }}</td>
            <td>{{ $row['day'] ?? '-' }}</td><td>{{ $row['student_name'] ?? '-' }}</td><td>{{ $row['student_number'] ?? '-' }}</td>
            <td>{{ $row['class_name'] ?? '-' }}</td><td>{{ $row['schedule'] ?? '-' }}</td>
            <td>{{ $row['check_in'] ?? '-' }}</td><td>{{ $row['check_out'] ?? '-' }}</td>
            <td>{{ $row['status'] ?? '-' }}</td><td>{{ $row['notes'] ?? '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="empty-state">Belum ada data presensi sesuai filter.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
