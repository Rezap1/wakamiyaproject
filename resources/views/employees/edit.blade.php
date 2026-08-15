@extends('layouts.app')

@section('header', 'Edit Karyawan')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $userOptions = [];
        foreach($users as $user) {
            $userOptions[$user['User_ID']] = $user['Full_Name'] . ' (' . $user['Email'] . ') - Peran: ' . $user['Role_ID'];
        }
        if(isset($employee['User_ID']) && !collect($users)->contains('User_ID', $employee['User_ID'])) {
            $userOptions[$employee['User_ID']] = $employee['Full_Name'] . ' (' . $employee['Email'] . ')';
        }
        
        $deptOptions = [];
        foreach($departments as $dept) {
            $deptOptions[$dept['Department_ID']] = $dept['Department_Name'];
        }
    @endphp

    <x-universal.form 
        action="{{ route('employees.update', $employee['Employee_ID']) }}" 
        method="PUT"
        :hasFiles="true"
        title="Ubah Data Karyawan" 
        description="Mengubah data karyawan: {{ $employee['Full_Name'] }} ({{ $employee['Employee_ID'] }})"
        buttonText="Perbarui Karyawan"
    >
        <div class="space-y-8">
            <!-- SECTION: DATA PRIBADI -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Data Pribadi & Foto Profil</h3>
                
                <!-- PROFILE PHOTO UPLOAD PREVIEW -->
                <div class="mb-6 bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col md:flex-row items-center gap-4">
                    <div class="relative w-24 h-24 rounded-full overflow-hidden border-2 border-white shadow-md bg-slate-200 flex items-center justify-center shrink-0">
                        @php
                            $photoUrl = !empty($employee['Profile_Photo']) ? $employee['Profile_Photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($employee['Full_Name'] ?? 'Emp') . '&background=0D8ABC&color=fff&size=128';
                        @endphp
                        <img id="photo_preview" src="{{ $photoUrl }}" alt="Foto {{ $employee['Full_Name'] ?? '' }}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 w-full">
                        <label for="Profile_Photo" class="block text-xs font-bold text-slate-700 mb-1">Ganti Foto Profil Karyawan (Opsional)</label>
                        <input type="file" name="Profile_Photo" id="Profile_Photo" accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewImage(this)" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all cursor-pointer">
                        <p class="text-[11px] text-slate-500 mt-1">Format: JPG, PNG, WEBP. Maksimum: 2MB.</p>
                        @error('Profile_Photo') <p class="mt-1 text-xs font-bold text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Employee_ID" 
                        label="ID Karyawan" 
                        value="{{ $employee['Employee_ID'] }}"
                        readonly="true"
                    />
                    <x-universal.input 
                        name="Employee_Number" 
                        label="NIK Karyawan" 
                        value="{{ $employee['Employee_Number'] }}"
                        readonly="true"
                    />
                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="User_ID" 
                            label="Pilih Akun Pengguna" 
                            :required="true"
                            :options="$userOptions"
                            value="{{ $employee['User_ID'] ?? '' }}"
                        />
                    </div>
                    <x-universal.input 
                        name="National_ID" 
                        label="Nomor KTP (NIK Nasional)" 
                        value="{{ $employee['National_ID'] ?? '' }}"
                    />
                    <x-universal.input 
                        name="Birth_Place" 
                        label="Tempat Lahir" 
                        value="{{ $employee['Birth_Place'] ?? '' }}"
                    />
                    <x-universal.input 
                        name="Birth_Date" 
                        label="Tanggal Lahir" 
                        type="date"
                        value="{{ $employee['Birth_Date'] ?? '' }}"
                    />
                    <x-universal.select 
                        name="Gender" 
                        label="Jenis Kelamin" 
                        :required="true"
                        :options="['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan']"
                        value="{{ $employee['Gender'] ?? '' }}"
                    />
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Address" 
                            label="Alamat Domisili" 
                            value="{{ $employee['Address'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION: DATA PEKERJAAN -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Data Pekerjaan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.searchable-select 
                        name="Department_ID" 
                        label="Departemen" 
                        :required="true"
                        :options="$deptOptions"
                        value="{{ $employee['Department_ID'] ?? '' }}"
                    />
                    
                    <div>
                        <label for="Position_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Posisi / Jabatan <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Position_ID" id="Position_ID" required class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Position_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                <option value="" disabled>Pilih Posisi...</option>
                                @foreach($positions as $pos)
                                    <option value="{{ $pos['Position_ID'] }}" data-dept="{{ $pos['Department_ID'] }}" {{ old('Position_ID', $employee['Position_ID']) == $pos['Position_ID'] ? 'selected' : '' }}>
                                        {{ $pos['Position_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('Position_ID') <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</p> @enderror
                    </div>

                    <x-universal.input 
                        name="Join_Date" 
                        label="Tanggal Bergabung" 
                        type="date"
                        :required="true"
                        value="{{ $employee['Join_Date'] ?? '' }}"
                    />
                    <x-universal.select 
                        name="Employment_Status" 
                        label="Status Kepegawaian" 
                        :required="true"
                        :options="['Tetap (PKWTT)' => 'Tetap (PKWTT)', 'Kontrak (PKWT)' => 'Kontrak (PKWT)', 'Probation' => 'Probation (Percobaan)', 'Magang' => 'Magang (Internship)']"
                        value="{{ $employee['Employment_Status'] ?? '' }}"
                    />
                </div>
            </div>

            <!-- SECTION: DATA PERBANKAN -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Data Perbankan & Pajak</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Tax_Number" 
                        label="NPWP" 
                        value="{{ $employee['Tax_Number'] ?? '' }}"
                    />
                    <x-universal.select 
                        name="Bank_Name" 
                        label="Nama Bank" 
                        :options="['BCA' => 'BCA', 'Mandiri' => 'Mandiri', 'BNI' => 'BNI', 'BRI' => 'BRI', 'BSI' => 'BSI', 'Lainnya' => 'Lainnya']"
                        value="{{ $employee['Bank_Name'] ?? '' }}"
                    />
                    <x-universal.input 
                        name="Bank_Account_Number" 
                        label="Nomor Rekening" 
                        value="{{ $employee['Bank_Account_Number'] ?? '' }}"
                    />
                    <x-universal.input 
                        name="Account_Holder_Name" 
                        label="Nama Pemilik Rekening" 
                        value="{{ $employee['Account_Holder_Name'] ?? '' }}"
                    />
                </div>
            </div>

            <!-- SECTION: LAINNYA -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Konfigurasi Tambahan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.select 
                        name="Is_Active" 
                        label="Status Karyawan" 
                        :required="true"
                        :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                        value="{{ $employee['Is_Active'] ?? 'TRUE' }}"
                    />
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Tambahan" 
                            value="{{ $employee['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deptSelect = document.getElementById('Department_ID');
        const posSelect = document.getElementById('Position_ID');
        const posOptions = Array.from(posSelect.options);
        const oldPosId = "{{ old('Position_ID', $employee['Position_ID']) }}";

        function filterPositions() {
            const selectedDept = deptSelect.value;
            let hasValidOptions = false;
            
            posSelect.innerHTML = '';
            
            if (!selectedDept) {
                const defaultOption = new Option('Pilih Departemen...', '', true, true);
                defaultOption.disabled = true;
                posSelect.add(defaultOption);
                return;
            }

            posOptions.forEach(option => {
                if (option.value && option.getAttribute('data-dept') === selectedDept) {
                    const newOption = new Option(option.text, option.value);
                    if (option.value === oldPosId) {
                        newOption.selected = true;
                    }
                    posSelect.add(newOption);
                    hasValidOptions = true;
                }
            });
            
            if (!hasValidOptions) {
                posSelect.innerHTML = '';
                const emptyOption = new Option('Tidak ada posisi di departemen ini', '', true, true);
                emptyOption.disabled = true;
                posSelect.add(emptyOption);
            }
        }

        deptSelect.addEventListener('change', filterPositions);
        
        if (deptSelect.value) {
            filterPositions();
        }
    });

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photo_preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection

