@extends('layouts.app')

@section('header', 'Edit Pengguna')

@section('content')
<div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
    <!-- Decorative Header -->
    <div class="h-2 bg-gradient-to-r from-primary-400 to-primary-600 absolute top-0 w-full"></div>
    
    <div class="p-8 border-b border-gray-100 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900">Ubah Detail Pengguna</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Mengubah data pengguna ID: <span class="font-bold">{{ $user['User_ID'] }}</span></p>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold {{ ($user['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                {{ ($user['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Status: Aktif' : 'Status: Nonaktif' }}
            </span>
        </div>
    </div>

    <form action="{{ route('users.update', $user['User_ID']) }}" method="POST" class="p-8 space-y-7">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <div>
                <label for="Username" class="block text-sm font-bold text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                <input type="text" name="Username" id="Username" required value="{{ old('Username', $user['Username'] ?? '') }}" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="Username unik">
            </div>

            <div>
                <label for="Full_Name" class="block text-sm font-bold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="Full_Name" id="Full_Name" required value="{{ old('Full_Name', $user['Full_Name'] ?? '') }}" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="Masukkan nama lengkap...">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <div>
                <label for="Email" class="block text-sm font-bold text-gray-700 mb-2">Alamat Email <span class="text-red-500">*</span></label>
                <input type="email" name="Email" id="Email" required value="{{ old('Email', $user['Email'] ?? '') }}" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="contoh@wakamiya.co.id">
            </div>

            <div>
                <label for="Password" class="block text-sm font-bold text-gray-700 mb-2">Kata Sandi Baru</label>
                <input type="password" name="Password" id="Password" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="Kosongkan jika tidak ingin mengubah sandi">
                <p class="text-xs text-gray-400 mt-1">Hanya isi jika ingin mereset kata sandi (min 8 karakter).</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
            <div>
                <label for="Role_ID" class="block text-sm font-bold text-gray-700 mb-2">Peran (Role) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select name="Role_ID" id="Role_ID" required class="block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white appearance-none cursor-pointer">
                        <option value="" disabled>Pilih peran akses...</option>
                        @foreach($roles as $role)
                            @if(isset($role['Role_ID']))
                                <option value="{{ $role['Role_ID'] }}" {{ old('Role_ID', $user['Role_ID'] ?? '') == $role['Role_ID'] ? 'selected' : '' }}>
                                    {{ $role['Role_Name'] ?? $role['Role_ID'] }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>
            </div>

            <div>
                <label for="Employee_ID" class="block text-sm font-bold text-gray-700 mb-2">ID Karyawan</label>
                <input type="text" name="Employee_ID" id="Employee_ID" value="{{ old('Employee_ID', $user['Employee_ID'] ?? '') }}" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="Opsional (mis. EMP000001)">
            </div>

            <div>
                <label for="Is_Active" class="block text-sm font-bold text-gray-700 mb-2">Status Akun</label>
                <div class="relative">
                    <select name="Is_Active" id="Is_Active" required class="block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white appearance-none cursor-pointer">
                        <option value="TRUE" {{ old('Is_Active', $user['Is_Active'] ?? '') == 'TRUE' ? 'selected' : '' }}>Aktif</option>
                        <option value="FALSE" {{ old('Is_Active', $user['Is_Active'] ?? '') == 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Khusus</label>
            <textarea name="Notes" id="Notes" rows="3" class="appearance-none block w-full px-4 py-3.5 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 text-base font-medium transition-colors bg-gray-50 hover:bg-white" placeholder="Catatan tambahan opsional...">{{ old('Notes', $user['Notes'] ?? '') }}</textarea>
        </div>

        <div class="pt-8 border-t border-gray-100 flex justify-end space-x-4">
            <a href="{{ route('users.index') }}" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Perbarui Pengguna
            </button>
        </div>
    </form>
</div>
@endsection
