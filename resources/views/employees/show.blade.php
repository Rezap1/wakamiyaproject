@extends('layouts.app')
@section('header', 'Detail Profil Karyawan')
@section('content')

@php
    $isActive = ($employee['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Pegawai Aktif' : 'Nonaktif';
    $photoUrl = !empty($employee['Profile_Photo']) ? $employee['Profile_Photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($employee['Full_Name'] ?? 'Emp') . '&background=0D8ABC&color=fff&size=256';
    $completeness = $employee['Completeness_Score'] ?? 0;
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <!-- SECURITY & AUTHORIZATION BANNER -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl {{ $isAuthorized ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-amber-50 text-amber-600 border border-amber-200' }} flex items-center justify-center font-bold shrink-0">
                @if($isAuthorized)
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                @endif
            </div>
            <div>
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Otorisasi Keamanan Data Sensitif (B5 EPS)</h4>
                <p class="text-xs text-slate-500">
                    @if($isAuthorized)
                        <span class="text-emerald-600 font-bold">AKSES PENUH (AUTHORIZED):</span> Anda memiliki hak akses Administrator/HR untuk melihat NIK, NPWP, dan No. Rekening secara utuh.
                    @else
                        <span class="text-amber-600 font-bold">AKSES TERBATAS (MASKED):</span> NIK, NPWP, dan No. Rekening di-masking secara server-side sesuai kebijakan keamanan RBAC.
                    @endif
                </p>
            </div>
        </div>

        <!-- EMAIL ACTION FORM -->
        <form action="{{ route('employees.send-email', $employee['Employee_ID']) }}" method="POST" class="flex items-center gap-2 w-full sm:w-auto">
            @csrf
            <input type="email" name="email" value="{{ $employee['Email'] ?? '' }}" placeholder="Email Tujuan..." required class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            <button type="submit" class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-all shadow-sm flex items-center gap-1.5 shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                Kirim Email
            </button>
        </form>
    </div>

    <!-- MAIN DETAIL LAYOUT -->
    <x-universal.detail-layout 
        title="{{ $employee['Full_Name'] }}" 
        description="NIK: {{ $employee['Employee_Number'] }} | {{ $employee['Position_Name'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard'), 'Data Master' => '#', 'Karyawan' => route('employees.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('employees.edit', $employee['Employee_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('employees.destroy', $employee['Employee_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <!-- SECTION F: PROFILE PHOTO & COMPLETENESS SCORE -->
                <div class="bg-gradient-to-r from-slate-50 to-blue-50/30 p-5 rounded-2xl border border-slate-200/80 flex flex-col md:flex-row items-center gap-6">
                    <div class="relative w-28 h-28 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-200 shrink-0">
                        <img src="{{ $photoUrl }}" alt="Foto {{ $employee['Full_Name'] }}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 w-full space-y-2 text-center md:text-left">
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-2">
                            <h2 class="text-xl font-bold text-slate-800">{{ $employee['Full_Name'] }}</h2>
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded-lg bg-blue-100 text-blue-700">{{ $employee['Employee_Number'] }}</span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium">{{ $employee['Position_Name'] }} &bull; {{ $employee['Department_Name'] }}</p>
                        
                        <!-- COMPLETENESS SCORE PROGRESS BAR -->
                        <div class="pt-2">
                            <div class="flex items-center justify-between text-xs font-bold mb-1">
                                <span class="text-slate-600">Kelengkapan Profil Data:</span>
                                <span class="text-blue-600">{{ $completeness }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ $completeness }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION A: PERSONAL INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">A</span>
                        Informasi Pribadi
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nama Lengkap</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $employee['Full_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Jenis Kelamin</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Gender'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tempat, Tanggal Lahir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                {{ $employee['Birth_Place'] ?: '-' }}, 
                                {{ !empty($employee['Birth_Date']) ? \Carbon\Carbon::parse($employee['Birth_Date'])->format('d F Y') : '-' }}
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">NIK / No. KTP</p>
                            <p class="text-sm font-mono font-bold text-slate-800 mt-1 flex items-center justify-between">
                                <span>{{ $employee['National_ID'] ?: '-' }}</span>
                                @if(!$isAuthorized)
                                    <span class="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-sans">Masked</span>
                                @endif
                            </p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Alamat Domisili</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Address'] ?: 'Tidak ada informasi alamat.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION B: CONTACT INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">B</span>
                        Informasi Kontak
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Alamat Email</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Email'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Telepon / WhatsApp</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Phone_Number'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION C: EMPLOYMENT INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">C</span>
                        Informasi Kepegawaian
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Departemen</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $employee['Department_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Posisi / Jabatan</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $employee['Position_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Kepegawaian</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Employment_Status'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Bergabung</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Join_Date']) ? \Carbon\Carbon::parse($employee['Join_Date'])->format('d M Y') : '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION D: TAX INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">D</span>
                        Informasi Perpajakan
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor NPWP</p>
                            <p class="text-sm font-mono font-bold text-slate-800 mt-1 flex items-center justify-between">
                                <span>{{ $employee['Tax_Number'] ?: '-' }}</span>
                                @if(!$isAuthorized)
                                    <span class="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-sans">Masked</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- SECTION E: BANKING INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">E</span>
                        Informasi Perbankan
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nama Bank</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Bank_Name'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Rekening</p>
                            <p class="text-sm font-mono font-bold text-slate-800 mt-1 flex items-center justify-between">
                                <span>{{ $employee['Bank_Account_Number'] ?: '-' }}</span>
                                @if(!$isAuthorized)
                                    <span class="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-sans">Masked</span>
                                @endif
                            </p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nama Pemilik Rekening</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Account_Holder_Name'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($employee['Notes']))
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Catatan Tambahan</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200">{{ $employee['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <!-- SECTION G: SYSTEM METADATA -->
        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Section G. System Metadata & Audit</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Karyawan (Primary Key)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $employee['Employee_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Pengguna (SSOT)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $employee['User_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Created_At']) ? \Carbon\Carbon::parse($employee['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $employee['Created_By'] ?? 'Sistem' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Updated_At']) ? \Carbon\Carbon::parse($employee['Updated_At'])->format('d M Y, H:i:s') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $employee['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
