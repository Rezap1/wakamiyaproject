@extends('layouts.app')

@section('header', 'Profil Perusahaan')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Action Bar -->
    <div class="flex justify-between items-center">
        <a href="{{ route('companies.index') }}" class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar
        </a>
        <div class="flex gap-2">
            <a href="{{ route('companies.edit', $company['Company_ID']) }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Data
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Banner Profile -->
        <div class="h-32 bg-gradient-to-r from-primary-600 to-indigo-700 relative">
            <div class="absolute inset-0 bg-white/10" style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 24px 24px;"></div>
        </div>

        <div class="px-6 md:px-8 pb-8 relative">
            <!-- Avatar / Logo -->
            <div class="-mt-16 mb-4 flex justify-between items-end">
                <div class="relative group">
                    @if(!empty($company['Company_Logo']))
                        <img src="{{ Storage::url($company['Company_Logo']) }}" alt="Logo" class="h-32 w-32 rounded-2xl border-4 border-white object-cover bg-white shadow-lg relative z-10">
                    @else
                        <div class="h-32 w-32 rounded-2xl border-4 border-white bg-gradient-to-br from-indigo-50 to-blue-50 text-indigo-600 flex flex-col items-center justify-center shadow-lg relative z-10">
                            <svg class="w-12 h-12 mb-1 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span class="text-xs font-bold opacity-50">No Logo</span>
                        </div>
                    @endif
                </div>
                <div>
                    @if(($company['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span> Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span> Nonaktif
                        </span>
                    @endif
                </div>
            </div>

            <!-- Identity -->
            <div class="border-b border-gray-100 pb-6 mb-6">
                <h1 class="text-3xl font-extrabold text-gray-900">{{ $company['Company_Name'] }}</h1>
                <p class="text-lg font-medium text-gray-500 mt-1">{{ $company['Legal_Name'] }}</p>
                <div class="flex flex-wrap gap-3 mt-4">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold font-mono bg-indigo-50 text-indigo-700 border border-indigo-100">
                        <svg class="w-3.5 h-3.5 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                        {{ $company['Company_Code'] }}
                    </span>
                    @if(!empty($company['NPWP']))
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold font-mono bg-gray-50 text-gray-700 border border-gray-200">
                        <svg class="w-3.5 h-3.5 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        NPWP: {{ $company['NPWP'] }}
                    </span>
                    @endif
                </div>
            </div>

            <!-- Detailed Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Col (Info) -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Perizinan & Pimpinan -->
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            Struktur & Legalitas
                        </h3>
                        <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Pimpinan / Direktur Utama</p>
                                <p class="text-sm font-bold text-gray-900 mt-1">{{ $company['Director_Name'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">Nomor Induk Berusaha (NIB)</p>
                                <p class="text-sm font-bold text-gray-900 font-mono mt-1">{{ $company['Business_License_Number'] ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Kontak & Lokasi -->
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            Lokasi & Komunikasi
                        </h3>
                        <div class="border border-gray-200 rounded-2xl overflow-hidden">
                            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-200">
                                <div class="p-5 bg-white">
                                    <p class="text-xs font-medium text-gray-500 mb-3">Alamat Surat Menyurat</p>
                                    <p class="text-sm text-gray-900 font-medium leading-relaxed">{{ $company['Address'] ?: 'Alamat belum diisi.' }}</p>
                                    <p class="text-sm text-gray-600 mt-2">{{ $company['City'] }} {{ $company['Province'] }}</p>
                                    <p class="text-sm text-gray-600 font-bold mt-1">{{ $company['Country'] }} <span class="font-mono text-xs font-normal text-gray-500 ml-1">{{ $company['Postal_Code'] }}</span></p>
                                </div>
                                <div class="p-5 bg-gray-50 space-y-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-xs font-medium text-gray-500">Telepon / Fax</p>
                                            <p class="text-sm font-bold text-gray-900 font-mono mt-0.5">{{ $company['Phone_Number'] ?: '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-xs font-medium text-gray-500">Email Utama</p>
                                            <p class="text-sm font-bold text-gray-900 mt-0.5">
                                                @if($company['Email'])
                                                    <a href="mailto:{{ $company['Email'] }}" class="text-primary-600 hover:underline">{{ $company['Email'] }}</a>
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-xs font-medium text-gray-500">Website</p>
                                            <p class="text-sm font-bold text-gray-900 mt-0.5">
                                                @if($company['Website'])
                                                    <a href="{{ $company['Website'] }}" target="_blank" class="text-primary-600 hover:underline break-all">{{ $company['Website'] }}</a>
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Col (Stamp & Notes) -->
                <div class="space-y-6">
                    <!-- Stempel -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm text-center">
                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b border-gray-100 pb-3">Stempel Resmi / Cap</h3>
                        
                        <div class="flex justify-center my-4">
                            @if(!empty($company['Company_Stamp']))
                                <img src="{{ Storage::url($company['Company_Stamp']) }}" alt="Stamp" class="max-h-32 object-contain mix-blend-multiply">
                            @else
                                <div class="h-24 w-24 rounded-full border-2 border-dashed border-gray-300 flex flex-col items-center justify-center text-gray-400 opacity-50">
                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    <span class="text-[10px] font-bold">NO STAMP</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Catatan -->
                    @if($company['Notes'])
                    <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 shadow-sm">
                        <h3 class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2 flex items-center border-b border-amber-200/50 pb-2">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Catatan Internal
                        </h3>
                        <div class="text-sm text-amber-900 whitespace-pre-wrap leading-relaxed mt-3 font-medium">
                            {{ $company['Notes'] }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="bg-gray-50 px-6 md:px-8 py-5 border-t border-gray-200">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Audit & Log Sistem
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Record ID</p>
                    <p class="text-sm font-bold text-gray-900 font-mono mt-1">{{ $company['Company_ID'] }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Registrasi Awal</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">{{ $company['Created_At'] ? \Carbon\Carbon::parse($company['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Oleh: {{ $company['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm border-l-2 border-l-primary-500">
                    <p class="text-xs text-primary-600 font-bold uppercase tracking-wider">Modifikasi Terakhir</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">{{ $company['Updated_At'] ? \Carbon\Carbon::parse($company['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Oleh: {{ $company['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
