@extends('layouts.app')
@section('header', 'Edit Tagihan & Invoice')
@section('content')
<div class="space-y-6" x-data="invoiceForm()">
    <x-page-header title="Edit Tagihan #{{ $invoice['Invoice_ID'] }}" description="Perbarui rincian komponen biaya (Itemized Billing) tagihan." :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Tagihan' => route('invoices.index'), 'Edit' => '#']" />
    
    <form action="{{ route('invoices.update', $invoice['Invoice_ID']) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="Invoice_Type" value="{{ $invoice['Invoice_Type'] ?? 'STUDENT' }}">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden space-y-6 p-6">
            
            <div class="border-b border-slate-100 pb-4">
                <h3 class="text-sm font-bold text-slate-800">Identitas Tagihan & Penerima</h3>
                <p class="text-xs text-slate-500 mt-0.5">Perbarui informasi utama tagihan.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Siswa Target <span class="text-rose-500 font-black">*</span></label>
                    <input type="text" value="{{ $invoice['Student_ID'] ?? $invoice['Company_ID'] ?? '' }}" readonly class="block w-full text-[13px] font-bold rounded-xl bg-slate-100 border-slate-200 text-slate-800 px-4 py-2.5 shadow-sm cursor-not-allowed">
                    <input type="hidden" name="Student_ID" value="{{ $invoice['Student_ID'] ?? '' }}">
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Kategori Tagihan <span class="text-rose-500 font-black">*</span></label>
                    <select name="Category" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-4 py-2.5 shadow-sm" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ ($invoice['Category'] ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Jatuh Tempo <span class="text-rose-500 font-black">*</span></label>
                    <input type="date" name="Due_Date" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-4 py-2.5 shadow-sm" value="{{ $invoice['Due_Date'] ?? date('Y-m-d') }}" required>
                </div>
            </div>

            <!-- ITEMIZED LINE ITEMS SECTION -->
            <div class="border-t border-slate-100 pt-6 space-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Rincian Komponen Biaya (Itemized Billing)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola item komponen biaya untuk tagihan ini.</p>
                    </div>
                    <button type="button" @click="addItem()" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold transition-colors flex items-center gap-1">
                        ➕ Tambah Item
                    </button>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-500 uppercase">
                                <th class="p-3">Deskripsi Komponen Item</th>
                                <th class="p-3 w-20 text-center">Qty</th>
                                <th class="p-3 w-36 text-right">Harga Satuan (Rp)</th>
                                <th class="p-3 w-28 text-right">Diskon (Rp)</th>
                                <th class="p-3 w-28 text-right">Pajak (Rp)</th>
                                <th class="p-3 w-36 text-right">Subtotal</th>
                                <th class="p-3 w-12 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b border-slate-100">
                                    <td class="p-2">
                                        <input type="text" :name="'items[' + index + '][description]'" x-model="item.description" class="w-full text-xs rounded-xl border-slate-200 focus:ring-emerald-500 p-2" placeholder="Nama Komponen / Biaya..." required>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" :name="'items[' + index + '][qty]'" x-model.number="item.qty" min="1" class="w-full text-xs rounded-xl border-slate-200 text-center focus:ring-emerald-500 p-2" required>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" min="0" class="w-full text-xs rounded-xl border-slate-200 text-right focus:ring-emerald-500 p-2" required>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" :name="'items[' + index + '][discount]'" x-model.number="item.discount" min="0" class="w-full text-xs rounded-xl border-slate-200 text-right focus:ring-emerald-500 p-2">
                                    </td>
                                    <td class="p-2">
                                        <input type="number" :name="'items[' + index + '][tax]'" x-model.number="item.tax" min="0" class="w-full text-xs rounded-xl border-slate-200 text-right focus:ring-emerald-500 p-2">
                                    </td>
                                    <td class="p-2 text-right font-bold text-slate-800 align-middle">
                                        Rp <span x-text="formatNumber(calculateSubtotal(item))"></span>
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" @click="removeItem(index)" class="p-1 text-rose-500 hover:text-rose-700 transition-colors" title="Hapus Item" x-show="items.length > 1">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-slate-50 text-xs font-bold">
                            <tr>
                                <td colspan="5" class="p-3 text-right text-slate-500 uppercase">Grand Total Tagihan:</td>
                                <td class="p-3 text-right text-emerald-600 text-sm font-black">
                                    Rp <span x-text="formatNumber(calculateGrandTotal())"></span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Catatan / Instruksi Pembayaran (Opsional)</label>
                <textarea name="Notes" rows="3" class="w-full text-xs rounded-xl border-slate-200 focus:ring-2 focus:ring-emerald-500 p-3 shadow-sm">{{ $invoice['Notes'] ?? '' }}</textarea>
            </div>
            
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('invoices.show', $invoice['Invoice_ID']) }}" class="px-6 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md transition-colors">
                    Simpan Perubahan Invoice
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function invoiceForm() {
        return {
            items: @json($invoice['Parsed_Line_Items'] ?? []),

            init() {
                if (!this.items || this.items.length === 0) {
                    this.items = [{ description: '{{ $invoice['Category'] ?? 'Tagihan' }}', qty: 1, unit_price: {{ $invoice['Amount'] ?? 0 }}, discount: 0, tax: 0 }];
                }
            },

            addItem() {
                this.items.push({ description: '', qty: 1, unit_price: 0, discount: 0, tax: 0 });
            },

            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },

            calculateSubtotal(item) {
                let qty = parseFloat(item.qty) || 0;
                let price = parseFloat(item.unit_price) || 0;
                let disc = parseFloat(item.discount) || 0;
                let tax = parseFloat(item.tax) || 0;
                return Math.max(0, (qty * price) - disc + tax);
            },

            calculateGrandTotal() {
                return this.items.reduce((sum, item) => sum + this.calculateSubtotal(item), 0);
            },

            formatNumber(val) {
                return new Intl.NumberFormat('id-ID').format(val);
            }
        }
    }
</script>
@endsection
