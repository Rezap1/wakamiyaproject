@extends('layouts.app')

@section('header', 'Detail Aplikasi Kerja')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-4">
                <a href="{{ route('applications.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-white transition-colors border border-transparent hover:border-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-extrabold text-gray-900">
                            {{ $application['Application_Number'] ?? $application['Application_ID'] }}
                        </h2>
                        @php
                            $status = $application['Application_Status'] ?? '';
                            $statusColor = match($status) {
                                'APPROVED' => 'bg-green-100 text-green-700',
                                'SUBMITTED' => 'bg-blue-100 text-blue-700',
                                'IN_PROGRESS' => 'bg-amber-100 text-amber-700',
                                'REJECTED' => 'bg-red-100 text-red-700',
                                'CANCELED' => 'bg-gray-200 text-gray-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ $status ?: 'UNKNOWN' }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-gray-500 mt-1">Dibuat pada {{ \Carbon\Carbon::parse($application['Created_At'])->format('d M Y H:i') }} oleh {{ $application['Created_By'] ?? 'System' }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('applications.edit', $application['Application_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5">
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
                            Data Aplikasi & Relasi
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">ID Aplikasi</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $application['Application_ID'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tanggal Aplikasi</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ !empty($application['Application_Date']) ? \Carbon\Carbon::parse($application['Application_Date'])->format('d M Y') : '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Kandidat / Siswa</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $application['Student_Name'] ?? 'Data Siswa Tidak Ditemukan' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $application['Student_Registration_Number'] ?? 'Tanpa NIS' }} • ID: {{ $application['Student_ID'] }}</div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Job Order / Lowongan Pekerjaan</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $application['Job_Title'] ?? 'Data Job Tidak Ditemukan' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">Perusahaan: {{ $application['Company_Name'] ?? '-' }} • ID: {{ $application['Job_Order_ID'] }}</div>
                                </dd>
                            </div>
                            @if(!empty($application['Matching_ID']))
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                                <dt class="text-sm font-medium text-blue-800">Terkait Matching</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-blue-900">{{ $application['Matching_Number'] ?? $application['Matching_ID'] }}</div>
                                    <div class="text-xs text-blue-700 mt-0.5">Status: {{ $application['Matching_Status'] ?? '-' }}</div>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Info Pembayaran & Status -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Keuangan & Administrasi
                        </h3>
                        <dl class="space-y-4">
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Biaya Aplikasi</dt>
                                    <dd class="mt-1 text-xl font-black text-gray-900">
                                        Rp {{ number_format((float)($application['Application_Fee'] ?? 0), 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div class="text-right">
                                    <dt class="text-sm font-medium text-gray-500">Status Pembayaran</dt>
                                    <dd class="mt-1">
                                        @php
                                            $payStatus = $application['Payment_Status'] ?? '';
                                            $payColor = match($payStatus) {
                                                'PAID' => 'text-green-600 bg-green-50 border border-green-200',
                                                'PENDING' => 'text-amber-600 bg-amber-50 border border-amber-200',
                                                'PARTIAL' => 'text-blue-600 bg-blue-50 border border-blue-200',
                                                'FAILED' => 'text-red-600 bg-red-50 border border-red-200',
                                                'REFUNDED' => 'text-gray-600 bg-gray-100 border border-gray-300',
                                                default => 'text-gray-500 bg-gray-50 border border-gray-200'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold uppercase {{ $payColor }}">
                                            @if($payStatus === 'PAID')
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            @endif
                                            {{ $payStatus ?: 'TIDAK ADA' }}
                                        </span>
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Catatan & Informasi Lain
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Remarks</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $application['Remarks'] ?: 'Tidak ada remarks' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catatan Internal WMS</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $application['Notes'] ?: 'Tidak ada catatan internal' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Audit Log Info -->
            <div class="mt-10 pt-6 border-t border-gray-100 text-xs text-gray-500 flex flex-col md:flex-row md:items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Terakhir diubah pada {{ \Carbon\Carbon::parse($application['Updated_At'])->format('d M Y H:i') }} oleh {{ $application['Updated_By'] ?? 'System' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
