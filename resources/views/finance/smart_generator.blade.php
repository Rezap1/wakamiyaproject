@extends('layouts.app')
@section('header', 'Smart Generator Invoice & Kwitansi Pro V3')

@section('content')
<div x-data="smartGenerator()" x-init="init()" class="space-y-6">

    <!-- TOP NAVBAR HEADER -->
    <div class="bg-slate-900 text-white rounded-2xl p-4 border border-slate-800 shadow-xl flex flex-wrap items-center justify-between gap-4">
        <!-- BRAND BADGE -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-black shadow-lg shadow-emerald-500/30">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-base font-black tracking-wide text-white" x-text="company.company_name || 'PT WAKAMIYA MANDIRI SEJAHTERA'"></h1>
                <p class="text-xs font-semibold text-emerald-400">Smart Generator Invoice & Kwitansi Pro V3</p>
            </div>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="flex items-center bg-slate-800/80 p-1.5 rounded-xl border border-slate-700/60">
            <button @click="activeTab = 'kop'" :class="{'bg-emerald-600 text-white shadow-md': activeTab === 'kop', 'text-slate-400 hover:text-slate-200': activeTab !== 'kop'}" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5">
                📁 <span>Profil Kop Surat</span>
            </button>
            <button @click="activeTab = 'invoice'" :class="{'bg-emerald-600 text-white shadow-md': activeTab === 'invoice', 'text-slate-400 hover:text-slate-200': activeTab !== 'invoice'}" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5">
                📄 <span>Invoice</span>
            </button>
            <button @click="activeTab = 'kwitansi'" :class="{'bg-emerald-600 text-white shadow-md': activeTab === 'kwitansi', 'text-slate-400 hover:text-slate-200': activeTab !== 'kwitansi'}" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5">
                📜 <span>Kwitansi</span>
            </button>
            <button @click="activeTab = 'history'" :class="{'bg-emerald-600 text-white shadow-md': activeTab === 'history', 'text-slate-400 hover:text-slate-200': activeTab !== 'history'}" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 relative">
                ⏱️ <span>Riwayat</span>
                <span x-show="history.length > 0" x-text="history.length" class="ml-1 px-1.5 py-0.5 bg-emerald-500 text-white rounded-full text-[10px] font-black"></span>
            </button>
        </div>

        <!-- RIGHT ACTIONS -->
        <div class="flex items-center gap-3">
            <button @click="openEmailModal = true" class="px-3.5 py-2 bg-emerald-600/30 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/40 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                🚀 <span>Kirim Email</span>
            </button>
            <div class="flex items-center gap-2 pl-2 border-l border-slate-700">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-xs font-bold text-slate-300">admin</span>
            </div>
        </div>
    </div>

    <!-- MAIN DUAL PANE GENERATOR GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT FORM CONTROL PANE (6 COLS) -->
        <div class="lg:col-span-6 bg-slate-900/95 text-slate-200 rounded-2xl p-5 border border-slate-800 shadow-xl space-y-6">
            
            <!-- HEADER PANE CONTROLS -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider" x-text="getPaneTitle()"></h2>
                    <p class="text-xs text-slate-400 mt-0.5">Kelola input data dokumen di bawah. Pratinjau diperbarui secara real-time.</p>
                </div>
                <div class="flex items-center gap-2" x-show="activeTab === 'invoice' || activeTab === 'kwitansi'">
                    <button type="button" @click="loadSampleData()" class="px-3 py-1.5 bg-emerald-950/50 text-emerald-300 hover:bg-emerald-600 hover:text-white border border-emerald-700/50 rounded-xl text-xs font-bold transition-all flex items-center gap-1">
                        🛠️ <span>Data Contoh</span>
                    </button>
                    <button type="button" @click="resetForm()" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-600 hover:text-white border border-rose-700/50 rounded-xl text-xs font-bold transition-all flex items-center gap-1">
                        🔄 <span>Reset</span>
                    </button>
                </div>
            </div>

            <!-- MODE SWITCHER (TAGIHAN SISWA vs INVOICE MANUAL) -->
            <div x-show="activeTab === 'invoice' || activeTab === 'kwitansi'" class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 space-y-2">
                <label class="block text-[10.5px] font-black text-slate-400 uppercase tracking-wider">Sumber Dokumen Invoice:</label>
                <div class="grid grid-cols-2 gap-2.5">
                    <button type="button" @click="setSourceMode('student_invoice')" :class="{'bg-emerald-600 text-white font-black shadow-lg ring-2 ring-emerald-400': sourceMode === 'student_invoice', 'bg-slate-800 text-slate-400 font-bold hover:bg-slate-700 hover:text-white': sourceMode !== 'student_invoice'}" class="py-2.5 px-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2">
                        <span>🧑‍🎓</span> <span>TAGIHAN SISWA</span>
                    </button>
                    <button type="button" @click="setSourceMode('manual_invoice')" :class="{'bg-emerald-600 text-white font-black shadow-lg ring-2 ring-emerald-400': sourceMode === 'manual_invoice', 'bg-slate-800 text-slate-400 font-bold hover:bg-slate-700 hover:text-white': sourceMode !== 'manual_invoice'}" class="py-2.5 px-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2">
                        <span>✏️</span> <span>INVOICE MANUAL</span>
                    </button>
                </div>
            </div>

            <!-- STUDENT INVOICE SEARCH & PICKER PANEL -->
            <div x-show="(activeTab === 'invoice' || activeTab === 'kwitansi') && sourceMode === 'student_invoice'" class="bg-slate-800/90 border border-slate-700 p-4 rounded-xl space-y-3 shadow-md">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🔎</span> <span>Cari Tagihan Siswa dari Finance</span>
                    </h3>
                    <template x-if="selectedSourceId">
                        <span class="px-2.5 py-0.5 bg-emerald-900/80 text-emerald-300 border border-emerald-500 rounded text-[10px] font-bold">
                            ✓ Tagihan Finance Dipilih
                        </span>
                    </template>
                </div>

                <div class="flex gap-2">
                    <input type="text" x-model="studentSearchQuery" @keyup.enter="searchStudentInvoices()" placeholder="Cari nama / Student ID / Invoice ID..." class="flex-1 text-xs bg-slate-900 border-slate-700 rounded-xl text-white p-2.5 focus:ring-2 focus:ring-emerald-500">
                    <button type="button" @click="searchStudentInvoices()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl flex items-center gap-1 shrink-0">
                        <span>🔍 Cari</span>
                    </button>
                </div>

                <!-- Searching Loading -->
                <div x-show="isSearching" class="text-center py-3 text-xs text-slate-400 flex items-center justify-center gap-2">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-ping"></span>
                    <span>Mencari invoice siswa di Finance Engine...</span>
                </div>

                <!-- Search Results List -->
                <div x-show="!isSearching && studentSearchResults.length > 0" class="space-y-2 max-h-56 overflow-y-auto pr-1">
                    <template x-for="inv in studentSearchResults" :key="inv.id">
                        <div class="bg-slate-900 p-3 rounded-lg border border-slate-700 flex items-center justify-between gap-3 hover:border-emerald-500 transition-all">
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-emerald-400 text-xs" x-text="inv.doc_number"></span>
                                    <span class="px-2 py-0.5 text-[9.5px] font-bold rounded uppercase" :class="{'bg-emerald-900 text-emerald-200': inv.status === 'PAID' || inv.status === 'LUNAS', 'bg-amber-900 text-amber-200': inv.status !== 'PAID' && inv.status !== 'LUNAS'}" x-text="inv.status"></span>
                                </div>
                                <p class="text-xs font-bold text-white"><span x-text="inv.student_name"></span> (<span class="text-slate-400" x-text="inv.student_id"></span>)</p>
                                <p class="text-[10px] text-slate-400" x-text="inv.description || 'Tagihan Siswa'"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-black text-emerald-400" x-text="'Rp ' + Number(inv.grand_total || inv.amount || 0).toLocaleString('id-ID')"></p>
                                <button type="button" @click="selectStudentInvoice(inv)" class="mt-1 px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] rounded-lg transition-all shadow-sm">
                                    PILIH INVOICE
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="!isSearching && hasSearched && studentSearchResults.length === 0" class="text-center py-3 text-xs text-slate-400">
                    Tidak ada tagihan siswa yang cocok.
                </div>

                <!-- Read-Only Banner when invoice selected -->
                <div x-show="selectedSourceId" class="bg-emerald-950/70 border border-emerald-700/80 p-3 rounded-xl flex items-center justify-between text-xs mt-2">
                    <div class="flex items-center gap-2 text-emerald-200">
                        <span class="text-sm">🔒</span>
                        <div>
                            <p class="font-bold">Tagihan Finance: <span class="font-mono text-emerald-300" x-text="selectedSourceId"></span></p>
                            <p class="text-[10px] text-emerald-400/90">Data diambil langsung dari Finance Engine (Read-Only).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 1: PROFIL KOP SURAT FORM -->
            <div x-show="activeTab === 'kop'" class="space-y-5" x-cloak>
                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Logo Perusahaan & Layout Kop</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Unggah Logo Perusahaan (PNG/JPG)</label>
                            <input type="file" @change="handleFileUpload($event, 'company_logo')" class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500 cursor-pointer bg-slate-800/80 rounded-xl border border-slate-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Tata Letak Kop Surat (Layout)</label>
                            <select x-model="company.layout_kop" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white focus:ring-2 focus:ring-emerald-500 p-2.5">
                                <option value="left">Modern Left-Aligned (Samping)</option>
                                <option value="center">Centered (Tengah)</option>
                                <option value="classic">Classic Formal Header</option>
                            </select>
                        </div>
                    </div>

                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider pt-2">Identitas Perusahaan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Nama Perusahaan / Usaha</label>
                            <input type="text" x-model="company.company_name" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Slogan / Tagline Usaha</label>
                            <input type="text" x-model="company.company_tagline" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Alamat Lengkap Usaha</label>
                        <textarea x-model="company.company_address" rows="2" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">No. Telepon / WA</label>
                            <input type="text" x-model="company.company_phone" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Email Resmi Usaha</label>
                            <input type="email" x-model="company.company_email" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Website Perusahaan</label>
                            <input type="text" x-model="company.company_web" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Nomor NPWP</label>
                            <input type="text" x-model="company.company_npwp" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                    </div>

                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider pt-2">Informasi Rekening Bank Default</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Nama Bank</label>
                            <input type="text" x-model="company.bank_name" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Nomor Rekening</label>
                            <input type="text" x-model="company.bank_account" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Atas Nama Rekening</label>
                            <input type="text" x-model="company.bank_holder" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: INVOICE GENERATOR FORM -->
            <div x-show="activeTab === 'invoice'" class="space-y-5">
                
                <!-- DOCUMENT CONFIG -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Tema Visual Dokumen</label>
                        <select x-model="invoice.theme" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                            <option value="emerald">Emerald Clean (Default)</option>
                            <option value="indigo">Indigo Executive</option>
                            <option value="crimson">Crimson Premium</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">No. Invoice</label>
                        <input type="text" x-model="invoice.doc_number" :readonly="sourceMode === 'student_invoice'" :class="{'bg-slate-950 text-slate-400 cursor-not-allowed': sourceMode === 'student_invoice'}" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Mata Uang</label>
                        <select x-model="invoice.currency" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                            <option value="IDR">IDR (Rupiah)</option>
                            <option value="USD">USD ($)</option>
                            <option value="JPY">JPY (¥)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Tanggal Terbit</label>
                        <input type="date" x-model="invoice.issue_date" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Jatuh Tempo</label>
                        <input type="date" x-model="invoice.due_date" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Status Dokumen</label>
                        <select x-model="invoice.status" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                            <option value="UNPAID">BELUM LUNAS / UNPAID</option>
                            <option value="LUNAS">LUNAS / PAID</option>
                            <option value="OVERDUE">JATUH TEMPO / OVERDUE</option>
                        </select>
                    </div>
                </div>

                <!-- CLIENT INFO -->
                <div class="pt-2 border-t border-slate-800 space-y-3">
                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Informasi Klien / Tagihan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Nama Perusahaan / Klien</label>
                            <input type="text" x-model="invoice.client_name" :readonly="sourceMode === 'student_invoice'" :class="{'bg-slate-950 text-slate-400 cursor-not-allowed': sourceMode === 'student_invoice'}" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Email Klien (Direct Email)</label>
                            <input type="email" x-model="invoice.client_email" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Alamat Lengkap Klien</label>
                        <textarea x-model="invoice.client_address" rows="2" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5"></textarea>
                    </div>
                </div>

                <!-- ITEMIZED ITEMS -->
                <div class="pt-2 border-t border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Rincian Barang / Jasa</h3>
                        <button type="button" x-show="sourceMode === 'manual_invoice'" @click="addItem()" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                            ➕ Tambah Item
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, idx) in invoice.items" :key="idx">
                            <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/70 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <input type="text" x-model="item.name" :readonly="sourceMode === 'student_invoice'" placeholder="Nama Barang / Layanan" class="flex-1 text-xs bg-slate-900 border-slate-700 rounded-lg text-white p-2 font-semibold">
                                    <button type="button" x-show="sourceMode === 'manual_invoice'" @click="removeItem(idx)" class="text-rose-400 hover:text-rose-300 p-1">
                                        🗑️
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-0.5">Jumlah (Qty)</label>
                                        <input type="number" x-model.number="item.qty" min="1" :readonly="sourceMode === 'student_invoice'" class="w-full text-xs bg-slate-900 border-slate-700 rounded-lg text-white p-2 font-bold">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-0.5">Harga Satuan (Rp)</label>
                                        <input type="number" x-model.number="item.price" min="0" :readonly="sourceMode === 'student_invoice'" class="w-full text-xs bg-slate-900 border-slate-700 rounded-lg text-white p-2 font-bold">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- TOTALS & TAXES -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2 border-t border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Diskon (Nominal)</label>
                        <input type="number" x-model.number="invoice.discount" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">PPN (%)</label>
                        <input type="number" x-model.number="invoice.ppn_percent" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Ongkir / Lainnya</label>
                        <input type="number" x-model.number="invoice.shipping" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                </div>

                <!-- NOTES -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Catatan & Ketentuan Pembayaran</label>
                    <textarea x-model="invoice.notes" rows="2" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5"></textarea>
                </div>
            </div>

            <!-- TAB 3: KWITANSI GENERATOR FORM -->
            <div x-show="activeTab === 'kwitansi'" class="space-y-5" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Tema Visual Dokumen</label>
                        <select x-model="kwitansi.theme" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                            <option value="emerald">Emerald Clean (Default)</option>
                            <option value="indigo">Indigo Modern</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">No. Kwitansi</label>
                        <input type="text" x-model="kwitansi.doc_number" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Tanggal</label>
                        <input type="date" x-model="kwitansi.issue_date" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Status Dokumen</label>
                        <select x-model="kwitansi.status" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                            <option value="PAID / LUNAS">PAID / LUNAS</option>
                            <option value="PENDING">PENDING</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Telah Diterima Dari</label>
                        <input type="text" x-model="kwitansi.client_name" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Email Pembayar (Penerima Email)</label>
                        <input type="email" x-model="kwitansi.client_email" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Uang Sejumlah (Rp)</label>
                        <input type="number" x-model.number="kwitansi.kwitansi_amount" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 font-bold">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Untuk Pembayaran</label>
                    <textarea x-model="kwitansi.payment_for" rows="2" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Kota Terbit</label>
                        <input type="text" x-model="kwitansi.issue_city" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Nama Penandatangan</label>
                        <input type="text" x-model="kwitansi.signer_name" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
                    </div>
                </div>
            </div>

            <!-- TAB 4: RIWAYAT TABLE -->
            <div x-show="activeTab === 'history'" class="space-y-4" x-cloak>
                <div class="overflow-x-auto border border-slate-800 rounded-xl">
                    <table class="w-full text-xs text-left text-slate-300">
                        <thead class="bg-slate-800 text-slate-400 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="p-3">Sumber</th>
                                <th class="p-3">No. Dokumen</th>
                                <th class="p-3">Tipe</th>
                                <th class="p-3">Klien</th>
                                <th class="p-3">Tanggal</th>
                                <th class="p-3 text-right">Total</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <template x-for="item in history" :key="item.id">
                                <tr class="hover:bg-slate-800/50">
                                    <td class="p-3">
                                        <template x-if="item.source_type === 'student_invoice'">
                                            <span class="px-2 py-0.5 bg-emerald-950 text-emerald-300 border border-emerald-700/60 rounded text-[9.5px] font-bold">🧑‍🎓 TAGIHAN SISWA</span>
                                        </template>
                                        <template x-if="item.source_type !== 'student_invoice'">
                                            <span class="px-2 py-0.5 bg-slate-800 text-slate-400 border border-slate-700 rounded text-[9.5px] font-bold">✏️ MANUAL</span>
                                        </template>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-emerald-400" x-text="item.doc_number"></td>
                                    <td class="p-3 uppercase font-bold text-[10px]" x-text="item.doc_type"></td>
                                    <td class="p-3 font-semibold text-white" x-text="item.client_name"></td>
                                    <td class="p-3 text-slate-400" x-text="item.saved_at || item.issue_date"></td>
                                    <td class="p-3 text-right font-bold text-emerald-400" x-text="'Rp ' + Number(item.grand_total || item.kwitansi_amount || 0).toLocaleString('id-ID')"></td>
                                    <td class="p-3 text-center flex items-center justify-center gap-1.5">
                                        <button @click="loadHistoryItem(item)" class="p-1.5 bg-emerald-900/60 hover:bg-emerald-600 text-emerald-300 hover:text-white rounded-lg text-xs" title="Muat ke Editor">
                                            👁️
                                        </button>
                                        <button @click="deleteHistoryItem(item.id)" class="p-1.5 bg-rose-900/60 hover:bg-rose-600 text-rose-300 hover:text-white rounded-lg text-xs" title="Hapus">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="history.length === 0">
                                <td colspan="7" class="p-6 text-center text-slate-500 font-semibold">
                                    Belum ada riwayat dokumen tersimpan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SIGNATURE & STAMP SECTION (FOR ALL TABS) -->
            <div x-show="activeTab !== 'history'" class="pt-4 border-t border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Tanda Tangan & Stempel Digital</h3>
                    <button type="button" @click="clearSignatureCanvas()" class="text-[11px] font-bold text-rose-400 hover:text-rose-300">
                        Hapus TTD
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Coret TTD di bawah atau Upload Stempel PNG:</label>
                        <div class="bg-white rounded-xl overflow-hidden p-1 border-2 border-dashed border-slate-700">
                            <canvas id="signatureCanvas" width="400" height="120" class="w-full h-24 bg-white rounded-lg cursor-crosshair"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">Unggah Stempel File (PNG Transparan)</label>
                            <input type="file" @change="handleFileUpload($event, 'stamp')" class="block w-full text-xs text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-700 file:text-white cursor-pointer bg-slate-800 border border-slate-700 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">Unggah TTD File (PNG Transparan)</label>
                            <input type="file" @change="handleFileUpload($event, 'signature')" class="block w-full text-xs text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-700 file:text-white cursor-pointer bg-slate-800 border border-slate-700 rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS BOTTOM -->
            <div x-show="activeTab !== 'history'" class="pt-4 border-t border-slate-800 flex flex-wrap items-center gap-3">
                <button type="button" @click="exportPdf()" class="flex-1 px-4 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2">
                    📥 <span>Ekspor / Unduh PDF Presisi High</span>
                </button>
                <button type="button" @click="printDocument()" class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5">
                    🖨️ <span>Cetak Document</span>
                </button>
                <button type="button" @click="saveToHistory()" class="px-4 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-700/30 transition-all flex items-center gap-1.5">
                    💾 <span>Simpan Ke Riwayat</span>
                </button>
            </div>

        </div>

        <!-- RIGHT LIVE CANVAS PRATINJAU (A4) PANE (6 COLS) -->
        <div class="lg:col-span-6 sticky top-6">
            <div class="bg-slate-900/90 text-slate-200 rounded-2xl p-4 border border-slate-800 shadow-2xl space-y-3">
                <!-- LIVE PREVIEW HEADER -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping"></span>
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Live Canvas Pratinjau (A4)</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="exportPdf()" class="px-2.5 py-1 bg-emerald-600 text-white text-[11px] font-bold rounded-lg hover:bg-emerald-500">
                            Ekspor PDF
                        </button>
                        <button type="button" @click="printDocument()" class="px-2.5 py-1 bg-slate-800 text-slate-300 text-[11px] font-bold rounded-lg border border-slate-700 hover:bg-slate-700">
                            Print
                        </button>
                    </div>
                </div>

                <!-- SCROLLABLE A4 SHEET CANVAS CONTAINER -->
                <div class="overflow-y-auto max-h-[820px] p-4 bg-slate-950 rounded-xl border border-slate-800 flex justify-center">
                    
                    <!-- A4 REALISTIC DOCUMENT PAPER SHEET (HIGH CONTRAST & CLEAR FONTS) -->
                    <div id="a4CanvasSheet" class="bg-white text-slate-900 shadow-2xl p-8 w-full max-w-[210mm] min-h-[297mm] text-[11px] leading-relaxed relative flex flex-col justify-between font-sans transition-all duration-300 border border-slate-300 rounded-sm">
                        
                        <div>
                            <!-- KOP LETTERHEAD -->
                            <div class="border-b-[2.5px] pb-3 mb-5" :style="'border-color: ' + getThemeColors().primary">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <template x-if="company.company_logo">
                                            <img :src="company.company_logo" class="w-14 h-14 object-contain shrink-0">
                                        </template>
                                        <div>
                                            <h2 class="text-base font-black text-slate-900 uppercase tracking-tight" x-text="company.company_name || 'PT WAKAMIYA MANDIRI SEJAHTERA'"></h2>
                                            <p class="text-[10px] font-extrabold italic" :style="'color: ' + getThemeColors().primary" x-text="company.company_tagline || 'Growing Together With Integrity'"></p>
                                            <p class="text-[10px] text-slate-800 font-medium leading-snug mt-0.5" x-text="company.company_address"></p>
                                            <p class="text-[10px] text-slate-800 font-semibold mt-0.5">
                                                Telp: <span x-text="company.company_phone"></span> | Email: <span x-text="company.company_email"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right text-[9.5px] font-bold text-slate-700 shrink-0">
                                        NPWP USAHA<br>
                                        <span class="text-slate-900 font-extrabold text-[10.5px]" x-text="company.company_npwp"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- INVOICE DOCUMENT CANVAS PREVIEW -->
                            <template x-if="activeTab === 'invoice' || activeTab === 'kop'">
                                <div class="space-y-5">
                                    <!-- TITLE BAR -->
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h1 class="text-lg font-black text-slate-900 tracking-wider">INVOICE TAGIHAN</h1>
                                            <p class="text-xs font-extrabold text-slate-700">No: <span x-text="invoice.doc_number" class="font-mono"></span></p>
                                        </div>
                                        <div>
                                            <span :class="{'bg-emerald-100 text-emerald-900 border-emerald-500': invoice.status === 'LUNAS' || invoice.status === 'PAID', 'bg-amber-100 text-amber-900 border-amber-600': invoice.status !== 'LUNAS' && invoice.status !== 'PAID'}" class="px-3 py-1 text-xs font-black rounded border-2 uppercase tracking-wider shadow-sm" x-text="invoice.status"></span>
                                        </div>
                                    </div>

                                    <!-- META CLIENT & DATES -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-[9.5px] font-black text-slate-600 uppercase tracking-wide">DITUJUAN KEPADA KLIEN:</p>
                                            <p class="text-sm font-black text-slate-900 mt-0.5" x-text="invoice.client_name || '-'"></p>
                                            <p class="text-[10.5px] text-slate-800 font-semibold leading-normal mt-0.5" x-text="invoice.client_address"></p>
                                            <p class="text-[10.5px] text-slate-700 font-bold mt-0.5" x-text="invoice.client_email"></p>
                                        </div>
                                        <div class="text-right space-y-1 text-[10.5px]">
                                            <div class="flex justify-between"><span class="text-slate-700 font-bold">Tanggal Terbit:</span> <span class="font-extrabold text-slate-900" x-text="formatDate(invoice.issue_date)"></span></div>
                                            <div class="flex justify-between"><span class="text-slate-700 font-bold">Jatuh Tempo:</span> <span class="font-extrabold text-slate-900" x-text="formatDate(invoice.due_date)"></span></div>
                                            <div class="flex justify-between"><span class="text-slate-700 font-bold">Mata Uang:</span> <span class="font-black text-slate-900" x-text="invoice.currency"></span></div>
                                        </div>
                                    </div>

                                    <!-- ITEMS TABLE -->
                                    <div class="overflow-hidden">
                                        <table class="w-full text-xs text-left">
                                            <thead class="text-slate-800 font-black text-[9.5px] uppercase border-b-2 border-slate-300">
                                                <tr>
                                                    <th class="py-2.5 pr-2">DESKRIPSI LAYANAN / PRODUK</th>
                                                    <th class="py-2.5 text-center px-2">QTY</th>
                                                    <th class="py-2.5 text-right px-2">HARGA SATUAN</th>
                                                    <th class="py-2.5 text-right pl-2">TOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200 text-[10.5px]">
                                                <template x-for="(item, idx) in invoice.items" :key="idx">
                                                    <tr>
                                                        <td class="py-2.5 pr-2 font-bold text-slate-900" x-text="item.name || '-'"></td>
                                                        <td class="py-2.5 text-center px-2 font-bold text-slate-900" x-text="item.qty || 1"></td>
                                                        <td class="py-2.5 text-right px-2 font-semibold text-slate-800" x-text="'Rp ' + Number(item.price || 0).toLocaleString('id-ID')"></td>
                                                        <td class="py-2.5 text-right pl-2 font-black text-slate-950 text-xs" x-text="'Rp ' + Number((item.qty || 1) * (item.price || 0)).toLocaleString('id-ID')"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- SUMMARY & BANK INFO -->
                                    <div class="grid grid-cols-12 gap-4 items-start pt-2">
                                        <div class="col-span-6 bg-slate-50 p-3 rounded-lg border-2 border-slate-300 text-[10px]">
                                            <p class="font-black text-slate-950 text-[10.5px] mb-1">Informasi Pembayaran Bank:</p>
                                            <p class="text-slate-800 font-semibold">Bank: <span class="font-extrabold text-slate-950" x-text="company.bank_name"></span></p>
                                            <p class="text-slate-800 font-semibold">No. Rek: <span class="font-black text-xs" :style="'color: ' + getThemeColors().primary" x-text="company.bank_account"></span></p>
                                            <p class="text-slate-800 font-semibold">A.N: <span class="font-extrabold text-slate-950" x-text="company.bank_holder"></span></p>
                                        </div>
                                        <div class="col-span-6 space-y-1.5 text-[10.5px] text-right">
                                            <div class="flex justify-between text-slate-700"><span class="font-bold">Subtotal:</span> <span class="font-extrabold text-slate-950" x-text="formatRupiah(calculateSubtotal())"></span></div>
                                            <template x-if="invoice.discount > 0">
                                                <div class="flex justify-between text-rose-700"><span class="font-bold">Diskon:</span> <span class="font-extrabold" x-text="'- ' + formatRupiah(invoice.discount)"></span></div>
                                            </template>
                                            <div class="flex justify-between text-slate-700"><span class="font-bold">PPN (<span x-text="invoice.ppn_percent"></span>%):</span> <span class="font-extrabold text-slate-950" x-text="formatRupiah(calculatePpn())"></span></div>
                                            <template x-if="invoice.shipping > 0">
                                                <div class="flex justify-between text-slate-700"><span class="font-bold">Ongkir/Lainnya:</span> <span class="font-extrabold text-slate-950" x-text="formatRupiah(invoice.shipping)"></span></div>
                                            </template>
                                            <div class="flex justify-between pt-2 border-t-2 border-slate-300 font-black text-sm text-slate-950">
                                                <span>Grand Total:</span>
                                                <span x-text="formatRupiah(calculateGrandTotal())"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NOTES -->
                                    <template x-if="invoice.notes">
                                        <div class="pt-2 text-[10px] text-slate-800">
                                            <p class="font-black text-slate-950 text-[10.5px]">Catatan & Ketentuan:</p>
                                            <p x-text="invoice.notes" class="whitespace-pre-line italic font-semibold text-slate-800"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- KWITANSI DOCUMENT CANVAS PREVIEW -->
                            <template x-if="activeTab === 'kwitansi'">
                                <div class="space-y-5">
                                    <!-- TITLE BAR -->
                                    <div class="flex items-center justify-between border-b-2 border-slate-300 pb-2">
                                        <div>
                                            <h1 class="text-lg font-black text-slate-950 tracking-wider uppercase">KWITANSI PEMBAYARAN</h1>
                                            <p class="text-[9.5px] font-bold text-slate-600">Bukti Transaksi Resmi Terverifikasi</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-black uppercase mr-2" :style="'color: ' + getThemeColors().primary" x-text="kwitansi.status"></span>
                                            <span class="text-xs font-black text-slate-700" x-text="'No: ' + kwitansi.doc_number"></span>
                                        </div>
                                    </div>

                                    <!-- FIELDS -->
                                    <div class="space-y-3 text-[11px] divide-y divide-slate-200">
                                        <div class="grid grid-cols-12 gap-2 items-center pb-2">
                                            <div class="col-span-4 font-black text-slate-700">Telah Diterima Dari:</div>
                                            <div class="col-span-8 font-black text-slate-950 text-sm" x-text="kwitansi.client_name || '-'"></div>
                                        </div>

                                        <div class="grid grid-cols-12 gap-2 items-start py-2">
                                            <div class="col-span-4 font-black text-slate-700 pt-1">Uang Sejumlah:</div>
                                            <div class="col-span-8 bg-slate-50 p-2.5 rounded-lg border-2 border-slate-300 font-extrabold italic text-slate-950 text-[10.5px]" x-text="getTerbilang(kwitansi.kwitansi_amount)"></div>
                                        </div>

                                        <div class="grid grid-cols-12 gap-2 items-start pt-2">
                                            <div class="col-span-4 font-black text-slate-700">Untuk Pembayaran:</div>
                                            <div class="col-span-8 text-slate-900 font-bold leading-relaxed" x-text="kwitansi.payment_for || '-'"></div>
                                        </div>
                                    </div>

                                    <!-- AMOUNT CARD -->
                                    <div class="pt-2">
                                        <div class="p-3.5 rounded-xl inline-block w-52 shadow-sm border-2 transition-all" :style="'background-color: ' + getThemeColors().bgLight + '; border-color: ' + getThemeColors().border">
                                            <p class="text-[9.5px] font-black uppercase" :style="'color: ' + getThemeColors().primary">JUMLAH TOTAL NOMINAL:</p>
                                            <p class="text-lg font-black" :style="'color: ' + getThemeColors().textPrimary" x-text="formatRupiah(kwitansi.kwitansi_amount)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- SIGNATURE FOOTER FOR ALL DOCS -->
                        <div class="pt-4 mt-4 flex justify-end">
                            <div class="w-52 text-center space-y-0.5">
                                <p class="text-[10px] text-slate-700 font-bold" x-text="(kwitansi.issue_city || 'Cianjur') + ', ' + formatDate(activeTab === 'kwitansi' ? kwitansi.issue_date : invoice.issue_date)"></p>
                                <div class="h-16 relative flex items-center justify-center my-1">
                                    <template x-if="company.stamp">
                                        <img :src="company.stamp" class="absolute h-14 opacity-85 z-0">
                                    </template>
                                    <template x-if="company.signature">
                                        <img :src="company.signature" class="relative z-10 h-14 object-contain">
                                    </template>
                                </div>
                                <p class="font-black text-slate-950 text-xs border-b-2 border-slate-950 pb-0.5 inline-block" x-text="activeTab === 'kwitansi' ? (kwitansi.signer_name || 'Helmi Maulana') : (company.company_name || 'PT WAKAMIYA MANDIRI SEJAHTERA')"></p>
                                <p class="text-[9px] text-slate-700 font-extrabold" x-text="activeTab === 'kwitansi' ? (company.company_name || 'PT WAKAMIYA MANDIRI SEJAHTERA') : 'Finance & Accounting Dept.'"></p>
                            </div>
                        </div>

                        <!-- WATERMARK FOOTER -->
                        <div class="text-center text-[8.5px] text-slate-600 font-bold pt-3 border-t border-slate-200">
                            Dokumen resmi diterbitkan secara sah oleh komputer <span x-text="company.company_name || 'PT WAKAMIYA MANDIRI SEJAHTERA'"></span>.
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- EMAIL MODAL -->
    <div x-show="openEmailModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
        <div class="bg-slate-900 border border-slate-800 text-slate-200 rounded-2xl p-6 w-full max-w-md space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white">🚀 Kirim Email Dokumen</h3>
                <button @click="openEmailModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Email Klien / Siswa Target</label>
                <input type="email" x-model="invoice.client_email" placeholder="email@siswa.ac.id" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button @click="openEmailModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Batal</button>
                <button @click="sendClientEmail()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30">
                    Kirim Sekarang
                </button>
            </div>
        </div>
    </div>

</div>

<!-- HIDDEN FORM FOR PDF EXPORT -->
<form id="pdfExportForm" action="{{ route('finance.smart_generator.pdf') }}" method="POST" class="hidden">
    @csrf
    <input type="hidden" name="payload" id="pdfPayloadInput">
</form>

<script>
function smartGenerator() {
    return {
        activeTab: 'invoice',
        sourceMode: 'student_invoice', // 'student_invoice' vs 'manual_invoice'
        openEmailModal: false,
        history: [],

        // Student search state
        studentSearchQuery: '',
        studentSearchResults: [],
        isSearching: false,
        hasSearched: false,
        selectedSourceId: null,
        selectedStudentId: null,
        selectedInvoice: null,

        company: {
            company_name: "PT WAKAMIYA MANDIRI SEJAHTERA",
            company_tagline: "Growing Together With Integrity",
            company_address: "Perum Graha Samolo Indah Blok B1 No 22 Desa Babakan Caringin, Karang Tengah,Cianjur",
            company_phone: "0813-1811-5151",
            company_email: "lpkwakamiya01@gmail.com",
            company_web: "www.wakamiya.com",
            company_npwp: "1000000003150626",
            bank_name: "Bank Syariah Indonesia (BSI)",
            bank_account: "7343551023",
            bank_holder: "PT WAKAMIYA MANDIRI SEJAHTERA",
            layout_kop: 'left',
            company_logo: "{{ asset('img/logo.png.jpeg') }}",
            signature: "",
            stamp: ""
        },

        invoice: {
            theme: 'emerald',
            doc_number: 'INV-WMS-0001',
            currency: 'IDR',
            issue_date: '2026-08-04',
            due_date: '2026-09-02',
            status: 'UNPAID',
            client_name: 'Rifai Sholikhin',
            client_email: 'rifai@example.com',
            client_address: 'Ds. Sukareja Blok.Karanganyar RT.07/RW 03 Kec.Balongan Kab.Indramayu',
            items: [
                { name: 'Sisa Angsuran Biaya Pengurusan Dokumen Ke Jepang', qty: 1, price: 11550000 }
            ],
            discount: 0,
            ppn_percent: 0,
            shipping: 0,
            notes: 'Pembayaran via Transfer BSI 7343551023 a.n PT Wakamiya Mandiri Sejahtera.'
        },

        kwitansi: {
            theme: 'emerald',
            doc_number: 'KWI-WMS-0001',
            issue_date: '2026-08-04',
            status: 'PAID / LUNAS',
            client_name: 'Rifai Sholikhin',
            client_email: 'rifai@example.com',
            kwitansi_amount: 33450000,
            payment_for: 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang',
            issue_city: 'Cianjur',
            signer_name: 'Helmi Maulana'
        },

        init() {
            this.initSignatureCanvas();
            this.fetchHistory();
            this.searchStudentInvoices();
        },

        setSourceMode(mode) {
            this.sourceMode = mode;
            if (mode === 'manual_invoice') {
                this.selectedSourceId = null;
                this.selectedStudentId = null;
                this.selectedInvoice = null;
            }
        },

        getPaneTitle() {
            if (this.activeTab === 'kop') return 'Pengaturan Profil Kop Surat';
            if (this.activeTab === 'invoice') return 'Pengaturan Invoice';
            if (this.activeTab === 'kwitansi') return 'Pengaturan Kwitansi';
            return 'Riwayat Dokumen Tersimpan';
        },

        searchStudentInvoices() {
            this.isSearching = true;
            this.hasSearched = true;
            fetch("{{ route('finance.smart_generator.search_student_invoices') }}?q=" + encodeURIComponent(this.studentSearchQuery))
                .then(res => res.json())
                .then(data => {
                    this.isSearching = false;
                    if (data.success) {
                        this.studentSearchResults = data.invoices || [];
                    }
                })
                .catch(() => {
                    this.isSearching = false;
                });
        },

        selectStudentInvoice(inv) {
            this.sourceMode = 'student_invoice';
            this.selectedSourceId = inv.id;
            this.selectedStudentId = inv.student_id;
            this.selectedInvoice = inv;

            this.invoice.doc_number = inv.doc_number;
            this.invoice.client_name = inv.student_name;
            this.invoice.client_email = inv.student_email || '';
            this.invoice.client_address = inv.student_address || 'Alamat Siswa';
            this.invoice.issue_date = inv.issue_date || '2026-08-04';
            this.invoice.due_date = inv.due_date || '2026-08-18';
            this.invoice.status = inv.status || 'UNPAID';
            this.invoice.items = inv.items || [{ name: inv.description || 'Tagihan Siswa', qty: 1, price: inv.amount }];
            this.invoice.discount = inv.discount || 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.invoice.notes = 'Tagihan Resmi Siswa (Finance Engine: ' + inv.doc_number + ')';

            // Also update Kwitansi
            this.kwitansi.doc_number = 'KWI-' + inv.doc_number;
            this.kwitansi.client_name = inv.student_name;
            this.kwitansi.client_email = inv.student_email || '';
            this.kwitansi.kwitansi_amount = inv.grand_total || inv.amount;
            this.kwitansi.payment_for = inv.description || 'Pelunasan Tagihan Siswa';
        },

        addItem() {
            if (this.sourceMode === 'student_invoice') return;
            this.invoice.items.push({ name: 'Rincian Biaya Pengurusan Dokumen', qty: 1, price: 1000000 });
        },

        removeItem(index) {
            if (this.sourceMode === 'student_invoice') return;
            this.invoice.items.splice(index, 1);
        },

        calculateSubtotal() {
            return this.invoice.items.reduce((acc, item) => acc + (Number(item.qty || 1) * Number(item.price || 0)), 0);
        },

        calculatePpn() {
            const sub = this.calculateSubtotal();
            const afterDisc = Math.max(0, sub - Number(this.invoice.discount || 0));
            return (afterDisc * Number(this.invoice.ppn_percent || 0)) / 100;
        },

        calculateGrandTotal() {
            const sub = this.calculateSubtotal();
            const afterDisc = Math.max(0, sub - Number(this.invoice.discount || 0));
            return afterDisc + this.calculatePpn() + Number(this.invoice.shipping || 0);
        },

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },

        formatDate(dateStr) {
            if (!dateStr) return '2026-08-04';
            return dateStr;
        },

        getTerbilang(num) {
            return this.terbilangWord(num);
        },

        terbilangWord(n) {
            n = Math.abs(Number(n) || 0);
            if (n === 0) return '# Nol Rupiah #';
            const units = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
            function convert(num) {
                if (num < 12) return units[num];
                if (num < 20) return convert(num - 10) + ' Belas';
                if (num < 100) return convert(Math.floor(num / 10)) + ' Puluh ' + convert(num % 10);
                if (num < 200) return 'Seratus ' + convert(num - 100);
                if (num < 1000) return convert(Math.floor(num / 100)) + ' Ratus ' + convert(num % 100);
                if (num < 2000) return 'Seribu ' + convert(num - 1000);
                if (num < 1000000) return convert(Math.floor(num / 1000)) + ' Ribu ' + convert(num % 1000);
                if (num < 1000000000) return convert(Math.floor(num / 1000000)) + ' Juta ' + convert(num % 1000000);
                return convert(Math.floor(num / 1000000000)) + ' Miliar ' + convert(num % 1000000000);
            }
            return '# ' + convert(n).trim() + ' Rupiah #';
        },

        loadSampleData() {
            this.sourceMode = 'manual_invoice';
            this.selectedSourceId = null;
            this.invoice.doc_number = 'INV-WMS-0001';
            this.invoice.client_name = 'Rifai Sholikhin';
            this.invoice.client_email = 'rifai@example.com';
            this.invoice.client_address = 'Ds. Sukareja Blok.Karanganyar RT.07/RW 03 Kec.Balongan Kab.Indramayu';
            this.invoice.items = [
                { name: 'Sisa Angsuran Biaya Pengurusan Dokumen Ke Jepang', qty: 1, price: 11550000 }
            ];
            this.invoice.discount = 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.kwitansi.doc_number = 'KWI-WMS-0001';
            this.kwitansi.client_name = 'Rifai Sholikhin';
            this.kwitansi.kwitansi_amount = 33450000;
            this.kwitansi.payment_for = 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang';
            this.kwitansi.signer_name = 'Helmi Maulana';
        },

        resetForm() {
            this.selectedSourceId = null;
            this.selectedStudentId = null;
            this.selectedInvoice = null;
            this.invoice.doc_number = 'INV-WMS-' + Math.floor(1000 + Math.random() * 9000);
            this.invoice.client_name = '';
            this.invoice.client_email = '';
            this.invoice.client_address = '';
            this.invoice.items = [{ name: 'Deskripsi Layanan / Produk', qty: 1, price: 0 }];
            this.invoice.discount = 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.invoice.notes = '';
            this.kwitansi.doc_number = 'KWI-WMS-' + Math.floor(1000 + Math.random() * 9000);
            this.kwitansi.client_name = '';
            this.kwitansi.client_email = '';
            this.kwitansi.kwitansi_amount = 0;
            this.kwitansi.payment_for = '';
        },

        handleFileUpload(event, key) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.company[key] = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        initSignatureCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let drawing = false;

            canvas.addEventListener('mousedown', (e) => {
                drawing = true;
                ctx.beginPath();
                ctx.moveTo(e.offsetX, e.offsetY);
            });
            canvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                ctx.lineTo(e.offsetX, e.offsetY);
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.stroke();
            });
            canvas.addEventListener('mouseup', () => {
                drawing = false;
                this.company.signature = canvas.toDataURL();
            });
        },

        clearSignatureCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            this.company.signature = '';
        },

        exportPdf() {
            const payload = this.getPayload();
            const form = document.getElementById('pdfExportForm');
            
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="source_type" value="${payload.source_type}">
                <input type="hidden" name="source_id" value="${payload.source_id || ''}">
                <input type="hidden" name="student_id" value="${payload.student_id || ''}">
                <input type="hidden" name="doc_type" value="${payload.doc_type}">
                <input type="hidden" name="doc_number" value="${payload.doc_number}">
                <input type="hidden" name="issue_date" value="${payload.issue_date}">
                <input type="hidden" name="due_date" value="${payload.due_date}">
                <input type="hidden" name="status" value="${payload.status}">
                <input type="hidden" name="client_name" value="${payload.client_name}">
                <input type="hidden" name="client_email" value="${payload.client_email}">
                <input type="hidden" name="client_address" value="${payload.client_address}">
                <input type="hidden" name="company_name" value="${payload.company_name}">
                <input type="hidden" name="company_tagline" value="${payload.company_tagline}">
                <input type="hidden" name="company_address" value="${payload.company_address}">
                <input type="hidden" name="company_phone" value="${payload.company_phone}">
                <input type="hidden" name="company_email" value="${payload.company_email}">
                <input type="hidden" name="company_web" value="${payload.company_web}">
                <input type="hidden" name="company_npwp" value="${payload.company_npwp}">
                <input type="hidden" name="bank_name" value="${payload.bank_name}">
                <input type="hidden" name="bank_account" value="${payload.bank_account}">
                <input type="hidden" name="bank_holder" value="${payload.bank_holder}">
                <input type="hidden" name="items" value='${JSON.stringify(payload.items)}'>
                <input type="hidden" name="discount" value="${payload.discount}">
                <input type="hidden" name="ppn_percent" value="${payload.ppn_percent}">
                <input type="hidden" name="shipping" value="${payload.shipping}">
                <input type="hidden" name="notes" value="${payload.notes}">
                <input type="hidden" name="kwitansi_amount" value="${payload.kwitansi_amount}">
                <input type="hidden" name="payment_for" value="${payload.payment_for}">
                <input type="hidden" name="issue_city" value="${payload.issue_city}">
                <input type="hidden" name="signer_name" value="${payload.signer_name}">
                <input type="hidden" name="company_logo" value="${payload.company_logo || ''}">
                <input type="hidden" name="signature" value="${payload.signature || ''}">
                <input type="hidden" name="stamp" value="${payload.stamp || ''}">
                <input type="hidden" name="theme" value="${payload.theme || 'emerald'}">
            `;
            form.submit();
        },

        printDocument() {
            window.print();
        },

        getThemeColors() {
            const theme = (this.activeTab === 'kwitansi') ? (this.kwitansi.theme || 'emerald') : (this.invoice.theme || 'emerald');
            if (theme === 'indigo') {
                return {
                    primary: '#4338ca',
                    secondary: '#4f46e5',
                    bgLight: '#eef2ff',
                    border: '#4f46e5',
                    textPrimary: '#1e1b4b',
                    textDark: '#312e81',
                    badgeBg: 'bg-indigo-100 text-indigo-900 border-indigo-500'
                };
            }
            if (theme === 'crimson') {
                return {
                    primary: '#be123c',
                    secondary: '#e11d48',
                    bgLight: '#fff1f2',
                    border: '#e11d48',
                    textPrimary: '#881337',
                    textDark: '#9f1239',
                    badgeBg: 'bg-rose-100 text-rose-900 border-rose-500'
                };
            }
            // Default Emerald
            return {
                primary: '#047857',
                secondary: '#059669',
                bgLight: '#ecfdf5',
                border: '#059669',
                textPrimary: '#064e3b',
                textDark: '#065f46',
                badgeBg: 'bg-emerald-100 text-emerald-900 border-emerald-500'
            };
        },

        saveToHistory() {
            const payload = this.getPayload();
            fetch("{{ route('finance.smart_generator.save') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.history = data.history;
                    alert('Dokumen ' + payload.doc_number + ' berhasil disimpan ke riwayat!');
                }
            });
        },

        fetchHistory() {
            fetch("{{ route('finance.smart_generator.history_api') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.history = data.history;
                    }
                });
        },

        deleteHistoryItem(id) {
            if (!confirm('Hapus dokumen ini dari riwayat?')) return;
            fetch("{{ url('/finance/smart-generator/history') }}/" + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.history = data.history;
                }
            });
        },

        loadHistoryItem(item) {
            if (item.source_type) {
                this.sourceMode = item.source_type;
                this.selectedSourceId = item.source_id || null;
                this.selectedStudentId = item.student_id || null;
            }

            if (item.doc_type === 'kwitansi') {
                this.activeTab = 'kwitansi';
                this.kwitansi.doc_number = item.doc_number;
                this.kwitansi.client_name = item.client_name;
                this.kwitansi.kwitansi_amount = item.kwitansi_amount;
                this.kwitansi.payment_for = item.payment_for;
                this.kwitansi.signer_name = item.signer_name || 'Helmi Maulana';
                if (item.theme) this.kwitansi.theme = item.theme;
            } else {
                this.activeTab = 'invoice';
                this.invoice.doc_number = item.doc_number;
                this.invoice.client_name = item.client_name;
                this.invoice.client_email = item.client_email;
                this.invoice.client_address = item.client_address;
                this.invoice.items = item.items || [];
                this.invoice.discount = item.discount || 0;
                this.invoice.ppn_percent = item.ppn_percent || 0;
                this.invoice.shipping = item.shipping || 0;
                if (item.theme) this.invoice.theme = item.theme;
            }
        },

        sendClientEmail() {
            const payload = this.getPayload();
            fetch("{{ route('finance.smart_generator.send_email') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    client_email: payload.client_email,
                    doc_number: payload.doc_number,
                    source_type: payload.source_type,
                    source_id: payload.source_id,
                    student_id: payload.student_id
                })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.message || 'Gagal mengirim email.'); });
                }
                return res.json();
            })
            .then(data => {
                alert(data.message);
                this.openEmailModal = false;
            })
            .catch(err => {
                alert(err.message);
            });
        },

        getPayload() {
            const isInvoice = (this.activeTab === 'invoice' || this.activeTab === 'kop');
            return {
                source_type: this.sourceMode,
                source_id: this.selectedSourceId,
                student_id: this.selectedStudentId,
                theme: isInvoice ? (this.invoice.theme || 'emerald') : (this.kwitansi.theme || 'emerald'),

                doc_type: isInvoice ? 'invoice' : 'kwitansi',
                doc_number: isInvoice ? this.invoice.doc_number : this.kwitansi.doc_number,
                issue_date: isInvoice ? this.invoice.issue_date : this.kwitansi.issue_date,
                due_date: this.invoice.due_date,
                status: isInvoice ? this.invoice.status : this.kwitansi.status,
                client_name: isInvoice ? this.invoice.client_name : this.kwitansi.client_name,
                client_email: isInvoice ? this.invoice.client_email : this.kwitansi.client_email,
                client_address: this.invoice.client_address,
                company_name: this.company.company_name,
                company_tagline: this.company.company_tagline,
                company_address: this.company.company_address,
                company_phone: this.company.company_phone,
                company_email: this.company.company_email,
                company_web: this.company.company_web,
                company_npwp: this.company.company_npwp,
                bank_name: this.company.bank_name,
                bank_account: this.company.bank_account,
                bank_holder: this.company.bank_holder,
                items: this.invoice.items,
                subtotal: this.calculateSubtotal(),
                discount: this.invoice.discount,
                ppn_percent: this.invoice.ppn_percent,
                shipping: this.invoice.shipping,
                grand_total: this.calculateGrandTotal(),
                notes: this.invoice.notes,
                kwitansi_amount: this.kwitansi.kwitansi_amount,
                payment_for: this.kwitansi.payment_for,
                issue_city: this.kwitansi.issue_city,
                signer_name: this.kwitansi.signer_name,
                company_logo: this.company.company_logo,
                signature: this.company.signature,
                stamp: this.company.stamp
            };
        }
    }
}
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #a4CanvasSheet, #a4CanvasSheet * {
        visibility: visible;
    }
    #a4CanvasSheet {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        margin: 0 !important;
        padding: 10mm !important;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
@endsection
