@extends('layouts.app')
@section('header', 'Detail Penggajian')
@section('content')

@php
    $status = $payroll['Status'] ?? 'Draft';
    $statusColor = match($status) {
        'Paid' => 'green',
        'Approved' => 'blue',
        'Calculated', 'Generated' => 'slate',
        'Waiting Approval' => 'yellow',
        default => 'slate',
    };
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="{{ $payroll['Employee_ID'] ?? 'Karyawan Tidak Diketahui' }}" 
    subtitle="Penggajian: {{ $payroll['Payroll_Number'] ?? '-' }}"
    status="{{ $status }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($payroll['Employee_ID'] ?? 'E', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Penggajian' => route('payrolls.index'), 'Detail' => '#']"
>

    <x-slot:actions>
        @if($status == 'Draft' || $status == 'Approved' || $status == 'Paid')
            <div x-data="{ openPayModal: false }" class="inline">
                <button @click="openPayModal = true" type="button" class="px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-xl shadow-sm hover:bg-green-700 transition-colors">
                    {{ $status == 'Paid' ? 'Perbarui Bukti' : 'Cairkan Gaji' }}
                </button>
                
                <!-- Payment Modal -->
                <div x-show="openPayModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" style="display: none;">
                    <div @click.away="openPayModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <h3 class="font-bold text-slate-800 text-lg">{{ $status == 'Paid' ? 'Perbarui Bukti Transfer' : 'Cairkan Gaji & Unggah Bukti' }}</h3>
                            <button @click="openPayModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                        </div>
                        <form action="{{ route('payrolls.pay', $payroll['Payroll_ID'] ?? 1) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                            @csrf
                            <p class="text-sm text-slate-600">
                                {{ $status == 'Paid' ? 'Silakan unggah ulang bukti transfer terbaru jika ada perbaikan.' : 'Sistem akan menandai gaji ini sebagai Lunas (Paid). Silakan unggah bukti transfer agar pegawai dapat melihatnya di akun mereka.' }}
                            </p>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Bukti Transfer (Gambar / PDF)</label>
                                <input type="file" name="Payment_Proof" accept="image/*,.pdf" required
                                       class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-xl p-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                                <textarea name="Notes" rows="2" class="w-full text-sm text-slate-700 border border-slate-200 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Pembayaran Gaji Bulan Juli..."></textarea>
                            </div>
                            
                            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                                <button type="button" @click="openPayModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm font-bold rounded-xl hover:bg-slate-200 transition-colors">Batal</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-xl shadow-sm hover:bg-green-700 transition-colors">Kirim & Cairkan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        <x-universal.action-button action="edit" url="{{ route('payrolls.edit', $payroll['Payroll_ID'] ?? 1) }}" />
    </x-slot:actions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Periode</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $payroll['Payroll_Period'] ?? '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Gaji Bersih</p>
            <p class="text-xl font-black text-slate-800 mt-1">Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="pt-4 border-t border-slate-100">
            <a href="{{ route('payrolls.slip', $payroll['Payroll_ID'] ?? 1) }}" class="block text-center w-full py-2 bg-slate-800 text-white font-bold rounded-lg text-xs hover:bg-slate-900 transition-colors">Pratinjau Slip</a>
        </div>
    </x-slot:sidebarContent>

    <x-slot:information>
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Rincian Gaji</h3>
                
                <div class="space-y-4">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Earnings -->
                        <!-- Simplified to only show Net Salary -->
                        <div class="border-l-4 border-emerald-400 bg-white p-4 rounded-r-xl border border-slate-200 shadow-sm col-span-2">
                            <h4 class="text-xs font-bold text-emerald-600 uppercase mb-2">Total Gaji Bersih</h4>
                            <div class="text-2xl font-black text-slate-800">
                                Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Sistem disederhanakan hanya menggunakan Total Gaji Bersih.</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-sm text-blue-800 font-medium">Nomor Dokumen</span>
                        </div>
                        <span class="font-bold text-blue-900">{{ $payroll['Document_Number'] ?? 'Tertunda' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:information>

    <x-slot:audit>
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Log Sistem</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">ID Rekaman</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payroll['Payroll_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $payroll['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    </x-slot:audit>

</x-universal.detail-layout>

@endsection



