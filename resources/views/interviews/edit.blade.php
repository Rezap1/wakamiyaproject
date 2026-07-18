@extends('layouts.app')

@section('header', 'Edit Jadwal Interview')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('interviews.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Edit Jadwal Interview</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Ubah informasi penjadwalan atau hasil untuk interview <span class="font-bold text-gray-700">{{ $interview['Interview_Number'] ?? $interview['Interview_ID'] }}</span>.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('interviews.show', $interview['Interview_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Lihat Detail
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('interviews.update', $interview['Interview_ID']) }}" method="POST" class="space-y-10">
                @csrf
                @method('PUT')
                
                <!-- 1. Data Relasi (Wajib) -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Data Relasi (Kandidat & Pekerjaan)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Job_Order_ID" class="block text-sm font-bold text-gray-700 mb-2">Job Order (Perusahaan) <span class="text-red-500">*</span></label>
                            <select name="Job_Order_ID" id="Job_Order_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Job Order --</option>
                                @foreach($jobOrders as $jo)
                                    <option value="{{ $jo['Job_Order_ID'] }}" {{ old('Job_Order_ID', $interview['Job_Order_ID'] ?? '') == $jo['Job_Order_ID'] ? 'selected' : '' }}>
                                        {{ $jo['Job_Order_Number'] ?? $jo['Job_Order_ID'] }} - {{ $jo['Company_Name'] ?? 'Tanpa Perusahaan' }} ({{ $jo['Job_Title'] ?? 'Tanpa Judul' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('Job_Order_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Student_ID" class="block text-sm font-bold text-gray-700 mb-2">Kandidat (Siswa) <span class="text-red-500">*</span></label>
                            <select name="Student_ID" id="Student_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Kandidat --</option>
                                @foreach($students as $student)
                                    <option value="{{ $student['Student_ID'] }}" {{ old('Student_ID', $interview['Student_ID'] ?? '') == $student['Student_ID'] ? 'selected' : '' }}>
                                        {{ $student['Registration_Number'] ?? $student['Student_ID'] }} - {{ $student['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Student_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. Informasi Penjadwalan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Informasi Penjadwalan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="Interview_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor Referensi (Opsional)</label>
                            <input type="text" name="Interview_Number" id="Interview_Number" value="{{ old('Interview_Number', $interview['Interview_Number'] ?? '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Cth: INV-2023-001">
                            @error('Interview_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Interview_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Pelaksanaan <span class="text-red-500">*</span></label>
                            <input type="date" name="Interview_Date" id="Interview_Date" value="{{ old('Interview_Date', !empty($interview['Interview_Date']) ? \Carbon\Carbon::parse($interview['Interview_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('Interview_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Interview_Time" class="block text-sm font-bold text-gray-700 mb-2">Waktu Pelaksanaan <span class="text-red-500">*</span></label>
                            <input type="time" name="Interview_Time" id="Interview_Time" value="{{ old('Interview_Time', $interview['Interview_Time'] ?? '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('Interview_Time') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Interview_Method" class="block text-sm font-bold text-gray-700 mb-2">Metode Interview <span class="text-red-500">*</span></label>
                            <select name="Interview_Method" id="Interview_Method" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Metode --</option>
                                <option value="Online (Zoom/Meet)" {{ old('Interview_Method', $interview['Interview_Method'] ?? '') == 'Online (Zoom/Meet)' ? 'selected' : '' }}>Online (Zoom/Meet)</option>
                                <option value="Offline (Tatap Muka)" {{ old('Interview_Method', $interview['Interview_Method'] ?? '') == 'Offline (Tatap Muka)' ? 'selected' : '' }}>Offline (Tatap Muka)</option>
                            </select>
                            @error('Interview_Method') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Interviewer" class="block text-sm font-bold text-gray-700 mb-2">Nama Pewawancara</label>
                            <input type="text" name="Interviewer" id="Interviewer" value="{{ old('Interviewer', $interview['Interviewer'] ?? '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Perwakilan perusahaan">
                        </div>

                        <div>
                            <label for="Interview_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Jadwal <span class="text-red-500">*</span></label>
                            <select name="Interview_Status" id="Interview_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="SCHEDULED" {{ old('Interview_Status', $interview['Interview_Status'] ?? '') == 'SCHEDULED' ? 'selected' : '' }}>Scheduled (Dijadwalkan)</option>
                                <option value="COMPLETED" {{ old('Interview_Status', $interview['Interview_Status'] ?? '') == 'COMPLETED' ? 'selected' : '' }}>Completed (Selesai)</option>
                                <option value="CANCELLED" {{ old('Interview_Status', $interview['Interview_Status'] ?? '') == 'CANCELLED' ? 'selected' : '' }}>Cancelled (Dibatalkan)</option>
                                <option value="RESCHEDULED" {{ old('Interview_Status', $interview['Interview_Status'] ?? '') == 'RESCHEDULED' ? 'selected' : '' }}>Rescheduled (Dijadwalkan Ulang)</option>
                            </select>
                            @error('Interview_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Hasil & Catatan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Hasil & Catatan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Interview_Result" class="block text-sm font-bold text-gray-700 mb-2">Hasil Interview <span class="text-red-500">*</span></label>
                            <select name="Interview_Result" id="Interview_Result" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="PENDING" {{ old('Interview_Result', $interview['Interview_Result'] ?? '') == 'PENDING' ? 'selected' : '' }}>PENDING (Menunggu Keputusan)</option>
                                <option value="PASSED" {{ old('Interview_Result', $interview['Interview_Result'] ?? '') == 'PASSED' ? 'selected' : '' }}>PASSED (Lulus)</option>
                                <option value="FAILED" {{ old('Interview_Result', $interview['Interview_Result'] ?? '') == 'FAILED' ? 'selected' : '' }}>FAILED (Gagal)</option>
                                <option value="RESERVE" {{ old('Interview_Result', $interview['Interview_Result'] ?? '') == 'RESERVE' ? 'selected' : '' }}>RESERVE (Cadangan)</option>
                            </select>
                            @error('Interview_Result') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Result_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Pengumuman Hasil</label>
                            <input type="date" name="Result_Date" id="Result_Date" value="{{ old('Result_Date', !empty($interview['Result_Date']) ? \Carbon\Carbon::parse($interview['Result_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div class="md:col-span-2">
                            <label for="Remarks" class="block text-sm font-bold text-gray-700 mb-2">Remarks (Umpan Balik / Evaluasi dari Perusahaan)</label>
                            <textarea name="Remarks" id="Remarks" rows="3" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Remarks', $interview['Remarks'] ?? '') }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $interview['Notes'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- 4. Konfigurasi Sistem -->
                <div class="hidden">
                    <label for="Is_Active" class="block text-sm font-bold text-gray-700 mb-2">Status Aktif</label>
                    <select name="Is_Active" id="Is_Active" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        <option value="TRUE" {{ old('Is_Active', $interview['Is_Active'] ?? 'TRUE') == 'TRUE' ? 'selected' : '' }}>Aktif</option>
                        <option value="FALSE" {{ old('Is_Active', $interview['Is_Active'] ?? 'TRUE') == 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('interviews.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Perubahan</span>
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
