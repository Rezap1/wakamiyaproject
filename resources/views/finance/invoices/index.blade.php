@extends('layouts.app')
@section('header', 'Manajemen Tagihan')
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
    title="Tagihan" 
    description="Kelola tagihan dan invoice siswa."
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Tagihan' => route('invoices.index')]"
    add-action="{{ route('invoices.create') }}"
    add-text="Buat Tagihan"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="invoices" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('invoices.index') }}" 
            refresh-url="{{ route('invoices.index') }}"
            export-url="#"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($invoices) === 0" empty-title="Data Tagihan Kosong" empty-description="Belum ada data tagihan.">
        <x-slot:header>
            <th class="px-6 py-4">ID Tagihan</th>
            <th class="px-6 py-4">Siswa</th>
            <th class="px-6 py-4">Kelas / Batch</th>
            <th class="px-6 py-4">Kategori</th>
            <th class="px-6 py-4 text-center">Nominal</th>
            <th class="px-6 py-4 text-center">Jatuh Tempo</th>
            <th class="px-6 py-4 text-center">Tgl Dibuat</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($invoices as $item)
            @php
                $status = $item['Status'] ?? 'Draft';
                $badgeColor = match($status) {
                    'Paid' => 'green',
                    'Waiting Payment' => 'yellow',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                <td class="px-6 py-4">
                    <div>
                        <p class="font-bold text-slate-800 text-sm">{{ $item['student_name'] ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ $item['Student_ID'] ?? '-' }}</p>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">{{ $item['class_name'] ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ $item['batch_name'] ?? '-' }}</p>
                    </div>
                </td>
                <td class="px-6 py-4 font-semibold">{{ $item['Category'] ?? '' }}</td>
                <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-center">
                    @if(!empty($item['Due_Date']))
                        <span class="text-sm font-medium text-slate-700">{{ \Carbon\Carbon::parse($item['Due_Date'])->format('d M Y') }}</span>
                    @else
                        <span class="text-xs text-slate-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    @if(!empty($item['Created_At']))
                        <span class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($item['Created_At'])->format('d M Y') }}</span>
                    @else
                        <span class="text-xs text-slate-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($status == 'Draft')
                            <form action="{{ route('invoices.publish', $item['Invoice_ID']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" onclick="return confirm('Publikasikan tagihan ini? Status akan menjadi Menunggu Pembayaran dan siswa akan menerima notifikasi.')" class="px-2 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center justify-center gap-1 shadow-sm" title="Publikasi">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                    Publikasi
                                </button>
                            </form>
                        @endif
                        @if($status != 'Paid' && $status != 'Draft')
                            <button type="button" 
                                @click="openModal('{{ $item['Invoice_ID'] }}', '{{ $item['student_name'] ?? $item['Student_ID'] }}', '{{ $item['Category'] ?? '-' }}', '{{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}', '{{ $item['Due_Date'] ?? '-' }}')" 
                                class="px-2 py-1 bg-amber-50 text-amber-600 border border-amber-200 rounded-lg text-xs font-bold hover:bg-amber-100 transition-colors flex items-center justify-center gap-1 shadow-sm" title="Notifikasi">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                Notifikasi
                            </button>
                        @endif
                        <x-universal.action-button action="detail" url="{{ route('invoices.show', $item['Invoice_ID']) }}" />
                        <x-universal.action-button action="edit" url="{{ route('invoices.edit', $item['Invoice_ID']) }}" />
                        <form action="{{ route('invoices.destroy', $item['Invoice_ID']) }}" method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus invoice ini?');">
                            @csrf
                            @method('DELETE')
                            <x-universal.action-button action="delete" type="submit" url="#" />
                        </form>
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

    <!-- Notification Modal -->
    <div x-show="openNotify" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-transition>
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden" @click.outside="openNotify = false">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Kirim Notifikasi Tagihan</h3>
                <button @click="openNotify = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form :action="`{{ url('finance/invoices') }}/${selectedInvoice}/notify`" method="POST">
                @csrf
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <div><p class="text-xs text-blue-400 font-bold uppercase">Siswa</p><p class="font-bold text-blue-900" x-text="studentName"></p></div>
                        <div><p class="text-xs text-blue-400 font-bold uppercase">No Tagihan</p><p class="font-bold text-blue-900" x-text="selectedInvoice"></p></div>
                        <div><p class="text-xs text-blue-400 font-bold uppercase">Kategori</p><p class="font-bold text-blue-900" x-text="category"></p></div>
                        <div><p class="text-xs text-blue-400 font-bold uppercase">Nominal</p><p class="font-bold text-blue-900">Rp <span x-text="amount"></span></p></div>
                        <div><p class="text-xs text-blue-400 font-bold uppercase">Jatuh Tempo</p><p class="font-bold text-rose-600" x-text="dueDate"></p></div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Pesan Notifikasi</label>
                        <textarea name="message" x-model="message" rows="4" class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm" required></textarea>
                        <p class="text-xs text-slate-400 mt-1">Anda dapat mengubah pesan ini sebelum dikirim.</p>
                    </div>
                </div>
                <div class="p-6 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
                    <button type="button" @click="openNotify = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">Batal</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700">Kirim Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection



