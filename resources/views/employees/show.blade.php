@extends('layouts.app')

@section('header', 'Detail Karyawan')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
        <div class="h-32 bg-gradient-to-r from-primary-600 to-primary-800"></div>
        <div class="px-6 sm:px-10 pb-8">
            <div class="relative flex justify-between items-start -mt-12">
                <div class="flex flex-col md:flex-row gap-6 md:items-end">
                    <div class="h-28 w-28 rounded-2xl bg-white p-1.5 shadow-lg border border-gray-100">
                        <div class="h-full w-full rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 text-gray-500 flex items-center justify-center font-extrabold text-4xl shadow-inner border border-gray-50">
                            {{ substr($employee['Full_Name'] ?? 'U', 0, 1) }}
                        </div>
                    </div>
                    <div class="pb-2">
                        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ $employee['Full_Name'] }}</h1>
                        <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
                            <span class="font-mono bg-primary-50 text-primary-700 px-2.5 py-1 rounded-md font-bold">{{ $employee['Employee_Number'] }}</span>
                            <span class="text-gray-500 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                {{ $employee['Position_Name'] }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end gap-3 mt-14 md:mt-16">
                    @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Pegawai Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Nonaktif
                        </span>
                    @endif
                    
                    <a href="{{ route('employees.edit', $employee['Employee_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Pribadi & Bank -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Data Pribadi -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Informasi Pribadi
                    </h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor KTP (NIK)</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['National_ID'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Kelamin</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Gender'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tempat, Tanggal Lahir</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">
                                {{ $employee['Birth_Place'] ?: '-' }}, 
                                {{ $employee['Birth_Date'] ? \Carbon\Carbon::parse($employee['Birth_Date'])->format('d F Y') : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Handphone</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Phone_Number'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email Pribadi</dt>
                            <dd class="mt-1 text-sm font-bold text-blue-600">{{ $employee['Email'] ?: '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2 border-t border-gray-100 pt-4 mt-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat Domisili</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $employee['Address'] ?: 'Tidak ada informasi alamat.' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Data Bank & Pajak -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Informasi Perbankan & Pajak
                    </h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor NPWP</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900 font-mono">{{ $employee['Tax_Number'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Bank</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Bank_Name'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Rekening</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900 font-mono">{{ $employee['Bank_Account_Number'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Atas Nama (Pemilik)</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Account_Holder_Name'] ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Right Column: Pekerjaan & Audit -->
        <div class="space-y-6">
            <!-- Data Pekerjaan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Status Pekerjaan
                    </h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-5">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">ID Sistem</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Employee_ID'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Department_Name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Posisi / Jabatan</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $employee['Position_Name'] }}</dd>
                        </div>
                        <div class="pt-4 border-t border-gray-100">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Status Kepegawaian</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $employee['Employment_Status'] ?: '-' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Bergabung</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">
                                {{ $employee['Join_Date'] ? \Carbon\Carbon::parse($employee['Join_Date'])->format('d M Y') : '-' }}
                                <span class="text-xs font-medium text-gray-500 ml-1">
                                    ({{ $employee['Join_Date'] ? \Carbon\Carbon::parse($employee['Join_Date'])->diffForHumans(null, true) . ' lalu' : '' }})
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Notes -->
            @if($employee['Notes'])
            <div class="bg-amber-50 rounded-2xl shadow-sm border border-amber-100 overflow-hidden">
                <div class="p-5">
                    <h3 class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Catatan
                    </h3>
                    <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ $employee['Notes'] }}</p>
                </div>
            </div>
            @endif

            <!-- Audit Log Summary -->
            <div class="bg-gray-50 rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-200 bg-gray-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Audit Trail</h3>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Dibuat Pada</p>
                            <p class="text-sm font-bold text-gray-900">{{ $employee['Created_At'] ? \Carbon\Carbon::parse($employee['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Oleh: <span class="font-medium text-gray-700">{{ $employee['Created_By'] ?? 'Sistem' }}</span></p>
                        </div>
                        <div class="pt-3 border-t border-gray-200">
                            <p class="text-xs text-gray-500 font-medium">Terakhir Diperbarui</p>
                            <p class="text-sm font-bold text-gray-900">{{ $employee['Updated_At'] ? \Carbon\Carbon::parse($employee['Updated_At'])->format('d M Y, H:i:s') : '-' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Oleh: <span class="font-medium text-gray-700">{{ $employee['Updated_By'] ?? 'Sistem' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
