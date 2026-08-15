@extends('layouts.app')
@section('header', 'Buat Tagihan & Invoice')
@section('content')
<div class="space-y-6" x-data="invoiceForm()">
    <x-page-header title="Tagihan Baru" description="Buat tagihan baru dengan rincian komponen biaya (Itemized Billing)." :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Tagihan' => route('invoices.index'), 'Buat Tagihan' => '#']" />
    
    <form action="{{ route('invoices.store') }}" method="POST">
        @csrf
        <input type="hidden" name="Invoice_Type" value="STUDENT">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden space-y-6 p-6">
            
            <div class="border-b border-slate-100 pb-4">
                <h3 class="text-sm font-bold text-slate-800">Identitas Tagihan & Penerima</h3>
                <p class="text-xs text-slate-500 mt-0.5">Pilih siswa target penerima tagihan dan kategori utama.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Filter Batch</label>
                    <select x-model="selectedBatch" @change="studentId = ''; student = {}; isSelected = false" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-3 py-2 shadow-sm">
                        <option value="">-- Semua Batch --</option>
                        @foreach($batches as $b)
                            <option value="{{ $b['Batch_ID'] ?? '' }}">{{ $b['Batch_Name'] ?? 'Tidak diketahui' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Filter Kelas</label>
                    <select x-model="selectedClass" @change="studentId = ''; student = {}; isSelected = false" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-3 py-2 shadow-sm">
                        <option value="">-- Semua Kelas --</option>
                        @foreach($classes as $c)
                            <option value="{{ $c['Class_ID'] ?? '' }}">{{ $c['Class_Name'] ?? 'Tidak diketahui' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Siswa Target <span class="text-rose-500 font-black">*</span></label>
                    <div class="relative" @click.away="open = false">
                        <input type="hidden" name="Student_ID" x-model="studentId" required>
                        
                        <button 
                            type="button" 
                            @click="open = !open"
                            class="w-full flex justify-between items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-[13px] shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                            <span x-text="selectedStudentName" :class="{'text-slate-400': !studentId, 'text-slate-800 font-bold': studentId}" class="truncate block text-left w-full"></span>
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="open" 
                             x-transition.opacity
                             class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden"
                             style="display: none;"
                        >
                            <div class="p-2 border-b border-slate-100">
                                <input type="text" x-model="search" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:border-emerald-500 focus:bg-white" placeholder="Cari nama atau NIS..." @keydown.escape="open = false">
                            </div>
                            <ul class="max-h-60 overflow-y-auto p-1 text-xs">
                                <template x-for="s in filteredStudents" :key="s.Student_ID">
                                    <li @click="selectStudent(s.Student_ID)"
                                        class="px-3 py-2 cursor-pointer rounded-lg transition-colors hover:bg-emerald-50 hover:text-emerald-700"
                                        :class="{'bg-emerald-50 text-emerald-700 font-bold': studentId === s.Student_ID, 'text-slate-700': studentId !== s.Student_ID}"
                                    >
                                        <span x-text="s.Full_Name + ' (' + (s.Student_Number || '-') + ')'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Kategori Tagihan <span class="text-rose-500 font-black">*</span></label>
                    <select name="Category" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-4 py-2.5 shadow-sm" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Jatuh Tempo <span class="text-rose-500 font-black">*</span></label>
                    <input type="date" name="Due_Date" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 px-4 py-2.5 shadow-sm" value="{{ date('Y-m-d', strtotime('+14 days')) }}" required>
                </div>
            </div>

            <!-- ITEMIZED LINE ITEMS SECTION -->
            <div class="border-t border-slate-100 pt-6 space-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Rincian Komponen Biaya (Itemized Billing)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Tambahkan satu atau lebih item komponen biaya tagihan.</p>
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
                <textarea name="Notes" rows="3" class="w-full text-xs rounded-xl border-slate-200 focus:ring-2 focus:ring-emerald-500 p-3 shadow-sm" placeholder="Catatan tambahan untuk penerima tagihan..."></textarea>
            </div>
            
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('invoices.index') }}" class="px-6 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md transition-colors">
                    Simpan Sebagai Draft
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function invoiceForm() {
        return {
            studentId: '',
            isSelected: false,
            student: {},
            open: false,
            search: '',
            selectedBatch: '',
            selectedClass: '',
            students: @json($students->values()),
            items: [
                { description: 'SPP Bulan Ini', qty: 1, unit_price: 500000, discount: 0, tax: 0 }
            ],

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
            },
            
            get filteredStudents() {
                let filtered = this.students;
                if (this.selectedBatch !== '') {
                    filtered = filtered.filter(s => s.Batch_ID === this.selectedBatch);
                }
                if (this.selectedClass !== '') {
                    filtered = filtered.filter(s => s.Class_ID === this.selectedClass);
                }
                if (this.search !== '') {
                    filtered = filtered.filter(s => (s.Full_Name || '').toLowerCase().includes(this.search.toLowerCase()) || (s.Student_Number || '').toLowerCase().includes(this.search.toLowerCase()));
                }
                return filtered;
            },
            
            get selectedStudentName() {
                if (!this.studentId) return '-- Pilih Siswa Target --';
                let s = this.students.find(s => s.Student_ID === this.studentId);
                return s ? s.Full_Name + ' (' + (s.Student_Number || '-') + ')' : '-- Pilih Siswa Target --';
            },

            selectStudent(id) {
                this.studentId = id;
                this.open = false;
            }
        }
    }
</script>
@endsection
