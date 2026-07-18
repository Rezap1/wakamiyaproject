@extends('layouts.app')

@section('header', 'Detail Interview')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('interviews.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Detail Jadwal Interview</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Informasi lengkap penjadwalan dan hasil evaluasi kandidat.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('interviews.edit', $interview['Interview_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="flex items-start mb-8 pb-8 border-b border-gray-100">
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-2xl shadow-sm border border-indigo-50 flex-shrink-0">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <div class="ml-6 flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-extrabold text-gray-900">{{ $interview['Student_Name'] ?? 'Siswa Tidak Diketahui' }}</h3>
                            <p class="text-gray-500 font-medium">{{ $interview['Student_Registration_Number'] ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="block text-lg font-bold text-gray-900">{{ $interview['Interview_Number'] ?? $interview['Interview_ID'] }}</span>
                            <span class="block text-xs font-medium text-gray-500">ID: {{ $interview['Interview_ID'] ?? '-' }}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 mt-4">
                        @php
                            $status = $interview['Interview_Status'] ?? '';
                            $statusColor = match($status) {
                                'COMPLETED' => 'bg-green-50 text-green-700 border-green-200',
                                'SCHEDULED' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'CANCELLED' => 'bg-red-50 text-red-700 border-red-200',
                                'RESCHEDULED' => 'bg-orange-50 text-orange-700 border-orange-200',
                                default => 'bg-gray-50 text-gray-700 border-gray-200'
                            };
                            
                            $result = $interview['Interview_Result'] ?? 'PENDING';
                            $resultColor = match($result) {
                                'PASSED' => 'bg-green-100 text-green-800 border-green-300',
                                'FAILED' => 'bg-red-100 text-red-800 border-red-300',
                                'RESERVE' => 'bg-orange-100 text-orange-800 border-orange-300',
                                default => 'bg-gray-100 text-gray-600 border-gray-200'
                            };
                        @endphp
                        
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border shadow-sm {{ $statusColor }}">
                            Status: {{ $status ?: 'UNKNOWN' }}
                        </span>
                        
                        @if($status === 'COMPLETED')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border shadow-sm {{ $resultColor }}">
                                Hasil: {{ $result }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Data Pekerjaan -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Tujuan Perusahaan (Job Order)
                    </h4>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Perusahaan</dt>
                            <dd class="text-sm font-bold text-primary-700 mt-1">{{ $interview['Company_Name'] ?? 'Tidak Diketahui' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Posisi / Judul Pekerjaan</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">{{ $interview['Job_Title'] ?? 'Tidak Diketahui' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">No. Job Order</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">{{ $interview['Job_Order_Number'] ?? $interview['Job_Order_ID'] }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Informasi Pelaksanaan -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Informasi Pelaksanaan
                    </h4>
                    <dl class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase">Tanggal</dt>
                                <dd class="text-sm font-semibold text-gray-900 mt-1">{{ !empty($interview['Interview_Date']) ? \Carbon\Carbon::parse($interview['Interview_Date'])->format('d M Y') : '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase">Waktu</dt>
                                <dd class="text-sm font-semibold text-gray-900 mt-1">{{ $interview['Interview_Time'] ?? '-' }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Metode</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">{{ $interview['Interview_Method'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Pewawancara</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">{{ $interview['Interviewer'] ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>
                
                <!-- Hasil Evaluasi -->
                <div class="md:col-span-2 bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Umpan Balik & Evaluasi
                    </h4>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Tanggal Pengumuman Hasil</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">{{ !empty($interview['Result_Date']) ? \Carbon\Carbon::parse($interview['Result_Date'])->format('d M Y') : 'Belum diumumkan' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Remarks (Catatan Perusahaan)</dt>
                            <dd class="text-sm font-medium text-gray-800 mt-1 p-4 bg-white rounded-xl border border-gray-200 whitespace-pre-line">{{ $interview['Remarks'] ?: 'Tidak ada remarks.' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 uppercase">Catatan Internal WMS</dt>
                            <dd class="text-sm font-medium text-gray-800 mt-1 p-4 bg-white rounded-xl border border-gray-200 whitespace-pre-line">{{ $interview['Notes'] ?: 'Tidak ada catatan internal.' }}</dd>
                        </div>
                    </dl>
                </div>
                
                <!-- System Info -->
                <div class="md:col-span-2 text-xs text-gray-400 font-medium flex justify-between border-t border-gray-100 pt-4">
                    <div>Dibuat: {{ !empty($interview['Created_At']) ? \Carbon\Carbon::parse($interview['Created_At'])->format('d M Y H:i') : '-' }} oleh {{ $interview['Created_By'] ?? '-' }}</div>
                    <div>Diperbarui: {{ !empty($interview['Updated_At']) ? \Carbon\Carbon::parse($interview['Updated_At'])->format('d M Y H:i') : '-' }} oleh {{ $interview['Updated_By'] ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
