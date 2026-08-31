@extends('layouts.app')

@section('header', 'Tambah Tenaga Pendidik Baru')

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
    @endphp

    <x-universal.form 
        action="{{ route('teachers.store') }}" 
        method="POST"
        title="Tambah Tenaga Pendidik" 
        description="Pilih Pengguna yang telah terdaftar untuk diangkat sebagai Guru. Kode Guru (TCH) akan dibuat otomatis."
        buttonText="Simpan Guru"
    >
        <div class="space-y-8">
            <!-- SECTION: PILIH PENGGUNA -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Pemilihan Pengguna</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.select 
                            name="User_ID" 
                            label="Pilih Akun Pengguna" 
                            :required="true"
                            :options="$userOptions"
                            value=""
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION: DATA PRIBADI (AUTOFILL READONLY) -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Informasi Pengguna Terpilih</h3>
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
                            placeholder="Contoh: Matematika, Bahasa Inggris, dll."
                        />
                    </div>
                    <x-universal.input 
                        name="Hire_Date" 
                        label="Tanggal Mulai Mengajar" 
                        type="date"
                        :required="true"
                        value="{{ date('Y-m-d') }}"
                    />
                    <x-universal.select 
                        name="Teaching_Status" 
                        label="Status Mengajar" 
                        :required="true"
                        :options="['Aktif Mengajar' => 'Aktif Mengajar', 'Cuti Mengajar' => 'Cuti Mengajar']"
                        value=""
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
                            placeholder="Informasi tambahan terkait pengajaran..."
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userData = {{ \Illuminate\Support\Js::from(collect($users)->mapWithKeys(function($user) {
            return [$user['User_ID'] => [
                'Full_Name' => $user['Full_Name'] ?? '',
                'Phone_Number' => $user['Phone_Number'] ?? '',
                'Email' => $user['Email'] ?? ''
            ]];
        })) }};
        const userSelect = document.getElementById('User_ID');
        
        const autoName = document.getElementById('auto_Full_Name');
        const autoPhone = document.getElementById('auto_Phone');
        const autoEmail = document.getElementById('auto_Email');

        function triggerAutofill() {
            const selectedId = userSelect.value;
            if (selectedId && userData[selectedId]) {
                const data = userData[selectedId];
                autoName.value = data.Full_Name;
                autoPhone.value = data.Phone_Number;
                autoEmail.value = data.Email;
            } else {
                autoName.value = '';
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

