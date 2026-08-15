@extends('layouts.app')
@section('header', 'Detail Penggajian & Slip Gaji')
@section('content')

@php
    $status = $payroll['Status'] ?? 'Draft';
    $badgeColor = match($status) {
        'Paid', 'Closed' => 'green',
        'Approved' => 'blue',
        'Waiting Approval' => 'yellow',
        'Rejected' => 'red',
        default => 'slate',
    };
    $details = $docData['details'] ?? [];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Payroll #{{ $payroll['Payroll_Number'] ?? $payroll['Payroll_ID'] }}" 
        description="Pegawai: {{ $docData['employee']['Full_Name'] ?? $payroll['Employee_ID'] }} ({{ $payroll['Employee_ID'] }})"
        status="{{ $status }}"
        badgeColor="{{ $badgeColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Penggajian' => route('payrolls.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('payrolls.pdf', $payroll['Payroll_ID']) }}" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                📄 PDF Slip Gaji Resmi
            </a>

            @if($status === 'Draft' || $status === 'Calculated')
                <form action="{{ route('payrolls.submit', $payroll['Payroll_ID']) }}" method="POST" onsubmit="return confirm('Ajukan payroll ini untuk persetujuan?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        🚀 Ajukan Persetujuan
                    </button>
                </form>
            @endif

            @if($status === 'Waiting Approval')
                <form action="{{ route('payrolls.approve', $payroll['Payroll_ID']) }}" method="POST" onsubmit="return confirm('Setujui (Approve) penggajian ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ✅ Setujui Payroll
                    </button>
                </form>
                <form action="{{ route('payrolls.reject', $payroll['Payroll_ID']) }}" method="POST" onsubmit="return confirm('Tolak (Reject) penggajian ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ❌ Tolak Payroll
                    </button>
                </form>
            @endif

            @if($status === 'Approved')
                <div x-data="{ openPayModal: false }" class="inline">
                    <button @click="openPayModal = true" type="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        💵 Eksekusi Pembayaran Gaji
                    </button>
                    
                    <!-- Payment Modal -->
                    <div x-show="openPayModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" style="display: none;">
                        <div @click.away="openPayModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden text-left">
                            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 class="font-bold text-slate-800 text-sm">Cairkan Gaji & Rekam Jurnal Kas Expense</h3>
                                <button @click="openPayModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                            </div>
                            <form action="{{ route('payrolls.pay', $payroll['Payroll_ID']) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                                @csrf
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Sistem akan menandai gaji sebesar <strong>Rp {{ number_format((float)($payroll['Net_Salary'] ?? 0), 0, ',', '.') }}</strong> sebagai Lunas (Paid) dan secara otomatis merekam transaksi pengeluaran kas (Phase D Ledger).
                                </p>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Bukti Transfer (Gambar / PDF)</label>
                                    <input type="file" name="Payment_Proof" accept="image/*,.pdf" class="w-full text-xs text-slate-500 border border-slate-200 rounded-xl p-2 cursor-pointer">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                                    <textarea name="Notes" rows="2" class="w-full text-xs text-slate-700 border border-slate-200 rounded-xl p-3" placeholder="Catatan pembayaran gaji..."></textarea>
                                </div>
                                
                                <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                                    <button type="button" @click="openPayModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-md">Proses Pembayaran</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <!-- FINANCIAL SUMMARY CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-900 text-white p-5 rounded-2xl shadow-md">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gaji Bersih Diterima (Net)</p>
                        <p class="text-2xl font-black mt-1 text-emerald-400">Rp {{ number_format((float)($payroll['Net_Salary'] ?? 0), 0, ',', '.') }}</p>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Gaji Kotor (Gross)</p>
                        <p class="text-2xl font-black text-slate-800 mt-1">
                            Rp {{ number_format((float)($details['gross_salary'] ?? (($payroll['Base_Salary'] ?? 0) + ($payroll['Total_Allowances'] ?? 0))), 0, ',', '.') }}
                        </p>
                    </div>

                    <div class="bg-rose-50 border border-rose-100 p-5 rounded-2xl">
                        <p class="text-xs font-bold text-rose-800 uppercase tracking-wider">Total Potongan (Deduction)</p>
                        <p class="text-2xl font-black text-rose-700 mt-1">
                            - Rp {{ number_format((float)($payroll['Total_Deductions'] ?? 0), 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <!-- SALARY BREAKDOWN -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Rincian Perhitungan Komponen Gaji</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- INCOME -->
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                            <h4 class="text-xs font-bold text-emerald-700 uppercase border-b border-slate-200 pb-2">Komponen Penerimaan (+)</h4>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Gaji Pokok (Base Salary):</span>
                                <span class="font-bold text-slate-800">Rp {{ number_format((float)($payroll['Base_Salary'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Tunjangan & Inisiatif:</span>
                                <span class="font-bold text-emerald-600">+ Rp {{ number_format((float)($payroll['Total_Allowances'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            @if(($details['bonus'] ?? 0) > 0)
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-600">Bonus Kinerja:</span>
                                    <span class="font-bold text-emerald-600">+ Rp {{ number_format((float)$details['bonus'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- DEDUCTION -->
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                            <h4 class="text-xs font-bold text-rose-700 uppercase border-b border-slate-200 pb-2">Komponen Potongan (-)</h4>
                            @if(($details['late_deduction'] ?? 0) > 0)
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-600">Potongan Terlambat (Phase F QR):</span>
                                    <span class="font-bold text-rose-600">- Rp {{ number_format((float)$details['late_deduction'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(($details['absence_deduction'] ?? 0) > 0)
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-600">Potongan Mangkir:</span>
                                    <span class="font-bold text-rose-600">- Rp {{ number_format((float)$details['absence_deduction'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Pajak PPh21:</span>
                                <span class="font-bold text-rose-600">- Rp {{ number_format((float)($payroll['Tax'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Iuran BPJS:</span>
                                <span class="font-bold text-rose-600">- Rp {{ number_format((float)($payroll['BPJS'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Metadata System & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Payroll (Primary Key)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payroll['Payroll_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">No. Dokumen Slip</p>
                        <p class="text-sm font-mono font-bold text-blue-600 mt-1">{{ $payroll['Document_Number'] ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
