@extends('pdf.report_layout')

@section('content')
    <table class="enterprise-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Teacher ID</th>
                <th width="20%">Nama Guru</th>
                <th width="15%">NUPTK</th>
                <th width="15%">Spesialisasi</th>
                <th width="10%">Status Mengajar</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($teachers as $index => $teacher)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $teacher['Teacher_ID'] ?? '-' }}</td>
                    <td>{{ $teacher['Full_Name'] ?? '-' }}</td>
                    <td>{{ $teacher['NUPTK'] ?? '-' }}</td>
                    <td>{{ $teacher['Specialization'] ?? '-' }}</td>
                    <td>{{ $teacher['Teaching_Status'] ?? '-' }}</td>
                    <td>
                        {{ (isset($teacher['Is_Active']) && $teacher['Is_Active'] === 'TRUE') ? 'Aktif' : 'Non-Aktif' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada data guru tersedia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
