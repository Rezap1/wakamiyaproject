@extends('pdf.report_layout')

@section('content')
    <table class="enterprise-table">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th width="20%">Department ID</th>
                <th width="35%">Nama Departemen</th>
                <th width="15%">Head of Dept</th>
                <th width="20%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($departments as $index => $dept)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $dept['Department_ID'] ?? '-' }}</td>
                    <td>{{ $dept['Department_Name'] ?? '-' }}</td>
                    <td>{{ $dept['Head_of_Department'] ?? '-' }}</td>
                    <td>
                        {{ (isset($dept['Is_Active']) && $dept['Is_Active'] === 'TRUE') ? 'Aktif' : 'Non-Aktif' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Tidak ada data departemen tersedia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
