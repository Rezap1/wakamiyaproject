@extends('layouts.app')

@section('header', 'Tambah Karyawan Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $userOptions = [];
        foreach($users as $user) {
            $roleId = $user['Role_ID'] ?? '';
            $roleName = \App\Helpers\UserResolverHelper::getRoleName($roleId);
            if (strtoupper(trim($roleName)) === 'STUDENT' || $roleId === 'ROL000008') {
                continue;
            }
            $userOptions[$user['User_ID']] = $user['Full_Name'] . ' (' . $user['Email'] . ') - Peran: ' . $roleName;
        }
        
        $deptOptions = [];
        foreach($departments as $dept) {
            $deptOptions[$dept['Department_ID']] = $dept['Department_Name'];
        }
    @endphp

    <x-universal.form 
        action="{{ route('employees.store') }}" 
        method="POST"
        :hasFiles="true"
        title="Tambah Karyawan" 
        description="Lengkapi informasi di bawah ini. Nomor Induk Karyawan (NIK) akan dibuat otomatis oleh sistem."
        buttonText="Simpan Karyawan"
    >
        <div class="space-y-8">
            <!-- SECTION: DATA PRIBADI -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Data Pribadi & Foto Profil</h3>
                
                <!-- PROFILE PHOTO UPLOAD PREVIEW -->
                <div class="mb-6 bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col md:flex-row items-center gap-4">
                    <div class="relative w-24 h-24 rounded-full overflow-hidden border-2 border-white shadow-md bg-slate-200 flex items-center justify-center shrink-0">
                        <img id="photo_preview" src="https://ui-avatars.com/api/?name=Karyawan+Baru&background=0D8ABC&color=fff&size=128" alt="Preview Foto" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 w-full">
                        <label for="Profile_Photo" class="block text-xs font-bold text-slate-700 mb-1">Foto Profil Karyawan (Opsional)</label>
                        <input type="file" name="Profile_Photo" id="Profile_Photo" accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewImage(this)" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all cursor-pointer">
                        <p class="text-[11px] text-slate-500 mt-1">Format: JPG, PNG, WEBP. Maksimum: 2MB.</p>
                        @error('Profile_Photo') <p class="mt-1 text-xs font-bold text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="User_ID" 
                            label="Pilih Akun Pengguna" 
                            :required="true"
                            :options="$userOptions"
                            value=""
                        />
                    </div>
                    <x-universal.input 
                        name="National_ID" 
                        label="Nomor KTP (NIK Nasional)" 
                        placeholder="16 Digit NIK"
                    />
                    <div></div> <!-- Spacing -->
                    <x-universal.input 
                        name="Birth_Place" 
                        label="Tempat Lahir" 
                        placeholder="Kota Kelahiran"
                    />
                    <x-universal.input 
                        name="Birth_Date" 
                        label="Tanggal Lahir" 
                        type="date"
                    />
                    <x-universal.select 
                        name="Gender" 
                        label="Jenis Kelamin" 
                        :required="true"
                        :options="['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan']"
                        value=""
                    />
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Address" 
                            label="Alamat Domisili" 
                            placeholder="Alamat lengkap..."
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
                        value=""
                    />
                    
                    <div>
                        <label for="Position_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Posisi / Jabatan <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Position_ID" id="Position_ID" required class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Position_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                <option value="" disabled selected>Pilih Departemen Terlebih Dahulu...</option>
                                @foreach($positions as $pos)
                                    <option value="{{ $pos['Position_ID'] }}" data-dept="{{ $pos['Department_ID'] }}" {{ old('Position_ID') == $pos['Position_ID'] ? 'selected' : '' }}>
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
                        value="{{ date('Y-m-d') }}"
                    />
                    <x-universal.select 
                        name="Employment_Status" 
                        label="Status Kepegawaian" 
                        :required="true"
                        :options="['Tetap (PKWTT)' => 'Tetap (PKWTT)', 'Kontrak (PKWT)' => 'Kontrak (PKWT)', 'Probation' => 'Probation (Percobaan)', 'Magang' => 'Magang (Internship)']"
                        value=""
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
                        placeholder="Nomor NPWP"
                    />
                    <x-universal.select 
                        name="Bank_Name" 
                        label="Nama Bank" 
                        :options="['BCA' => 'BCA', 'Mandiri' => 'Mandiri', 'BNI' => 'BNI', 'BRI' => 'BRI', 'BSI' => 'BSI', 'Lainnya' => 'Lainnya']"
                        value=""
                    />
                    <x-universal.input 
                        name="Bank_Account_Number" 
                        label="Nomor Rekening" 
                        placeholder="Nomor Rekening"
                    />
                    <x-universal.input 
                        name="Account_Holder_Name" 
                        label="Nama Pemilik Rekening" 
                        placeholder="Sesuai Buku Tabungan"
                    />
                </div>
            </div>

            <!-- SECTION: LAINNYA -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Lainnya</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Tambahan" 
                            placeholder="Informasi medis, kontak darurat, atau catatan khusus..."
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
        const oldPosId = "{{ old('Position_ID') }}";

        function filterPositions() {
            const selectedDept = deptSelect.value;
            let hasValidOptions = false;
            
            posSelect.innerHTML = '';
            
            if (!selectedDept) {
                const defaultOption = new Option('Pilih Departemen Terlebih Dahulu...', '', true, true);
                defaultOption.disabled = true;
                posSelect.add(defaultOption);
                return;
            }

            const defaultOption = new Option('Pilih Posisi / Jabatan...', '', true, true);
            defaultOption.disabled = true;
            posSelect.add(defaultOption);

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

