@extends('pdf.report_layout')

@section('content')
    <table class="enterprise-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Employee ID</th>
                <th width="20%">Nama Lengkap</th>
                <th width="15%">Departemen</th>
                <th width="15%">Jabatan</th>
                <th width="10%">Status Karyawan</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $index => $emp)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $emp['Employee_ID'] ?? '-' }}</td>
                    <td>{{ $emp['Full_Name'] ?? '-' }}</td>
                    <td>{{ $emp['Department_Name'] ?? '-' }}</td>
                    <td>{{ $emp['Position_Name'] ?? '-' }}</td>
                    <td>{{ $emp['Employment_Status'] ?? '-' }}</td>
                    <td>
                        {{ (isset($emp['Is_Active']) && $emp['Is_Active'] === 'TRUE') ? 'Aktif' : 'Non-Aktif' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada data pegawai tersedia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
