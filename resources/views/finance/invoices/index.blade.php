@extends('layouts.app')
@section('header', 'Manajemen Tagihan & Invoice')
@section('content')

<div x-data="{
    openNotify: false,
    selectedInvoice: null,
    studentName: '',
    category: '',
    amount: '',
    dueDate: '',
    message: '',
    
    openModal(id, student, cat, amt, due) {
        this.selectedInvoice = id;
        this.studentName = student;
        this.category = cat;
        this.amount = amt;
        this.dueDate = due;
        this.message = `Peringatan Tagihan: Anda memiliki tagihan ${cat} sebesar Rp ${amt} yang belum dibayar (Jatuh Tempo: ${due}). Mohon segera hubungi bagian Keuangan.`;
        this.openNotify = true;
    }
}">

<x-universal.index-layout 
    title="Data Tagihan & Invoice" 
    description="Kelola tagihan, rincian komponen biaya (Itemized Billing), penerbitan invoice PDF resmi, pelacakan sisa piutang, dan status keterlambatan (Overdue)."
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Tagihan' => route('invoices.index')]"
    add-action="{{ route('invoices.create') }}"
    add-text="Buat Tagihan Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="invoices" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('invoices.index') }}" 
            refresh-url="{{ route('invoices.index') }}"
            export-url="{{ route('invoices.export-pdf') }}"
        >
            <div class="w-full md:w-auto">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Status Tagihan</option>
                    <option value="Draft" {{ request('status') == 'Draft' ? 'selected' : '' }}>📝 Draft</option>
                    <option value="Waiting Payment" {{ request('status') == 'Waiting Payment' ? 'selected' : '' }}>⏳ Menunggu Pembayaran (Waiting Payment)</option>
                    <option value="Partial Paid" {{ request('status') == 'Partial Paid' ? 'selected' : '' }}>🟪 Dibayar Sebagian (Partial Paid)</option>
                    <option value="OVERDUE" {{ request('status') == 'OVERDUE' ? 'selected' : '' }}>⚠️ TERLAMBAT (OVERDUE)</option>
                    <option value="Paid" {{ request('status') == 'Paid' ? 'selected' : '' }}>✅ LUNAS (Paid)</option>
                    <option value="Cancelled" {{ request('status') == 'Cancelled' ? 'selected' : '' }}>❌ Dibatalkan (Cancelled)</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($invoices) === 0" empty-title="Data Tagihan Kosong" empty-description="Belum ada data tagihan yang sesuai dengan kriteria.">
        <x-slot:header>
            <th class="px-6 py-4">ID & Pihak Tagihan</th>
            <th class="px-6 py-4">Kategori & Rincian</th>
            <th class="px-6 py-4 text-center">Grand Total</th>
            <th class="px-6 py-4 text-center">Sisa Piutang</th>
            <th class="px-6 py-4 text-center">Jatuh Tempo</th>
            <th class="px-6 py-4 text-center">Status Tagihan</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($invoices as $item)
            @php
                $status = $item['Status'] ?? 'Draft';
                $amount = (float)($item['Grand_Total'] ?? $item['Amount'] ?? 0);
                $remaining = (float)($item['Remaining_Amount'] ?? $amount);
            @endphp
            <tr class="hover:bg-slate-50 transition-colors {{ $status === 'OVERDUE' ? 'bg-rose-50/40' : '' }}">
                <td class="px-6 py-4">
                    <div class="font-mono font-bold text-slate-800 text-sm">{{ $item['Invoice_ID'] ?? '' }}</div>
                    <div class="text-xs font-bold text-slate-700 mt-0.5">{{ $item['student_name'] ?? ($item['Company_Name'] ?? '-') }}</div>
                    <div class="text-[11px] text-slate-500">{{ $item['Student_ID'] ?? ($item['Company_ID'] ?? '-') }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $item['Category'] ?? '-' }}</div>
                    <div class="text-xs text-slate-500 font-medium">{{ trim(($item['class_name'] ?? '') . ' / ' . ($item['batch_name'] ?? ''), ' /') }}</div>
                </td>
                <td class="px-6 py-4 text-center font-black text-slate-800 text-sm">
                    Rp {{ number_format($amount, 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-center font-bold text-sm {{ $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    Rp {{ number_format($remaining, 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-center">
                    @if(!empty($item['Due_Date']))
                        <span class="text-xs font-bold {{ $status === 'OVERDUE' ? 'text-rose-600' : 'text-slate-700' }}">
                            {{ \Carbon\Carbon::parse($item['Due_Date'])->format('d M Y') }}
                        </span>
                        @if($status === 'OVERDUE')
                            <div class="text-[10px] font-extrabold text-rose-500 uppercase mt-0.5">Lewat Jatuh Tempo</div>
                        @endif
                    @else
                        <span class="text-xs text-slate-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    @if($status === 'OVERDUE')
                        <span class="px-3 py-1 text-xs font-black rounded-lg bg-rose-500 text-white shadow-xs inline-flex items-center gap-1 uppercase">
                            ⚠️ OVERDUE
                        </span>
                    @elseif($status === 'Paid')
                        <span class="px-3 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-800 inline-flex items-center gap-1 uppercase">
                            ✅ LUNAS (PAID)
                        </span>
                    @elseif($status === 'Partial Paid')
                        <span class="px-3 py-1 text-xs font-bold rounded-lg bg-purple-100 text-purple-800 inline-flex items-center gap-1 uppercase">
                            🟪 PARTIAL PAID
                        </span>
                    @elseif($status === 'Waiting Payment')
                        <span class="px-3 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800 inline-flex items-center gap-1 uppercase">
                            ⏳ MENUNGGU
                        </span>
                    @elseif($status === 'Cancelled')
                        <span class="px-3 py-1 text-xs font-bold rounded-lg bg-slate-200 text-slate-700 inline-flex items-center gap-1 uppercase">
                            ❌ DIBATALKAN
                        </span>
                    @else
                        <span class="px-3 py-1 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 inline-flex items-center gap-1 uppercase">
                            📝 DRAFT
                        </span>
                    @endif
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('invoices.pdf', $item['Invoice_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh PDF Invoice Resmi">
                            📄 PDF
                        </a>

                        <x-universal.action-button action="detail" url="{{ route('invoices.show', $item['Invoice_ID']) }}" />

                        @if($status === 'Draft')
                            <form action="{{ route('invoices.publish', $item['Invoice_ID']) }}" method="POST" onsubmit="return confirm('Terbitkan tagihan ini?');" class="inline">
                                @csrf
                                <button type="submit" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-xs transition-colors">
                                    Publish
                                </button>
                            </form>
                            <x-universal.action-button action="edit" url="{{ route('invoices.edit', $item['Invoice_ID']) }}" />
                        @endif

                        @if(in_array($status, ['Waiting Payment', 'Partial Paid', 'OVERDUE']))
                            <button @click="openModal('{{ $item['Invoice_ID'] }}', '{{ addslashes($item['student_name'] ?? '') }}', '{{ addslashes($item['Category'] ?? '') }}', '{{ number_format($remaining, 0, ',', '.') }}', '{{ !empty($item['Due_Date']) ? \Carbon\Carbon::parse($item['Due_Date'])->format('d M Y') : '-' }}')" 
                                    class="px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-bold text-xs shadow-xs transition-colors" title="Kirim Notifikasi">
                                Notifikasi
                            </button>
                            
                            <form action="{{ route('invoices.cancel', $item['Invoice_ID']) }}" method="POST" onsubmit="return confirm('Batalkan tagihan ini?');" class="inline">
                                @csrf
                                <button type="submit" class="px-2.5 py-1.5 bg-slate-200 hover:bg-rose-100 text-slate-700 hover:text-rose-700 rounded-lg font-bold text-xs transition-colors">
                                    Batal
                                </button>
                            </form>
                        @endif

                        @if($status === 'Draft')
                            <x-universal.action-button action="delete" url="{{ route('invoices.destroy', $item['Invoice_ID']) }}" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($invoices, 'links'))
                <x-universal.pagination :paginator="$invoices" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

<!-- MODAL NOTIFIKASI REMINDER -->
<div x-show="openNotify" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-cloak>
    <div class="bg-white rounded-2xl p-6 max-w-lg w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-black text-slate-800 border-b pb-2">Kirim Peringatan Penagihan</h3>
        <p class="text-xs text-slate-600">Kirimkan notifikasi pengingat pembayaran tagihan kepada siswa: <strong x-text="studentName"></strong>.</p>
        
        <form x-bind:action="'/finance/invoices/' + selectedInvoice + '/notify'" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pesan Notifikasi</label>
                <textarea name="message" x-model="message" rows="4" class="w-full text-xs rounded-xl border-slate-200 focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="openNotify = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-md">Kirim Pengingat</button>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
