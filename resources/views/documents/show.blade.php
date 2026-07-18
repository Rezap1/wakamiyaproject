@extends('layouts.app')

@section('header', 'Detail Dokumen')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-4">
                <a href="{{ route('documents.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-white transition-colors border border-transparent hover:border-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-extrabold text-gray-900">
                            {{ $document['Document_Number'] ?? $document['Document_ID'] }}
                        </h2>
                        @php
                            $status = $document['Document_Status'] ?? '';
                            $statusColor = match($status) {
                                'VERIFIED' => 'bg-green-100 text-green-700',
                                'PENDING' => 'bg-amber-100 text-amber-700',
                                'REJECTED' => 'bg-red-100 text-red-700',
                                'EXPIRED' => 'bg-gray-200 text-gray-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ $status ?: 'UNKNOWN' }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-gray-500 mt-1">Dibuat pada {{ \Carbon\Carbon::parse($document['Created_At'])->format('d M Y H:i') }} oleh {{ $document['Created_By'] ?? 'System' }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                @if(!empty($document['File_URL']))
                <a href="{{ $document['File_URL'] }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    Buka File
                </a>
                @endif
                <a href="{{ route('documents.edit', $document['Document_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Info Relasi Utama -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            Kepemilikan & Relasi
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Pemilik Dokumen</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $document['Student_Name'] ?? 'Data Siswa Tidak Ditemukan' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">NIS: {{ $document['Student_Registration_Number'] ?? 'Tanpa NIS' }} • ID: {{ $document['Student_ID'] }}</div>
                                </dd>
                            </div>
                            @if(!empty($document['Application_ID']))
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                                <dt class="text-sm font-medium text-blue-800">Terkait Aplikasi Kerja</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-blue-900">{{ $document['Application_Number'] ?? $document['Application_ID'] }}</div>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            Detail Dokumen
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">ID Dokumen</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $document['Document_ID'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Jenis Dokumen</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $document['Document_Type'] ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nama Dokumen</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $document['Document_Name'] ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nama File</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $document['File_Name'] ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Info Validitas -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Masa Berlaku & Verifikasi
                        </h3>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Terbit</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">
                                        {{ !empty($document['Issue_Date']) ? \Carbon\Carbon::parse($document['Issue_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Berakhir</dt>
                                    @php
                                        $isExpired = !empty($document['Expiry_Date']) && \Carbon\Carbon::parse($document['Expiry_Date'])->isPast();
                                        $expClass = $isExpired ? 'text-red-600 font-extrabold' : 'text-gray-900 font-bold';
                                    @endphp
                                    <dd class="mt-1 text-sm {{ $expClass }}">
                                        {{ !empty($document['Expiry_Date']) ? \Carbon\Carbon::parse($document['Expiry_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                            </div>

                            @if($document['Document_Status'] === 'VERIFIED')
                            <div class="bg-green-50 p-4 rounded-xl border border-green-100">
                                <div class="flex items-center gap-2 text-green-800 font-bold mb-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Dokumen Terverifikasi
                                </div>
                                <div class="text-sm text-green-700">
                                    Diverifikasi oleh <span class="font-bold">{{ $document['Verified_By'] ?? 'Unknown' }}</span> pada {{ !empty($document['Verification_Date']) ? \Carbon\Carbon::parse($document['Verification_Date'])->format('d M Y') : '-' }}
                                </div>
                            </div>
                            @endif
                        </dl>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Catatan
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Remarks</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $document['Remarks'] ?: 'Tidak ada remarks' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catatan Internal WMS</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $document['Notes'] ?: 'Tidak ada catatan internal' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Audit Log Info -->
            <div class="mt-10 pt-6 border-t border-gray-100 text-xs text-gray-500 flex flex-col md:flex-row md:items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Terakhir diubah pada {{ \Carbon\Carbon::parse($document['Updated_At'])->format('d M Y H:i') }} oleh {{ $document['Updated_By'] ?? 'System' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
