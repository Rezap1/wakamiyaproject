@extends('layouts.app')

@section('header', 'Perbarui Profil Perusahaan')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Edit Data Perusahaan</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">ID: <span class="font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded font-mono">{{ $company['Company_ID'] }}</span></p>
            </div>
            <div>
                @if(($company['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                        Status: Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                        Status: Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('companies.update', $company['Company_ID']) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                @csrf
                @method('PUT')
                
                <!-- Section 1: Identitas & Legalitas -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">1. Identitas & Legalitas Perusahaan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Company_Code" class="block text-sm font-bold text-gray-700">Kode Perusahaan <span class="text-red-500">*</span></label>
                            <input type="text" name="Company_Code" id="Company_Code" value="{{ old('Company_Code', $company['Company_Code']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono uppercase @error('Company_Code') border-red-300 text-red-900 @enderror">
                            @error('Company_Code') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Legal_Name" class="block text-sm font-bold text-gray-700">Nama Legalitas (PT/CV) <span class="text-red-500">*</span></label>
                            <input type="text" name="Legal_Name" id="Legal_Name" value="{{ old('Legal_Name', $company['Legal_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Legal_Name') border-red-300 text-red-900 @enderror">
                            @error('Legal_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Company_Name" class="block text-sm font-bold text-gray-700">Nama Populer / Merek <span class="text-red-500">*</span></label>
                            <input type="text" name="Company_Name" id="Company_Name" value="{{ old('Company_Name', $company['Company_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Company_Name') border-red-300 text-red-900 @enderror">
                            @error('Company_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="NPWP" class="block text-sm font-bold text-gray-700">NPWP <span class="text-gray-400 font-normal">(Opsional)</span></label>
                            <input type="text" name="NPWP" id="NPWP" value="{{ old('NPWP', $company['NPWP']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono @error('NPWP') border-red-300 text-red-900 @enderror">
                            @error('NPWP') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Business_License_Number" class="block text-sm font-bold text-gray-700">NIB / Izin Usaha <span class="text-gray-400 font-normal">(Opsional)</span></label>
                            <input type="text" name="Business_License_Number" id="Business_License_Number" value="{{ old('Business_License_Number', $company['Business_License_Number']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono">
                        </div>

                        <div>
                            <label for="Director_Name" class="block text-sm font-bold text-gray-700">Nama Direktur / Pimpinan</label>
                            <input type="text" name="Director_Name" id="Director_Name" value="{{ old('Director_Name', $company['Director_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Alamat & Kontak -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">2. Alamat & Kontak</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="Address" class="block text-sm font-bold text-gray-700">Alamat Lengkap</label>
                            <textarea id="Address" name="Address" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Address', $company['Address']) }}</textarea>
                        </div>
                        
                        <div>
                            <label for="City" class="block text-sm font-bold text-gray-700">Kota / Kabupaten</label>
                            <input type="text" name="City" id="City" value="{{ old('City', $company['City']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        <div>
                            <label for="Province" class="block text-sm font-bold text-gray-700">Provinsi / State</label>
                            <input type="text" name="Province" id="Province" value="{{ old('Province', $company['Province']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <div>
                            <label for="Postal_Code" class="block text-sm font-bold text-gray-700">Kode Pos</label>
                            <input type="text" name="Postal_Code" id="Postal_Code" value="{{ old('Postal_Code', $company['Postal_Code']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono">
                        </div>
                        <div>
                            <label for="Country" class="block text-sm font-bold text-gray-700">Negara <span class="text-red-500">*</span></label>
                            <input type="text" name="Country" id="Country" value="{{ old('Country', $company['Country']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Country') border-red-300 text-red-900 @enderror">
                            @error('Country') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Phone_Number" class="block text-sm font-bold text-gray-700">Nomor Telepon</label>
                            <input type="text" name="Phone_Number" id="Phone_Number" value="{{ old('Phone_Number', $company['Phone_Number']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono">
                        </div>
                        <div>
                            <label for="Email" class="block text-sm font-bold text-gray-700">Alamat Email</label>
                            <input type="email" name="Email" id="Email" value="{{ old('Email', $company['Email']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Email') border-red-300 text-red-900 @enderror">
                            @error('Email') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="Website" class="block text-sm font-bold text-gray-700">URL Website</label>
                            <input type="url" name="Website" id="Website" value="{{ old('Website', $company['Website']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Website') border-red-300 text-red-900 @enderror">
                            @error('Website') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 3: Aset Visual & Status -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">3. Aset Visual & Status Sistem</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Logo Upload -->
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Logo Perusahaan</label>
                            <div class="flex items-start gap-4">
                                <div id="logo-preview-container" class="h-24 w-24 rounded-xl bg-white border border-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm relative">
                                    @if(!empty($company['Company_Logo']))
                                        <img id="logo-preview" src="{{ Storage::url($company['Company_Logo']) }}" alt="Logo" class="h-full w-full object-cover">
                                        <svg id="logo-placeholder" class="hidden w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    @else
                                        <img id="logo-preview" src="#" alt="Logo Preview" class="hidden h-full w-full object-cover">
                                        <svg id="logo-placeholder" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    @endif
                                </div>
                                <div class="flex-grow">
                                    <input type="file" name="Company_Logo" id="Company_Logo" accept="image/jpeg,image/png,image/svg+xml" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-colors mb-2">
                                    <p class="text-xs text-gray-500 mb-2">Pilih file baru untuk mengganti logo lama.</p>
                                    
                                    @if(!empty($company['Company_Logo']))
                                    <label class="inline-flex items-center text-sm text-red-600 font-medium cursor-pointer">
                                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500 mr-2">
                                        Hapus Logo Saat Ini
                                    </label>
                                    @endif
                                    
                                    @error('Company_Logo') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Stamp Upload -->
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Stempel / Cap</label>
                            <div class="flex items-start gap-4">
                                <div id="stamp-preview-container" class="h-24 w-24 rounded-xl bg-white border border-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm relative">
                                    @if(!empty($company['Company_Stamp']))
                                        <img id="stamp-preview" src="{{ Storage::url($company['Company_Stamp']) }}" alt="Stamp" class="h-full w-full object-cover">
                                        <svg id="stamp-placeholder" class="hidden w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    @else
                                        <img id="stamp-preview" src="#" alt="Stamp Preview" class="hidden h-full w-full object-cover">
                                        <svg id="stamp-placeholder" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    @endif
                                </div>
                                <div class="flex-grow">
                                    <input type="file" name="Company_Stamp" id="Company_Stamp" accept="image/jpeg,image/png,image/svg+xml" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-colors mb-2">
                                    <p class="text-xs text-gray-500 mb-2">Pilih file baru untuk mengganti stempel lama.</p>
                                    
                                    @if(!empty($company['Company_Stamp']))
                                    <label class="inline-flex items-center text-sm text-red-600 font-medium cursor-pointer">
                                        <input type="checkbox" name="remove_stamp" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500 mr-2">
                                        Hapus Stempel Saat Ini
                                    </label>
                                    @endif
                                    
                                    @error('Company_Stamp') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="Is_Active" class="block text-sm font-bold text-gray-700">Status Sistem WMS <span class="text-red-500">*</span></label>
                            <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-bold">
                                <option value="TRUE" {{ old('Is_Active', $company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                                <option value="FALSE" {{ old('Is_Active', $company['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif (Soft Delete)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal / Log Perubahan</label>
                    <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $company['Notes']) }}</textarea>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 mt-8">
                    <a href="{{ route('companies.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Data</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Image Previews
    function setupImagePreview(inputId, previewId, placeholderId) {
        document.getElementById(inputId).addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            
            // Uncheck remove checkbox if user selects a new file
            const removeCheckbox = document.querySelector(`input[name="remove_${inputId.split('_')[1].toLowerCase()}"]`);
            if (removeCheckbox) removeCheckbox.checked = false;
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (placeholder) placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            }
        });
    }

    setupImagePreview('Company_Logo', 'logo-preview', 'logo-placeholder');
    setupImagePreview('Company_Stamp', 'stamp-preview', 'stamp-placeholder');

    // Submit Loading State
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
