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

    @if(($invoiceGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
            @foreach($invoiceGroups as $group)
                @php
                    $groupBadgeClasses = match($group['id']) {
                        'OVERDUE' => 'bg-rose-50 text-rose-700',
                        'Waiting Payment' => 'bg-amber-50 text-amber-700',
                        'Partial Paid' => 'bg-purple-50 text-purple-700',
                        'Paid' => 'bg-emerald-50 text-emerald-700',
                        'Cancelled' => 'bg-slate-100 text-slate-700',
                        default => 'bg-blue-50 text-blue-700',
                    };
                @endphp
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex flex-col gap-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} tagihan | Total Rp {{ number_format($group['amount'], 0, ',', '.') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                            <span class="px-2.5 py-1 rounded-lg {{ $groupBadgeClasses }} text-xs font-black">Sisa Rp {{ number_format($group['remaining'], 0, ',', '.') }}</span>
                            <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                        </div>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $invoice)
                            <a href="{{ route('invoices.show', $invoice['Invoice_ID']) }}" class="flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $invoice['student_name'] ?? $invoice['Company_Name'] ?? $invoice['Student_ID'] ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono">{{ $invoice['Invoice_ID'] ?? '-' }} | {{ $invoice['Category'] ?? '-' }}</p>
                                </div>
                                <span class="text-sm font-black text-slate-800 shrink-0">Rp {{ number_format((float)($invoice['Remaining_Amount'] ?? $invoice['Amount'] ?? 0), 0, ',', '.') }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

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
                    <div class="text-xs font-black text-slate-800 mt-0.5">{{ $item['student_name'] ?? \App\Helpers\UserResolverHelper::getName($item['Student_ID'] ?? $item['Company_ID'] ?? '') }}</div>
                    @if(!empty($item['class_name']) && $item['class_name'] !== '-')
                        <div class="flex items-center gap-1 mt-1 flex-wrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-50 text-blue-700 border border-blue-200/60 inline-flex items-center gap-1">
                                🏫 {{ $item['class_name'] }}
                            </span>
                            @if(!empty($item['batch_name']) && $item['batch_name'] !== '-')
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1">
                                    🏷️ Batch {{ $item['batch_name'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                    <div class="text-[10px] font-mono text-slate-400 mt-0.5">{{ $item['Student_ID'] ?? ($item['Company_ID'] ?? '-') }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $item['Category'] ?? '-' }}</div>
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
                    <div class="wms-action-group">
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
    <div class="mx-4 max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl space-y-4 sm:p-6">
        <h3 class="text-lg font-black text-slate-800 border-b pb-2">Kirim Peringatan Penagihan</h3>
        <p class="text-xs text-slate-600">Kirimkan notifikasi pengingat pembayaran tagihan kepada siswa: <strong x-text="studentName"></strong>.</p>
        
        <form x-bind:action="'/finance/invoices/' + selectedInvoice + '/notify'" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pesan Notifikasi</label>
                <textarea name="message" x-model="message" rows="4" class="w-full text-xs rounded-xl border-slate-200 focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            
            <div class="grid grid-cols-1 gap-2 pt-2 sm:flex sm:justify-end">
                <button type="button" @click="openNotify = false" class="min-h-11 px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" class="min-h-11 px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-md">Kirim Pengingat</button>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
