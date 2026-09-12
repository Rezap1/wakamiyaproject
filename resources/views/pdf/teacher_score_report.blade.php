@extends(!empty($isPrintMode) ? 'pdf.print_layout' : 'pdf.report_layout')

@section('content')
@php
    $reportRecords = collect($records ?? []);
    $groups = collect($studentGroups ?? []);
    $mode = $reportMode ?? '';
    if ($groups->isEmpty() && $reportRecords->isNotEmpty()) {
        $groups = $reportRecords->groupBy(fn ($row) => $row['student_id'] ?? $row['student_name'] ?? 'Siswa')
            ->map(function ($studentRows) {
                $first = (array) $studentRows->first();
                return [
                    'student_id' => $first['student_id'] ?? '-',
                    'student_name' => $first['student_name'] ?? 'Siswa',
                    'student_number' => $first['student_number'] ?? '-',
                    'class_name' => $first['class_name'] ?? '-',
                    'records' => $studentRows->values(),
                ];
            })->values();
    }
@endphp

<style>
    .score-context { width: 100%; border-collapse: collapse; margin: 0 0 13px; border: 1px solid #cbd5e1; background: #f8fafc; }
    .score-context td { padding: 7px 9px; vertical-align: top; font-size: 9px; }
    .student-section { margin: 0 0 18px; page-break-inside: auto; }
    .student-heading { page-break-after: avoid; background: #17365d; color: #ffffff; padding: 9px 11px; border-left: 5px solid #e8b44f; }
    .student-name { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: .2px; }
    .student-meta { margin-top: 3px; font-size: 8.7px; color: #e2e8f0; }
    .subject-heading { page-break-after: avoid; margin: 8px 0 4px; padding: 5px 8px; border-left: 3px solid #2563eb; background: #eaf2ff; color: #17365d; font-size: 10px; font-weight: bold; }
    .score-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; page-break-inside: auto; }
    .score-table thead { display: table-header-group; }
    .score-table tr { page-break-inside: avoid; }
    .score-table th { background: #dce6f1; color: #17365d; border: .5px solid #9fb3c8; padding: 7px 6px; text-align: left; font-size: 8.5px; text-transform: uppercase; }
    .score-table td { border: .5px solid #cbd5e1; padding: 7px 6px; vertical-align: top; font-size: 9px; line-height: 1.42; overflow-wrap: anywhere; word-wrap: break-word; }
    .score-table .score-value { text-align: center; font-size: 12px; font-weight: bold; color: #17365d; }
    .score-table .grade-status { text-align: center; }
    .score-table .detail-row td { background: #f8fafc; padding: 7px 9px 9px; color: #334155; }
    .detail-title { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 3px; }
    .detail-line { display: inline-block; margin: 0 10px 3px 0; }
    .notes { margin-top: 4px; padding-top: 4px; border-top: .5px solid #e2e8f0; font-style: italic; white-space: normal; overflow-wrap: anywhere; word-wrap: break-word; }
    .no-score { border: 1px dashed #94a3b8; background: #f8fafc; color: #64748b; text-align: center; font-style: italic; padding: 13px; }
    .report-empty { border: 1px solid #cbd5e1; background: #f8fafc; color: #64748b; text-align: center; font-style: italic; padding: 18px; }
</style>

<table class="score-context">
    @if(($reportMode ?? '') === 'student')
        <tr>
            <td><strong>Nama Guru</strong><br>{{ $teacherName ?? '-' }}</td>
            <td><strong>Nama Siswa</strong><br>{{ data_get($selectedStudent ?? [], 'student_name', '-') }}</td>
            <td><strong>ID Siswa</strong><br>{{ data_get($selectedStudent ?? [], 'student_id', '-') }}</td>
        </tr>
        <tr><td colspan="3"><strong>Ruang Lingkup Guru</strong><br>{{ $teachingScopeLabel ?? '-' }}</td></tr>
    @elseif(($reportMode ?? '') === 'class')
        <tr>
            <td><strong>Nama Guru</strong><br>{{ $teacherName ?? '-' }}</td>
            <td><strong>Kelas</strong><br>{{ $selectedClassName ?? '-' }}</td>
            <td><strong>Tanggal Nilai</strong><br>{{ \App\Helpers\DateHelper::format($reportDate ?? null, 'd F Y') }}</td>
        </tr>
        <tr>
            <td><strong>Jumlah Siswa</strong><br>{{ $studentCount ?? $groups->count() }}</td>
            <td colspan="2"><strong>Ruang Lingkup Guru</strong><br>{{ $teachingScopeLabel ?? '-' }}</td>
        </tr>
    @else
        <tr>
            <td><strong>Guru</strong><br>{{ $teacherName ?? '-' }}</td>
            <td><strong>Ruang Lingkup</strong><br>{{ $scopeLabel ?? 'Seluruh scope pengajaran terotorisasi' }}</td>
        </tr>
    @endif
</table>

@if($reportRecords->isEmpty() && ($reportMode ?? '') !== 'student')
    <div class="report-empty">{{ $emptyMessage ?? 'Belum ada data nilai.' }}</div>
@elseif($groups->isEmpty())
    <div class="report-empty">{{ $emptyMessage ?? 'Belum ada data nilai.' }}</div>
@else
    @foreach($groups as $group)
        @php($studentRows = collect($group['records'] ?? []))
        <div class="student-section">
            <div class="student-heading">
                <div class="student-name">{{ $group['student_name'] ?? 'Siswa' }}</div>
                <div class="student-meta">
                    ID: {{ $group['student_id'] ?? '-' }}
                    &nbsp; | &nbsp; NIS: {{ $group['student_number'] ?? '-' }}
                    &nbsp; | &nbsp; Kelas: {{ $group['class_name'] ?? '-' }}
                </div>
            </div>

            @if($studentRows->isEmpty())
                <div class="no-score">{{ $reportRecords->isEmpty() ? ($emptyMessage ?? 'Belum ada data nilai.') : 'Belum ada nilai' }}</div>
            @else
                @foreach($studentRows->groupBy(function ($row) use ($mode) {
                    $subject = $row['subject_name'] ?? 'Mata Pelajaran';
                    return $mode === 'student' ? ($row['class_name'] ?? '-') . ' | ' . $subject : $subject;
                }) as $subjectHeading => $subjectRows)
                    <div class="subject-heading">{{ $subjectHeading }}</div>
                    <table class="score-table">
                        <thead><tr>
                            <th style="width:14%">Tanggal</th><th style="width:18%">Kategori</th>
                            <th style="width:34%">Penilaian</th><th style="width:12%">Nilai</th><th style="width:22%">Grade / Status</th>
                        </tr></thead>
                        <tbody>
                        @foreach($subjectRows as $row)
                            <tr>
                                <td>{{ \App\Helpers\DateHelper::format($row['date'] ?? null, 'd M Y') }}</td>
                                <td>{{ $row['category'] ?? '-' }}</td><td>{{ $row['title'] ?? '-' }}</td>
                                <td class="score-value">{{ $row['score'] ?? '-' }}</td>
                                <td class="grade-status"><strong>{{ $row['grade'] ?? '-' }}</strong><br>{{ $row['status'] ?? '-' }}</td>
                            </tr>
                            @if(!empty($row['detail_lines']) || !empty($row['notes']) || (!empty($row['details']) && ($row['details'] ?? '-') !== '-'))
                                <tr class="detail-row"><td colspan="5">
                                    <div class="detail-title">Detail penilaian</div>
                                    @forelse($row['detail_lines'] ?? [] as $detail)
                                        <span class="detail-line"><strong>{{ $detail['label'] ?? 'Detail' }}:</strong> {{ $detail['value'] ?? '-' }}</span>
                                    @empty
                                        @if(!empty($row['details']) && ($row['details'] ?? '-') !== '-')<span>{{ $row['details'] }}</span>@endif
                                    @endforelse
                                    @if(!empty($row['notes']))<div class="notes"><strong>Catatan:</strong> {{ $row['notes'] }}</div>@endif
                                </td></tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endif
        </div>
    @endforeach
@endif
@endsection
