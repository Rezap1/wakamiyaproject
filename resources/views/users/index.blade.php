@extends('layouts.app')

@section('header', 'Manajemen Pengguna')

@section('content')
<x-universal.index-layout 
    title="Pengguna Sistem" 
    description="Kelola akses administrator dan staf Anda."
    :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Pengguna' => route('users.index')]"
    add-action="{{ route('users.create') }}"
    add-text="Tambah Pengguna"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="users" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('users.index') }}" 
            refresh-url="{{ route('users.index') }}"
        >
            <div class="w-full md:w-auto">
                <select name="role" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role['Role_ID'] }}" {{ request('role') == $role['Role_ID'] ? 'selected' : '' }}>{{ $role['Role_Name'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="$users->isEmpty()" empty-title="Belum ada data pengguna" empty-description="Data pengguna belum ditambahkan.">
        <x-slot:header>
            <th class="px-6 py-4 text-left">User ID / Username</th>
            <th class="px-6 py-4 text-left">Nama Lengkap</th>
            <th class="px-6 py-4 text-left">Peran (Role)</th>
            <th class="px-6 py-4 text-left">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($users as $user)
            @php
                $status = ($user['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif';
                $badgeColor = $status === 'Aktif' ? 'green' : 'red';
                
                $roleName = $user['Role_ID'] ?? 'N/A';
                if (isset($roles) && isset($user['Role_ID'])) {
                    $role = collect($roles)->firstWhere('Role_ID', $user['Role_ID']);
                    if ($role) $roleName = $role['Role_Name'] ?? $user['Role_ID'];
                }
            @endphp
            <tr class="hover:bg-slate-50 transition-colors {{ $status === 'Nonaktif' ? 'opacity-50' : '' }}">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $user['User_ID'] ?? '-' }}</div>
                    <div class="text-xs text-slate-500">{{ $user['Username'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm border border-blue-100 shrink-0">
                            {{ substr($user['Full_Name'] ?? 'U', 0, 1) }}
                        </div>
                        <div>
                            <div class="font-bold text-slate-800">{{ $user['Full_Name'] ?? '-' }}</div>
                            <div class="text-xs font-medium text-slate-500 mt-0.5">{{ $user['Email'] ?? '-' }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4"><x-badge color="slate">{{ $roleName }}</x-badge></td>
                <td class="px-6 py-4"><x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge></td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="edit" url="{{ route('users.edit', $user['User_ID']) }}" />
                        <x-universal.action-button action="delete" url="{{ route('users.destroy', $user['User_ID']) }}" confirmMessage="Apakah Anda yakin ingin menghapus pengguna ini?" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($users, 'links'))
                <x-universal.pagination :paginator="$users" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>
</x-universal.index-layout>
@endsection
