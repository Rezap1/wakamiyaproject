@extends('layouts.app')

@section('header', 'Detail Hak Akses (Permission)')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Action Bar -->
    <div class="flex justify-between items-center">
        <a href="{{ route('permissions.index') }}" class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar
        </a>
        <div class="flex gap-2">
            <a href="{{ route('permissions.edit', $permission['Permission_ID']) }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Konfigurasi
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        <div class="p-6 md:p-8 relative">
            <div class="absolute top-6 right-6">
                @if(($permission['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span> Status Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                        <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span> Status Nonaktif
                    </span>
                @endif
            </div>

            <!-- Identity -->
            <div class="flex items-start mb-8 border-b border-gray-100 pb-8">
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center font-bold shadow-lg shadow-indigo-500/30 flex-shrink-0">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div class="ml-6">
                    <h1 class="text-3xl font-extrabold text-gray-900">{{ $permission['Role_Name'] }}</h1>
                    <p class="text-lg font-medium text-primary-600 mt-1">Akses pada Modul: {{ $permission['Module_Name'] }}</p>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold font-mono bg-gray-50 text-gray-700 border border-gray-200">
                            Config ID: {{ $permission['Permission_ID'] }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-mono bg-gray-50 text-gray-500 border border-gray-200">
                            Role ID: {{ $permission['Role_ID'] }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-mono bg-gray-50 text-gray-500 border border-gray-200">
                            Module ID: {{ $permission['Module_ID'] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Matriks Visual -->
            <div>
                <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Matriks Akses Tersedia
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach([
                        'Can_View' => ['icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'label' => 'Melihat Data (View)', 'color' => 'blue'],
                        'Can_Create' => ['icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6', 'label' => 'Menambah (Create)', 'color' => 'green'],
                        'Can_Edit' => ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => 'Mengubah (Edit)', 'color' => 'yellow'],
                        'Can_Delete' => ['icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'label' => 'Menghapus (Delete)', 'color' => 'red'],
                        'Can_Print' => ['icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z', 'label' => 'Mencetak (Print)', 'color' => 'indigo'],
                        'Can_Export_PDF' => ['icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', 'label' => 'Ekspor (Export PDF)', 'color' => 'purple']
                    ] as $field => $config)
                    
                    @if(($permission[$field] ?? 'FALSE') === 'TRUE')
                        <!-- DIIZINKAN -->
                        <div class="flex items-center p-4 bg-{{ $config['color'] }}-50 border border-{{ $config['color'] }}-100 rounded-xl text-{{ $config['color'] }}-800 shadow-sm">
                            <div class="bg-{{ $config['color'] }}-100 p-2 rounded-lg mr-4">
                                <svg class="w-6 h-6 text-{{ $config['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <p class="font-bold">{{ $config['label'] }}</p>
                                <p class="text-xs text-{{ $config['color'] }}-600 mt-0.5">DIIZINKAN</p>
                            </div>
                        </div>
                    @else
                        <!-- DITOLAK -->
                        <div class="flex items-center p-4 bg-gray-50 border border-gray-200 rounded-xl text-gray-500">
                            <div class="bg-white p-2 rounded-lg border border-gray-200 mr-4">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </div>
                            <div>
                                <p class="font-bold line-through opacity-70">{{ $config['label'] }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">DITOLAK</p>
                            </div>
                        </div>
                    @endif
                    
                    @endforeach
                </div>
            </div>

            <!-- Catatan -->
            @if($permission['Notes'])
            <div class="mt-8 bg-amber-50 rounded-2xl p-5 border border-amber-100 shadow-sm">
                <h3 class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2 flex items-center border-b border-amber-200/50 pb-2">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Catatan Internal
                </h3>
                <div class="text-sm text-amber-900 whitespace-pre-wrap leading-relaxed mt-3 font-medium">
                    {{ $permission['Notes'] }}
                </div>
            </div>
            @endif

        </div>

        <!-- Audit Trail -->
        <div class="bg-gray-50 px-6 md:px-8 py-5 border-t border-gray-200">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Audit & Log Sistem
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Pembuatan Konfigurasi</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">{{ $permission['Created_At'] ? \Carbon\Carbon::parse($permission['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Oleh: {{ $permission['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm border-l-2 border-l-primary-500">
                    <p class="text-xs text-primary-600 font-bold uppercase tracking-wider">Modifikasi Terakhir</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">{{ $permission['Updated_At'] ? \Carbon\Carbon::parse($permission['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Oleh: {{ $permission['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
