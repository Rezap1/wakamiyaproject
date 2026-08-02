@extends('layouts.app')
@section('header', 'Slip & Bukti Gaji Saya')
@section('content')

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-50 to-transparent"></div>
        <div class="relative z-10">
            <h2 class="text-2xl font-black text-slate-800">Gaji Saya</h2>
            <p class="text-slate-600 font-medium">Riwayat pembayaran gaji dan bukti transfer Anda.</p>
        </div>
        <div class="relative z-10 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Karyawan</span>
            <div class="text-sm font-black text-slate-800">{{ $employee['Full_Name'] ?? auth()->user()->Full_Name ?? 'Unknown' }}</div>
        </div>
    </div>

    <!-- Payroll History -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800">Riwayat Penggajian</h3>
        </div>
        <div class="p-6">
            @if(count($payrolls) > 0)
                <div class="space-y-4">
                    @foreach($payrolls as $payroll)
                        <div class="border border-slate-200 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800">{{ $payroll['Payroll_Period'] ?? 'Bulan Ini' }}</h4>
                                    <p class="text-xs text-slate-500 mt-1">Dibayar: {{ isset($payroll['Paid_Date']) ? \Carbon\Carbon::parse($payroll['Paid_Date'])->translatedFormat('d F Y') : '-' }}</p>
                                    @if(!empty($payroll['Notes']))
                                        <p class="text-xs text-blue-600 mt-2 bg-blue-50 p-2 rounded-lg border border-blue-100">
                                            <span class="font-bold">Catatan:</span> {{ $payroll['Notes'] }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-8">
                                <div class="text-left md:text-right">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Gaji Bersih</p>
                                    <p class="font-black text-slate-800">Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('payrolls.slip', $payroll['Payroll_ID']) }}" target="_blank" class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-200 transition-colors">Slip Gaji</a>
                                    
                                    @if(!empty($payroll['Payment_Proof']))
                                        @php 
                                            $isUrl = str_starts_with($payroll['Payment_Proof'], 'http');
                                            $proofUrl = $isUrl ? $payroll['Payment_Proof'] : asset($payroll['Payment_Proof']);
                                        @endphp
                                        <div x-data="{ openImageModal: false }" class="inline">
                                            <button @click="openImageModal = true" type="button" class="px-3 py-1.5 bg-blue-100 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-200 transition-colors flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Bukti Transfer
                                            </button>
                                            
                                            <!-- Image Modal -->
                                            <div x-show="openImageModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm" style="display: none;" x-transition>
                                                <div @click.away="openImageModal = false" class="bg-slate-100 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col relative mx-4">
                                                    <!-- Modal Header -->
                                                    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-white shrink-0">
                                                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                            Bukti Transfer - {{ $payroll['Payroll_Period'] ?? 'Bulan Ini' }}
                                                        </h3>
                                                        <button @click="openImageModal = false" class="text-slate-400 hover:text-rose-500 transition-colors p-1 bg-slate-100 rounded-full">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Image Container -->
                                                    <div x-data="{ isZoomed: false }" class="overflow-auto p-4 flex-1 flex items-center justify-center bg-slate-200/50" :class="isZoomed ? 'cursor-zoom-out items-start justify-start' : 'cursor-zoom-in'" @click="isZoomed = !isZoomed">
                                                        @if(str_ends_with(strtolower($proofUrl), '.pdf'))
                                                            <iframe src="{{ $proofUrl }}" class="w-full h-[60vh] rounded-lg border border-slate-300" @click.stop></iframe>
                                                        @else
                                                            <img src="{{ $proofUrl }}" alt="Bukti Transfer" class="rounded-lg shadow-sm border border-slate-200 transition-all duration-300" :class="isZoomed ? 'w-full max-w-none h-auto min-w-[600px]' : 'max-w-full max-h-[60vh] object-contain'">
                                                        @endif
                                                    </div>
                                                    
                                                    <!-- Modal Footer (Action Buttons) -->
                                                    <div class="px-6 py-4 bg-white border-t border-slate-200 flex justify-between shrink-0">
                                                        <button @click="openImageModal = false" type="button" class="px-5 py-2.5 bg-slate-100 text-slate-700 text-sm font-bold rounded-xl hover:bg-slate-200 transition-colors flex items-center gap-2">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                                            Kembali
                                                        </button>
                                                        <a href="{{ $proofUrl }}" download class="px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-sm">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                            Unduh
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="px-3 py-1.5 bg-slate-50 text-slate-400 text-xs font-bold rounded-lg border border-slate-100">Belum Ada Bukti</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-700">Belum Ada Data</h3>
                    <p class="text-slate-500 mt-1">Anda belum memiliki riwayat penggajian yang telah dibayar.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
