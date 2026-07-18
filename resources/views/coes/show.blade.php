@extends('layouts.app')

@section('header', 'Detail COE')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-4">
                <a href="{{ route('coes.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-white transition-colors border border-transparent hover:border-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-extrabold text-gray-900">
                            {{ $coe['COE_Number'] ?? $coe['COE_ID'] }}
                        </h2>
                        @php
                            $status = $coe['COE_Status'] ?? '';
                            $statusColor = match($status) {
                                'APPROVED' => 'bg-green-100 text-green-700',
                                'SUBMITTED' => 'bg-blue-100 text-blue-700',
                                'PREPARING' => 'bg-amber-100 text-amber-700',
                                'REJECTED' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ $status ?: 'UNKNOWN' }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-gray-500 mt-1">Dibuat pada {{ \Carbon\Carbon::parse($coe['Created_At'])->format('d M Y H:i') }} oleh {{ $coe['Created_By'] ?? 'System' }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('coes.edit', $coe['COE_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5">
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
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Kandidat & Perusahaan
                        </h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Siswa / Kandidat</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $coe['Student_Name'] ?? 'Data Siswa Dihapus' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">NIS: {{ $coe['Student_Registration_Number'] ?? 'Tanpa NIS' }} • ID: {{ $coe['Student_ID'] }}</div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Perusahaan Tujuan</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-gray-900">{{ $coe['Company_Name'] ?? 'Data Perusahaan Dihapus' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">ID: {{ $coe['Company_ID'] }}</div>
                                </dd>
                            </div>
                            @if(!empty($coe['Application_ID']))
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                                <dt class="text-sm font-medium text-blue-800">Terkait Aplikasi Kerja</dt>
                                <dd class="mt-1">
                                    <div class="text-sm font-bold text-blue-900">{{ $coe['Application_Number'] ?? $coe['Application_ID'] }}</div>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Timeline & Imigrasi -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Timeline Imigrasi
                        </h3>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Persiapan</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">
                                        {{ !empty($coe['Application_Date']) ? \Carbon\Carbon::parse($coe['Application_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Diajukan</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">
                                        {{ !empty($coe['Submission_Date']) ? \Carbon\Carbon::parse($coe['Submission_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Disetujui</dt>
                                    <dd class="mt-1 text-sm font-bold text-green-700">
                                        {{ !empty($coe['Approval_Date']) ? \Carbon\Carbon::parse($coe['Approval_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Kedaluwarsa</dt>
                                    @php
                                        $isExpired = !empty($coe['COE_Expiry_Date']) && \Carbon\Carbon::parse($coe['COE_Expiry_Date'])->isPast();
                                        $expClass = $isExpired ? 'text-red-600 font-extrabold' : 'text-gray-900 font-bold';
                                    @endphp
                                    <dd class="mt-1 text-sm {{ $expClass }}">
                                        {{ !empty($coe['COE_Expiry_Date']) ? \Carbon\Carbon::parse($coe['COE_Expiry_Date'])->format('d M Y') : '-' }}
                                    </dd>
                                </div>
                            </div>
                            
                            @if(!empty($coe['Immigration_Office']))
                            <div class="mt-4 p-4 rounded-xl bg-gray-50 border border-gray-200">
                                <dt class="text-sm font-medium text-gray-500">Kantor Imigrasi</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900">{{ $coe['Immigration_Office'] }}</dd>
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
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $coe['Remarks'] ?: 'Tidak ada remarks' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catatan Internal WMS</dt>
                                <dd class="mt-2 text-sm text-gray-900 whitespace-pre-line bg-gray-50 p-4 rounded-xl border border-gray-100">{{ $coe['Notes'] ?: 'Tidak ada catatan internal' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Audit Log Info -->
            <div class="mt-10 pt-6 border-t border-gray-100 text-xs text-gray-500 flex flex-col md:flex-row md:items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Terakhir diubah pada {{ \Carbon\Carbon::parse($coe['Updated_At'])->format('d M Y H:i') }} oleh {{ $coe['Updated_By'] ?? 'System' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
