@extends('pdf.' . (isset($isPrintMode) && $isPrintMode ? 'print_layout' : 'report_layout'))
@section('content')
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
            <tr><td colspan="{{ count($headers ?? ['']) }}" style="text-align:center;">No data available</td></tr>
        @endforelse
    </tbody>
</table>
@endsection