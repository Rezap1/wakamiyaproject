@extends('pdf.report_layout')

@section('content')
    <table class="enterprise-table">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th width="20%">Position ID</th>
                <th width="25%">Nama Jabatan</th>
                <th width="25%">Departemen</th>
                <th width="20%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($positions as $index => $pos)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $pos['Position_ID'] ?? '-' }}</td>
                    <td>{{ $pos['Position_Name'] ?? '-' }}</td>
                    <td>{{ $pos['Department_Name'] ?? '-' }}</td>
                    <td>
                        {{ (isset($pos['Is_Active']) && $pos['Is_Active'] === 'TRUE') ? 'Aktif' : 'Non-Aktif' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Tidak ada data jabatan tersedia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
