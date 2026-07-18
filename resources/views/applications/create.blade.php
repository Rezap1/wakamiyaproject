@extends('layouts.app')

@section('header', 'Tambah Data Aplikasi')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 flex items-center gap-4">
            <a href="{{ route('applications.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900">Buat Aplikasi Baru</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">Pilih kandidat dan lowongan untuk mendaftarkan aplikasi kerja.</p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('applications.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- 1. Data Relasi Utama -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Data Relasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Student_ID" class="block text-sm font-bold text-gray-700 mb-2">Kandidat (Siswa) <span class="text-red-500">*</span></label>
                            <select name="Student_ID" id="Student_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Kandidat --</option>
                                @foreach($students as $student)
                                    <option value="{{ $student['Student_ID'] }}" {{ old('Student_ID') == $student['Student_ID'] ? 'selected' : '' }}>
                                        {{ $student['Registration_Number'] ?? $student['Student_ID'] }} - {{ $student['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Student_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Job_Order_ID" class="block text-sm font-bold text-gray-700 mb-2">Job Order (Lowongan) <span class="text-red-500">*</span></label>
                            <select name="Job_Order_ID" id="Job_Order_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Job Order --</option>
                                @foreach($jobOrders as $jo)
                                    <option value="{{ $jo['Job_Order_ID'] }}" {{ old('Job_Order_ID') == $jo['Job_Order_ID'] ? 'selected' : '' }}>
                                        {{ $jo['Job_Order_Number'] ?? $jo['Job_Order_ID'] }} - {{ $jo['Company_Name'] ?? 'Tanpa Perusahaan' }} ({{ $jo['Job_Title'] ?? 'Tanpa Judul' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('Job_Order_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="Matching_ID" class="block text-sm font-bold text-gray-700 mb-2">Terkait Matching (Opsional)</label>
                            <select name="Matching_ID" id="Matching_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih Matching (Jika Ada) --</option>
                                @foreach($matchings as $matching)
                                    <option value="{{ $matching['Matching_ID'] }}" {{ old('Matching_ID') == $matching['Matching_ID'] ? 'selected' : '' }}>
                                        {{ $matching['Matching_Number'] ?? $matching['Matching_ID'] }} - Status: {{ $matching['Matching_Status'] ?? 'Unknown' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Pilih data matching jika proses aplikasi ini merupakan tindak lanjut dari kesepakatan.</p>
                            @error('Matching_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. Status & Administrasi -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Status & Administrasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="lg:col-span-2">
                            <label for="Application_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor Referensi (Opsional)</label>
                            <input type="text" name="Application_Number" id="Application_Number" value="{{ old('Application_Number') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Cth: APP-2023-001">
                            @error('Application_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Application_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Aplikasi <span class="text-red-500">*</span></label>
                            <input type="date" name="Application_Date" id="Application_Date" value="{{ old('Application_Date', date('Y-m-d')) }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('Application_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Application_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Aplikasi <span class="text-red-500">*</span></label>
                            <select name="Application_Status" id="Application_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="SUBMITTED" {{ old('Application_Status') == 'SUBMITTED' ? 'selected' : '' }}>Submitted (Diajukan)</option>
                                <option value="IN_PROGRESS" {{ old('Application_Status') == 'IN_PROGRESS' ? 'selected' : '' }}>In Progress (Diproses)</option>
                                <option value="APPROVED" {{ old('Application_Status') == 'APPROVED' ? 'selected' : '' }}>Approved (Diterima)</option>
                                <option value="REJECTED" {{ old('Application_Status') == 'REJECTED' ? 'selected' : '' }}>Rejected (Ditolak)</option>
                                <option value="CANCELED" {{ old('Application_Status') == 'CANCELED' ? 'selected' : '' }}>Canceled (Dibatalkan)</option>
                            </select>
                            @error('Application_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label for="Application_Fee" class="block text-sm font-bold text-gray-700 mb-2">Biaya Aplikasi / Fee (Opsional)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="number" name="Application_Fee" id="Application_Fee" value="{{ old('Application_Fee') }}" min="0" step="1000" class="block w-full pl-10 rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="0">
                            </div>
                            @error('Application_Fee') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label for="Payment_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Pembayaran</label>
                            <select name="Payment_Status" id="Payment_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih Status --</option>
                                <option value="PENDING" {{ old('Payment_Status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                                <option value="PAID" {{ old('Payment_Status') == 'PAID' ? 'selected' : '' }}>Paid (Lunas)</option>
                                <option value="PARTIAL" {{ old('Payment_Status') == 'PARTIAL' ? 'selected' : '' }}>Partial (Sebagian)</option>
                                <option value="FAILED" {{ old('Payment_Status') == 'FAILED' ? 'selected' : '' }}>Failed</option>
                                <option value="REFUNDED" {{ old('Payment_Status') == 'REFUNDED' ? 'selected' : '' }}>Refunded</option>
                            </select>
                            @error('Payment_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Catatan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Catatan Tambahan</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="Remarks" class="block text-sm font-bold text-gray-700 mb-2">Remarks</label>
                            <textarea name="Remarks" id="Remarks" rows="3" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Keterangan aplikasi, syarat visa, dll.">{{ old('Remarks') }}</textarea>
                        </div>

                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('applications.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Aplikasi</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const loading = document.getElementById('submitLoading');
        
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
        btn.classList.remove('hover:-translate-y-0.5');
        text.innerText = 'Menyimpan...';
        loading.classList.remove('hidden');
    });
</script>
@endsection
