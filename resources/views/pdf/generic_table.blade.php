@extends('pdf.' . (isset($isPrintMode) && $isPrintMode ? 'print_layout' : 'report_layout'))
@section('content')
@if(!empty($integrityIssues))
    <div style="border: 1px solid #b45309; padding: 8px; margin-bottom: 10px; color: #92400e;">
        <strong>Perlu rekonsiliasi data. Laporan belum dapat dinyatakan akurat.</strong>
        @foreach($integrityIssues as $issue)
            <div>{{ $issue }}</div>
        @endforeach
    </div>
@endif
<table class="enterprise-table">
    <thead>
        <tr>
            @foreach($headers ?? [] as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($records as $record)
            <tr>
                @php $mapped = isset($mapRow) ? $mapRow($record) : []; @endphp
                @foreach($mapped as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($headers ?? ['']) }}" class="empty-state">Tidak ada data yang tersedia.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
