@extends('layouts.app')
@section('header', 'Buat Tagihan')
@section('content')
<div class="space-y-6" x-data="invoiceForm()">
    <x-page-header title="Tagihan Baru" description="Buat tagihan baru." :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Tagihan' => route('invoices.index'), 'Buat' => '#']" />
    
    <form action="{{ route('invoices.store') }}" method="POST">
        @csrf
        <input type="hidden" name="Invoice_Type" value="STUDENT">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-800">Identitas Tagihan</h3>
                <p class="text-xs text-slate-500 mt-1">Pilih siswa dan kategori untuk tagihan.</p>
            </div>
            
            <div class="p-6">
                <x-smart-warning x-show="!student.Student_ID && isSelected" 
                    message="Data siswa tidak lengkap. Silakan lengkapi data master terlebih dahulu." 
                    type="warning" 
                    class="mb-6" />

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Filter Batch</label>
                        <select x-model="selectedBatch" @change="studentId = ''; student = {}; isSelected = false" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-3 py-2 shadow-sm">
                            <option value="">-- Semua Batch --</option>
                            @foreach($batches as $b)
                                <option value="{{ $b['Batch_ID'] ?? '' }}">{{ $b['Batch_Name'] ?? 'Tidak diketahui' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Filter Kelas</label>
                        <select x-model="selectedClass" @change="studentId = ''; student = {}; isSelected = false" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-3 py-2 shadow-sm">
                            <option value="">-- Semua Kelas --</option>
                            @foreach($classes as $c)
                                <option value="{{ $c['Class_ID'] ?? '' }}">{{ $c['Class_Name'] ?? 'Tidak diketahui' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Siswa <span class="text-rose-500">*</span></label>
                        <div class="relative" @click.away="open = false">
                            <input type="hidden" name="Student_ID" x-model="studentId" required>
                            
                            <button 
                                type="button" 
                                @click="open = !open"
                                class="w-full flex justify-between items-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[13px] shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                            >
                                <span x-text="selectedStudentName" :class="{'text-slate-400': !studentId, 'text-slate-800': studentId}" class="truncate block text-left w-full h-[20px]"></span>
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <div x-show="open" 
                                 x-transition.opacity
                                 class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden"
                                 style="display: none;"
                            >
                                <div class="p-2 border-b border-slate-100">
                                    <div class="relative">
                                        <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        <input type="text" x-model="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-emerald-500 focus:bg-white focus:ring-0 transition-colors" placeholder="Cari nama atau NIS..." @keydown.escape="open = false">
                                    </div>
                                </div>
                                <ul class="max-h-60 overflow-y-auto p-1" style="scrollbar-width: thin;">
                                    <template x-for="s in filteredStudents" :key="s.Student_ID">
                                        <li @click="selectStudent(s.Student_ID)"
                                            class="px-3 py-2 cursor-pointer rounded-lg text-sm transition-colors hover:bg-emerald-50 hover:text-emerald-700"
                                            :class="{'bg-emerald-50 text-emerald-700 font-bold': studentId === s.Student_ID, 'text-slate-700': studentId !== s.Student_ID}"
                                        >
                                            <span x-text="s.Full_Name + ' (' + (s.Student_Number || '-') + ')'"></span>
                                        </li>
                                    </template>
                                    <li x-show="filteredStudents.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                                        Tidak ditemukan
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Kategori</label>
                        <select name="Category" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- SMART FORM SECTION (AUTO-FILL) -->
                <div class="bg-slate-50 rounded-xl p-5 border border-slate-200 mb-8" x-show="studentId">
                    <h3 class="text-sm font-bold text-slate-700 mb-4 border-b border-slate-200 pb-2">Informasi Siswa (Auto-fill)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <x-input label="Nama Lengkap" x-model="student.Full_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Program" x-model="student.Program_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Batch" x-model="student.Batch_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Kelas" x-model="student.Class_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Email" x-model="student.Email" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Telepon" x-model="student.Phone_Number" readonly class="bg-slate-100 cursor-not-allowed" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-4">
                    <x-input type="number" name="Amount" label="Nominal (IDR)" required />
                    <x-input type="date" name="Due_Date" label="Jatuh Tempo" value="{{ date('Y-m-d', strtotime('+7 days')) }}" />
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Status</label>
                        <select name="Status" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm">
                            @foreach(config('finance.invoice_status') as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-8 border-t border-slate-100 pt-6">
                    <a href="{{ route('invoices.index') }}" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-800 transition-colors">Batal</a>
                    <button type="submit" :disabled="!student.Student_ID && isSelected" class="px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">Buat Tagihan</button>
                </div>
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
                if (!this.studentId) return '-- Pilih Siswa --';
                let s = this.students.find(s => s.Student_ID === this.studentId);
                return s ? s.Full_Name + ' (' + (s.Student_Number || '-') + ')' : '-- Pilih Siswa --';
            },

            selectStudent(id) {
                this.studentId = id;
                this.open = false;
                this.fetchStudentData();
            },

            fetchStudentData() {
                if (!this.studentId) {
                    this.student = {};
                    this.isSelected = false;
                    return;
                }
                
                this.isSelected = true;
                
                // Fetch from API
                fetch(`/api/students/${this.studentId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.student = data;
                    })
                    .catch(error => {
                        console.error('Error fetching student data:', error);
                        this.student = {};
                    });
            }
        }
    }
</script>
@endsection



