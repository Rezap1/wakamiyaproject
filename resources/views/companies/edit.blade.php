@extends('layouts.app')
@section('header', 'Perbarui Profil Perusahaan')
@section('content')

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.form 
        action="{{ route('companies.update', $company['Company_ID']) }}" 
        method="PUT"
        title="Edit Data Perusahaan" 
        description="ID: {{ $company['Company_ID'] }} | Status: {{ ($company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}"
        buttonText="Perbarui Data"
        enctype="multipart/form-data"
    >
        <div class="space-y-8">
            <!-- Section 1: Identitas & Legalitas -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">1. Identitas & Legalitas Perusahaan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input name="Company_Code" label="Kode Perusahaan" value="{{ $company['Company_Code'] }}" :required="true" />
                    <x-universal.input name="Legal_Name" label="Nama Legalitas (PT/CV)" value="{{ $company['Legal_Name'] }}" :required="true" />
                    <x-universal.input name="Company_Name" label="Nama Populer / Merek" value="{{ $company['Company_Name'] }}" :required="true" />
                    <x-universal.input name="NPWP" label="NPWP (Opsional)" value="{{ $company['NPWP'] }}" />
                    <x-universal.input name="Business_License_Number" label="NIB / Izin Usaha (Opsional)" value="{{ $company['Business_License_Number'] }}" />
                    <x-universal.input name="Director_Name" label="Nama Direktur / Pimpinan" value="{{ $company['Director_Name'] }}" />
                </div>
            </div>

            <!-- Section 2: Alamat & Kontak -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">2. Alamat & Kontak</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.textarea name="Address" label="Alamat Lengkap" value="{{ $company['Address'] }}" />
                    </div>
                    
                    <x-universal.input name="City" label="Kota / Kabupaten" value="{{ $company['City'] }}" />
                    <x-universal.input name="Province" label="Provinsi" value="{{ $company['Province'] }}" />
                    <x-universal.input name="Postal_Code" label="Kode Pos" value="{{ $company['Postal_Code'] }}" />
                    <x-universal.input name="Country" label="Negara" value="{{ $company['Country'] }}" :required="true" />
                    <x-universal.input name="Phone_Number" label="Nomor Telepon" value="{{ $company['Phone_Number'] }}" />
                    <x-universal.input type="email" name="Email" label="Alamat Email" value="{{ $company['Email'] }}" />
                    <div class="md:col-span-2">
                        <x-universal.input type="url" name="Website" label="Situs Web" value="{{ $company['Website'] }}" />
                    </div>
                </div>
            </div>

            <!-- Section 3: Aset Visual & Status -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">3. Aset Visual & Status Sistem</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Logo Upload -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-sm font-bold text-slate-700 mb-3">Logo Perusahaan</label>
                        <div class="flex items-start gap-4">
                            <div id="logo-preview-container" class="h-24 w-24 rounded-xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm relative">
                                @if(!empty($company['Company_Logo']))
                                    <img id="logo-preview" src="{{ Storage::url($company['Company_Logo']) }}" alt="Logo" class="h-full w-full object-cover">
                                    <svg id="logo-placeholder" class="hidden w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                @else
                                    <img id="logo-preview" src="#" alt="Logo Preview" class="hidden h-full w-full object-cover">
                                    <svg id="logo-placeholder" class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                @endif
                            </div>
                            <div class="flex-grow">
                                <input type="file" name="Company_Logo" id="Company_Logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors mb-2">
                                <p class="text-xs text-slate-500 mb-2">JPG, PNG, atau WEBP; maksimum 5MB. Pilih file baru untuk mengganti logo lama.</p>
                                
                                @if(!empty($company['Company_Logo']))
                                <label class="inline-flex items-center text-sm text-rose-600 font-medium cursor-pointer">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500 mr-2">
                                    Hapus Logo Saat Ini
                                </label>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Stamp Upload -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-sm font-bold text-slate-700 mb-3">Stempel / Cap</label>
                        <div class="flex items-start gap-4">
                            <div id="stamp-preview-container" class="h-24 w-24 rounded-xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm relative">
                                @if(!empty($company['Company_Stamp']))
                                    <img id="stamp-preview" src="{{ Storage::url($company['Company_Stamp']) }}" alt="Stamp" class="h-full w-full object-cover">
                                    <svg id="stamp-placeholder" class="hidden w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                @else
                                    <img id="stamp-preview" src="#" alt="Stamp Preview" class="hidden h-full w-full object-cover">
                                    <svg id="stamp-placeholder" class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                @endif
                            </div>
                            <div class="flex-grow">
                                <input type="file" name="Company_Stamp" id="Company_Stamp" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors mb-2">
                                <p class="text-xs text-slate-500 mb-2">JPG, PNG, atau WEBP; maksimum 5MB. Pilih file baru untuk mengganti stempel lama.</p>
                                
                                @if(!empty($company['Company_Stamp']))
                                <label class="inline-flex items-center text-sm text-rose-600 font-medium cursor-pointer">
                                    <input type="checkbox" name="remove_stamp" value="1" class="rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500 mr-2">
                                    Hapus Stempel Saat Ini
                                </label>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <x-universal.select 
                        name="Is_Active" 
                        label="Status Sistem WMS" 
                        :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif (Hapus Sementara)']"
                        value="{{ $company['Is_Active'] ?? 'TRUE' }}"
                        :required="true"
                    />
                </div>
            </div>

            <!-- Notes -->
            <div>
                <x-universal.textarea name="Notes" label="Catatan Internal / Log Perubahan" value="{{ $company['Notes'] }}" />
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function setupImagePreview(inputId, previewId, placeholderId) {
        document.getElementById(inputId).addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            
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
</script>
@endsection
