@extends('layouts.app')

@section('header', 'Detail Data Matching')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-4">
                <a href="{{ route('matchings.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-white transition-colors border border-transparent hover:border-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-extrabold text-gray-900">
                            {{ $matching['Matching_Number'] ?? $matching['Matching_ID'] }}
                        </h2>
                        @php
                            $status = $matching['Matching_Status'] ?? '';
                            $statusColor = match($status) {
                                'ACCEPTED' => 'bg-green-100 text-green-700',
                                'PROPOSED' => 'bg-blue-100 text-blue-700',
                                'REVIEWING' => 'bg-amber-100 text-amber-700',
                                'REJECTED' => 'bg-red-100 text-red-700',
                                'WITHDRAWN' => 'bg-gray-200 text-gray-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ $status ?: 'UNKNOWN' }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-gray-500 mt-1">Dibuat pada {{ \Carbon\Carbon::parse($matching['Created_At'])->format('d M Y H:i') }} oleh {{ $matching['Created_By'] ?? 'System' }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('matchings.edit', $matching['Matching_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5">
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
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            Data Relasi Utama
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">ID Matching</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $matching['Matching_ID'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Kandidat / Siswa</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $matching['Student_Name'] ?? 'Data Siswa Tidak Ditemukan' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $matching['Student_Registration_Number'] ?? 'Tanpa NIS' }} • ID: {{ $matching['Student_ID'] }}</div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Job Order / Lowongan Pekerjaan</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $matching['Job_Title'] ?? 'Data Job Tidak Ditemukan' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">Perusahaan: {{ $matching['Company_Name'] ?? '-' }} • ID: {{ $matching['Job_Order_ID'] }}</div>
                                </dd>
                            </div>
                            @if(!empty($matching['Interview_ID']))
                            <div class="bg-teal-50 p-3 rounded-lg border border-teal-100">
                                <dt class="text-sm font-medium text-teal-800">Terkait Interview</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-teal-900">{{ $matching['Interview_Number'] ?? $matching['Interview_ID'] }}</div>
                                    <div class="text-xs text-teal-700 mt-0.5">Tanggal: {{ !empty($matching['Interview_Date']) ? \Carbon\Carbon::parse($matching['Interview_Date'])->format('d M Y') : '-' }}</div>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Status & Persetujuan -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Timeline & Persetujuan
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tanggal Matching Dibuat</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">
                                    {{ !empty($matching['Matching_Date']) ? \Carbon\Carbon::parse($matching['Matching_Date'])->format('d M Y') : '-' }}
                                </dd>
                            </div>
                            <div class="flex items-center gap-4 bg-blue-50/50 p-3 rounded-lg border border-blue-100/50">
                                <div class="flex-shrink-0">
                                    @if(!empty($matching['Company_Approval_Date']))
                                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    @endif
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Persetujuan Perusahaan</dt>
                                    <dd class="mt-0.5 text-sm font-bold {{ !empty($matching['Company_Approval_Date']) ? 'text-blue-700' : 'text-gray-900' }}">
                                        {{ !empty($matching['Company_Approval_Date']) ? \Carbon\Carbon::parse($matching['Company_Approval_Date'])->format('d M Y') : 'Menunggu' }}
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 bg-green-50/50 p-3 rounded-lg border border-green-100/50">
                                <div class="flex-shrink-0">
                                    @if(!empty($matching['Student_Approval_Date']))
                                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    @endif
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Persetujuan Kandidat</dt>
                                    <dd class="mt-0.5 text-sm font-bold {{ !empty($matching['Student_Approval_Date']) ? 'text-green-700' : 'text-gray-900' }}">
                                        {{ !empty($matching['Student_Approval_Date']) ? \Carbon\Carbon::parse($matching['Student_Approval_Date'])->format('d M Y') : 'Menunggu' }}
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
                                <dt class="text-sm font-medium text-gray-500">Remarks (Umpan Balik / Syarat Tambahan)</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $matching['Remarks'] ?: 'Tidak ada remarks' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catatan Internal WMS</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $matching['Notes'] ?: 'Tidak ada catatan internal' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Audit Log Info -->
            <div class="mt-10 pt-6 border-t border-gray-100 text-xs text-gray-500 flex flex-col md:flex-row md:items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Terakhir diubah pada {{ \Carbon\Carbon::parse($matching['Updated_At'])->format('d M Y H:i') }} oleh {{ $matching['Updated_By'] ?? 'System' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
