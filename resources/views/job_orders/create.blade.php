@extends('layouts.app')

@section('header', 'Tambah Job Order Baru')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100">
            <div class="flex items-center gap-4">
                <a href="{{ route('job-orders.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Buat Job Order</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Lengkapi formulir di bawah ini untuk menambahkan permintaan pekerjaan baru.</p>
                </div>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('job-orders.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- 1. Data Utama -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Data Utama</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="Job_Order_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor JO <span class="text-red-500">*</span></label>
                            <input type="text" name="Job_Order_Number" id="Job_Order_Number" value="{{ old('Job_Order_Number') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('Job_Order_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Company_ID" class="block text-sm font-bold text-gray-700 mb-2">Perusahaan <span class="text-red-500">*</span></label>
                            <select name="Company_ID" id="Company_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company['Company_ID'] }}" {{ old('Company_ID') == $company['Company_ID'] ? 'selected' : '' }}>
                                        {{ $company['Company_Code'] }} - {{ $company['Company_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Company_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Job_Title" class="block text-sm font-bold text-gray-700 mb-2">Judul Pekerjaan <span class="text-red-500">*</span></label>
                            <input type="text" name="Job_Title" id="Job_Title" value="{{ old('Job_Title') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('Job_Title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Job_Category" class="block text-sm font-bold text-gray-700 mb-2">Kategori Pekerjaan</label>
                            <input type="text" name="Job_Category" id="Job_Category" value="{{ old('Job_Category') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Job_Order_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Job Order</label>
                            <select name="Job_Order_Status" id="Job_Order_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="OPEN" {{ old('Job_Order_Status') == 'OPEN' ? 'selected' : '' }}>Open</option>
                                <option value="CLOSED" {{ old('Job_Order_Status') == 'CLOSED' ? 'selected' : '' }}>Closed</option>
                                <option value="DRAFT" {{ old('Job_Order_Status') == 'DRAFT' ? 'selected' : '' }}>Draft</option>
                                <option value="CANCELLED" {{ old('Job_Order_Status') == 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label for="Is_Active" class="block text-sm font-bold text-gray-700 mb-2">Status Aktif</label>
                            <select name="Is_Active" id="Is_Active" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="TRUE" {{ old('Is_Active') == 'TRUE' ? 'selected' : '' }}>Aktif</option>
                                <option value="FALSE" {{ old('Is_Active') == 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Detail Pekerjaan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Detail Pekerjaan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Work_Location" class="block text-sm font-bold text-gray-700 mb-2">Lokasi Kerja</label>
                            <input type="text" name="Work_Location" id="Work_Location" value="{{ old('Work_Location') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Prefecture" class="block text-sm font-bold text-gray-700 mb-2">Prefektur (Jepang)</label>
                            <input type="text" name="Prefecture" id="Prefecture" value="{{ old('Prefecture') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Employment_Type" class="block text-sm font-bold text-gray-700 mb-2">Tipe Pekerjaan</label>
                            <input type="text" name="Employment_Type" id="Employment_Type" value="{{ old('Employment_Type') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Visa_Type" class="block text-sm font-bold text-gray-700 mb-2">Tipe Visa</label>
                            <input type="text" name="Visa_Type" id="Visa_Type" value="{{ old('Visa_Type') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="Job_Description" class="block text-sm font-bold text-gray-700 mb-2">Deskripsi Pekerjaan</label>
                            <textarea name="Job_Description" id="Job_Description" rows="4" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Job_Description') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. Kualifikasi -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Kualifikasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="Gender_Requirement" class="block text-sm font-bold text-gray-700 mb-2">Persyaratan Gender</label>
                            <select name="Gender_Requirement" id="Gender_Requirement" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">Bebas</option>
                                <option value="Male" {{ old('Gender_Requirement') == 'Male' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="Female" {{ old('Gender_Requirement') == 'Female' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label for="Minimum_Age" class="block text-sm font-bold text-gray-700 mb-2">Usia Minimal</label>
                            <input type="number" name="Minimum_Age" id="Minimum_Age" value="{{ old('Minimum_Age') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" min="18">
                        </div>

                        <div>
                            <label for="Maximum_Age" class="block text-sm font-bold text-gray-700 mb-2">Usia Maksimal</label>
                            <input type="number" name="Maximum_Age" id="Maximum_Age" value="{{ old('Maximum_Age') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Japanese_Level" class="block text-sm font-bold text-gray-700 mb-2">Level Bahasa Jepang</label>
                            <input type="text" name="Japanese_Level" id="Japanese_Level" value="{{ old('Japanese_Level') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Contoh: N4/N3">
                        </div>
                        
                        <div class="lg:col-span-2">
                            <label for="Education_Requirement" class="block text-sm font-bold text-gray-700 mb-2">Pendidikan Minimum</label>
                            <input type="text" name="Education_Requirement" id="Education_Requirement" value="{{ old('Education_Requirement') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div class="lg:col-span-2">
                            <label for="Required_Skill" class="block text-sm font-bold text-gray-700 mb-2">Skill Khusus (Opsional)</label>
                            <input type="text" name="Required_Skill" id="Required_Skill" value="{{ old('Required_Skill') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                    </div>
                </div>

                <!-- 4. Gaji & Fasilitas -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">4. Gaji & Fasilitas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="Basic_Salary" class="block text-sm font-bold text-gray-700 mb-2">Gaji Pokok (Numerik)</label>
                            <input type="number" name="Basic_Salary" id="Basic_Salary" value="{{ old('Basic_Salary') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Overtime_Pay" class="block text-sm font-bold text-gray-700 mb-2">Uang Lembur</label>
                            <input type="text" name="Overtime_Pay" id="Overtime_Pay" value="{{ old('Overtime_Pay') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Working_Hours" class="block text-sm font-bold text-gray-700 mb-2">Jam Kerja</label>
                            <input type="text" name="Working_Hours" id="Working_Hours" value="{{ old('Working_Hours') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div>
                            <label for="Working_Days" class="block text-sm font-bold text-gray-700 mb-2">Hari Kerja</label>
                            <input type="text" name="Working_Days" id="Working_Days" value="{{ old('Working_Days') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Holiday" class="block text-sm font-bold text-gray-700 mb-2">Hari Libur</label>
                            <input type="text" name="Holiday" id="Holiday" value="{{ old('Holiday') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Accommodation" class="block text-sm font-bold text-gray-700 mb-2">Akomodasi (Asrama/Apartemen)</label>
                            <input type="text" name="Accommodation" id="Accommodation" value="{{ old('Accommodation') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div>
                            <label for="Meal" class="block text-sm font-bold text-gray-700 mb-2">Makan</label>
                            <input type="text" name="Meal" id="Meal" value="{{ old('Meal') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div>
                            <label for="Transportation" class="block text-sm font-bold text-gray-700 mb-2">Transportasi</label>
                            <input type="text" name="Transportation" id="Transportation" value="{{ old('Transportation') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div>
                            <label for="Insurance" class="block text-sm font-bold text-gray-700 mb-2">Asuransi</label>
                            <input type="text" name="Insurance" id="Insurance" value="{{ old('Insurance') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                    </div>
                </div>

                <!-- 5. Kuota & Jadwal -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">5. Kuota & Jadwal</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="Recruitment_Quantity" class="block text-sm font-bold text-gray-700 mb-2">Kuota Dibutuhkan <span class="text-red-500">*</span></label>
                            <input type="number" name="Recruitment_Quantity" id="Recruitment_Quantity" value="{{ old('Recruitment_Quantity') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required min="1">
                        </div>

                        <div>
                            <label for="Interview_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Wawancara</label>
                            <input type="date" name="Interview_Date" id="Interview_Date" value="{{ old('Interview_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        
                        <div>
                            <label for="Departure_Target" class="block text-sm font-bold text-gray-700 mb-2">Target Keberangkatan</label>
                            <input type="date" name="Departure_Target" id="Departure_Target" value="{{ old('Departure_Target') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="PIC_Employee_ID" class="block text-sm font-bold text-gray-700 mb-2">PIC (Karyawan WMS)</label>
                            <select name="PIC_Employee_ID" id="PIC_Employee_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih PIC --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp['Employee_ID'] }}" {{ old('PIC_Employee_ID') == $emp['Employee_ID'] ? 'selected' : '' }}>
                                        {{ $emp['Employee_ID'] }} - {{ $emp['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 6. Catatan Tambahan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">6. Catatan</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="3" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('job-orders.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Job Order</span>
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
