@extends('layouts.app')

@section('header', 'Perbarui Data Tenaga Pendidik')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $userOptions = [];
        foreach($users as $user) {
            $userOptions[$user['User_ID']] = $user['Full_Name'] . ' (' . $user['Email'] . ') - Role: ' . $user['Role_ID'];
        }
        if(isset($teacher['User_ID']) && !collect($users)->contains('User_ID', $teacher['User_ID'])) {
            $userOptions[$teacher['User_ID']] = ($teacher['Full_Name'] ?? '') . ' (' . ($teacher['Email'] ?? '') . ')';
        }
    @endphp

    <x-universal.form 
        action="{{ route('teachers.update', $teacher['Teacher_ID']) }}" 
        method="PUT"
        title="Formulir Pembaruan Profil Guru" 
        description="Mengubah data pengajar: {{ $teacher['Teacher_Code'] }}"
        buttonText="Perbarui Profil Guru"
    >
        <div class="space-y-8">
            <!-- SECTION: PILIH PENGGUNA -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Tautan Akun Pengguna</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="User_ID" 
                            label="Akun Tersambung" 
                            :required="true"
                            :options="$userOptions"
                            value="{{ $teacher['User_ID'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION: DATA PRIBADI (AUTOFILL READONLY) -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Informasi Pribadi Tersinkronisasi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="auto_Full_Name" 
                            label="Nama Lengkap" 
                            readonly="true"
                            placeholder="-"
                        />
                    </div>
                    <x-universal.input 
                        name="auto_Gender" 
                        label="Jenis Kelamin" 
                        readonly="true"
                        placeholder="-"
                    />
                    <x-universal.input 
                        name="auto_Phone" 
                        label="Nomor Telepon" 
                        readonly="true"
                        placeholder="-"
                    />
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="auto_Email" 
                            label="Email Utama" 
                            readonly="true"
                            placeholder="-"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION: DATA PENGAJAR -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Informasi Pengajaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Specialization" 
                            label="Spesialisasi Mata Pelajaran" 
                            :required="true"
                            value="{{ $teacher['Specialization'] ?? '' }}"
                        />
                    </div>
                    <x-universal.input 
                        name="Hire_Date" 
                        label="Tanggal Mulai Mengajar" 
                        type="date"
                        :required="true"
                        value="{{ $teacher['Hire_Date'] ?? '' }}"
                    />
                    <x-universal.select 
                        name="Teaching_Status" 
                        label="Status Mengajar" 
                        :required="true"
                        :options="['Aktif Mengajar' => 'Aktif Mengajar', 'Cuti Mengajar' => 'Cuti Mengajar']"
                        value="{{ $teacher['Teaching_Status'] ?? '' }}"
                    />
                </div>
            </div>

            <!-- SECTION: PENGATURAN -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Pengaturan Sistem & Lainnya</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="Is_Active" 
                            label="Akses Sistem (Status)" 
                            :required="true"
                            :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                            value="{{ $teacher['Is_Active'] ?? 'TRUE' }}"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Tambahan" 
                            value="{{ $teacher['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userData = {!! json_encode(collect($users)->push(['User_ID' => $teacher['User_ID'] ?? '', 'Full_Name' => $teacher['Full_Name'] ?? '', 'Phone_Number' => $teacher['Phone_Number'] ?? '', 'Email' => $teacher['Email'] ?? '', 'Gender' => $teacher['Gender'] ?? ''])->mapWithKeys(function($user) {
            return [$user['User_ID'] => [
                'Full_Name' => $user['Full_Name'] ?? '',
                'Phone_Number' => $user['Phone_Number'] ?? '',
                'Email' => $user['Email'] ?? '',
                'Gender' => $user['Gender'] ?? ''
            ]];
        })) !!};
        const userSelect = document.getElementById('User_ID');
        
        const autoName = document.getElementById('auto_Full_Name');
        const autoGender = document.getElementById('auto_Gender');
        const autoPhone = document.getElementById('auto_Phone');
        const autoEmail = document.getElementById('auto_Email');

        function triggerAutofill() {
            const selectedId = userSelect.value;
            if (selectedId && userData[selectedId]) {
                const data = userData[selectedId];
                autoName.value = data.Full_Name || '-';
                if(autoGender) autoGender.value = data.Gender || '-';
                autoPhone.value = data.Phone_Number || '-';
                autoEmail.value = data.Email || '-';
            } else {
                autoName.value = '';
                if(autoGender) autoGender.value = '';
                autoPhone.value = '';
                autoEmail.value = '';
            }
        }

        userSelect.addEventListener('change', triggerAutofill);
        
        if (userSelect.value) {
            triggerAutofill();
        }
    });
</script>
@endsection

