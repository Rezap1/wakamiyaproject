@extends(!empty($isPrintMode) ? 'pdf.print_layout' : 'pdf.report_layout')

@section('content')
<div class="document-context">
    <table><tr>
        <td><strong>Guru</strong><br>{{ $teacherName ?? '-' }}</td>
        <td><strong>Ruang Lingkup</strong><br>{{ $scopeLabel ?? 'Seluruh scope pengajaran terotorisasi' }}</td>
    </tr></table>
</div>

@if(($records ?? collect())->isEmpty())
    <table class="enterprise-table"><tr><td class="empty-state">Belum ada data nilai.</td></tr></table>
@else
    @foreach($records->groupBy('student_name') as $studentName => $studentRows)
        <div style="font-weight:bold; font-size:11px; margin:8px 0 4px;">{{ $studentName }}</div>
        <table class="enterprise-table">
            <thead><tr>
                <th style="width:10%">Tanggal</th><th style="width:16%">Kelas</th><th style="width:16%">Mata Pelajaran</th>
                <th style="width:12%">Kategori</th><th style="width:17%">Penilaian</th><th style="width:7%">Nilai</th>
                <th style="width:8%">Grade</th><th style="width:8%">Status</th><th style="width:16%">Detail</th>
            </tr></thead>
            <tbody>
            @foreach($studentRows as $row)
                <tr>
                    <td>{{ \App\Helpers\DateHelper::format($row['date'] ?? null, 'd M Y') }}</td>
                    <td>{{ $row['class_name'] ?? '-' }}</td><td>{{ $row['subject_name'] ?? '-' }}</td>
                    <td>{{ $row['category'] ?? '-' }}</td><td>{{ $row['title'] ?? '-' }}</td>
                    <td>{{ $row['score'] ?? '-' }}</td><td>{{ $row['grade'] ?? '-' }}</td><td>{{ $row['status'] ?? '-' }}</td>
                    <td>{{ $row['details'] ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
@endif
@endsection
