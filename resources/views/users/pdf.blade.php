@extends('pdf.report_layout')

@section('content')
    <table class="enterprise-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Nama Lengkap</th>
                <th width="20%">Email</th>
                <th width="15%">Username</th>
                <th width="15%">Role</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $index => $user)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $user['Full_Name'] ?? '-' }}</td>
                    <td>{{ $user['Email'] ?? '-' }}</td>
                    <td>{{ $user['Username'] ?? '-' }}</td>
                    <td>
                        @php
                            $roleName = '-';
                            if (isset($user['Role_ID']) && isset($roles[$user['Role_ID']])) {
                                $roleName = $roles[$user['Role_ID']]['Role_Name'];
                            }
                        @endphp
                        {{ $roleName }}
                    </td>
                    <td>
                        {{ (isset($user['Is_Active']) && $user['Is_Active'] === 'TRUE') ? 'Aktif' : 'Non-Aktif' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">Tidak ada data pengguna tersedia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
