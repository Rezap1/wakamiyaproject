@extends('layouts.app')
@section('header', 'Pengaturan Sistem')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto" x-data="emailSettings()">
    <x-page-header title="Pengaturan Sistem" description="Pusat konfigurasi master untuk Sistem Manajemen Wakamiya." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Pengaturan' => '#']" />

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Sidebar Navigation -->
        <div class="w-full md:w-64 shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <div class="p-4 bg-slate-50 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm tracking-wide uppercase">Kategori</h3>
                </div>
                <div class="flex flex-col">
                    @php
                        $categoryLabels = [
                            'General' => 'Umum',
                            'Company' => 'Profil Perusahaan',
                            'Company_Bank' => 'Rekening Bank',
                            'Company_Document' => 'Dokumen & TTD',
                            'Academic' => 'Akademik',
                            'Finance' => 'Keuangan',
                            'Payroll' => 'Penggajian',
                            'Attendance' => 'Kehadiran',
                            'Assessment' => 'Penilaian',
                            'Notification' => 'Notifikasi',
                            'Email_Delivery' => 'Email Delivery',
                            'Workflow' => 'Alur Kerja',
                            'Document' => 'Dokumen',
                            'Security' => 'Keamanan',
                            'System' => 'Sistem',
                        ];

                        $categoryIcons = [
                            'General' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
                            'Company' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>',
                            'Company_Bank' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>',
                            'Company_Document' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
                            'Finance' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                            'Email_Delivery' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>',
                        ];
                    @endphp
                    @foreach($categories as $cat)
                        <a href="{{ route('settings.index', ['tab' => $cat]) }}" 
                           class="px-5 py-3 text-sm font-semibold border-l-4 transition-all flex items-center gap-2.5 {{ $activeTab == $cat ? 'bg-blue-50 border-blue-600 text-blue-700' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                            @if(isset($categoryIcons[$cat]))
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $categoryIcons[$cat] !!}</svg>
                            @endif
                            {{ $categoryLabels[$cat] ?? $cat }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="flex-grow">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Konfigurasi {{ $categoryLabels[$activeTab] ?? $activeTab }}</h2>
                        <p class="text-sm text-slate-500 mt-1">Kelola semua parameter dan pengaturan yang terkait dengan {{ strtolower($categoryLabels[$activeTab] ?? $activeTab) }}.</p>
                    </div>
                    @if($activeTab === 'Email_Delivery')
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
                            🟢 Email Sender Configured
                        </span>
                    @endif
                </div>

                @if($activeTab === 'Email_Delivery')
                    @php
                        $senderConfig = app(\App\Services\Core\EmailDeliveryService::class)->getSenderConfig();
                    @endphp
                    <div class="p-6 space-y-6">
                        <div class="bg-slate-900 text-white rounded-2xl p-6 border border-slate-800 space-y-5">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-400">Pengaturan Alamat Pengirim (Email Sender)</h3>
                                    <p class="text-xs text-slate-400 mt-0.5">Admin cukup menentukan Email dan Nama Pengirim. Provider Email dikelola terpisah di layer environment terenkripsi.</p>
                                </div>
                                <button type="button" @click="openTestModal = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-1.5">
                                    🚀 <span>Test Email</span>
                                </button>
                            </div>

                            <form action="{{ route('settings.update') }}" method="POST" class="space-y-4">
                                @csrf
                                <input type="hidden" name="active_tab" value="Email_Delivery">

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Email Pengirim (EMAIL_FROM_ADDRESS)</label>
                                    <input type="email" name="settings[SET_EMAIL_FROM_ADDRESS]" value="{{ $senderConfig['from_address'] }}" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-3 font-semibold focus:ring-2 focus:ring-emerald-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Nama Pengirim (EMAIL_FROM_NAME)</label>
                                    <input type="text" name="settings[SET_EMAIL_FROM_NAME]" value="{{ $senderConfig['from_name'] }}" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-3 font-semibold focus:ring-2 focus:ring-emerald-500">
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-md transition-all">
                                        Simpan Configuration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                    
                    @if($settings->count() > 0)
                    <div class="space-y-6 mb-8">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Pengaturan {{ $categoryLabels[$activeTab] ?? $activeTab }}</h3>
                        @foreach($settings as $s)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 py-3 border-b border-slate-50 last:border-0">
                                <div class="w-full md:w-1/2">
                                    <label class="font-bold text-slate-700 text-sm block">{{ $s['Setting_Name'] }}</label>
                                    <span class="text-xs text-slate-400">{{ $s['Description'] ?? '' }}</span>
                                    @if(!empty($s['Setting_Key']))
                                        <span class="text-[10px] font-mono text-slate-300 block mt-0.5">{{ $s['Setting_Key'] }}</span>
                                    @endif
                                </div>
                                <div class="w-full md:w-1/2">
                                    @if(($s['Value_Type'] ?? 'text') == 'boolean')
                                        <select name="settings[{{ $s['Setting_ID'] }}]" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">
                                            <option value="true" {{ $s['Setting_Value'] == 'true' ? 'selected' : '' }}>Aktif</option>
                                            <option value="false" {{ $s['Setting_Value'] == 'false' ? 'selected' : '' }}>Nonaktif</option>
                                        </select>
                                    @elseif(($s['Value_Type'] ?? 'text') == 'textarea')
                                        <textarea name="settings[{{ $s['Setting_ID'] }}]" rows="3" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">{{ $s['Setting_Value'] }}</textarea>
                                    @elseif(($s['Value_Type'] ?? 'text') == 'file')
                                        <div class="space-y-2">
                                            @if(!empty($s['Setting_Value']))
                                                <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg border border-slate-200">
                                                    <img src="{{ asset($s['Setting_Value']) }}" alt="{{ $s['Setting_Name'] }}" class="w-12 h-12 object-contain rounded border border-slate-200 bg-white" onerror="this.style.display='none'">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-xs font-mono text-slate-500 truncate">{{ $s['Setting_Value'] }}</p>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-2 p-2 bg-amber-50 rounded-lg border border-amber-200">
                                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                                                    <span class="text-xs text-amber-700 font-semibold">Belum ada file yang diunggah.</span>
                                                </div>
                                            @endif
                                            <input type="file" name="setting_files[{{ $s['Setting_ID'] }}]" accept="image/jpeg,image/png,image/jpg,image/webp" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all cursor-pointer">
                                            <p class="text-[11px] text-slate-400">Format: JPG, PNG, WEBP. Maks: 2MB.</p>
                                        </div>
                                    @else
                                        <input type="{{ ($s['Value_Type'] ?? 'text') == 'number' ? 'number' : 'text' }}" name="settings[{{ $s['Setting_ID'] }}]" value="{{ $s['Setting_Value'] }}" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($parameters->count() > 0)
                    <div class="space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Parameter Modul</h3>
                        @foreach($parameters as $p)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 py-2 border-b border-slate-50 last:border-0">
                                <div class="w-full md:w-1/2">
                                    <label class="font-bold text-slate-700 text-sm block">{{ str_replace('_', ' ', $p['Parameter_Key']) }}</label>
                                    <span class="text-xs text-slate-400">{{ $p['Description'] ?? '' }}</span>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <input type="text" name="parameters[{{ $p['Parameter_ID'] }}]" value="{{ $p['Parameter_Value'] }}" class="w-full rounded-lg border-slate-200 text-sm font-mono text-blue-600 focus:ring-blue-500 focus:border-blue-500 bg-blue-50/50">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($settings->count() == 0 && $parameters->count() == 0)
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-slate-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <h3 class="text-slate-400 font-bold">Tidak Ada Konfigurasi Ditemukan</h3>
                        <p class="text-sm text-slate-400 mt-1">Belum ada pengaturan yang tersedia untuk kategori ini.</p>
                    </div>
                    @else
                    <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold rounded-xl shadow-sm hover:bg-emerald-700 transition-colors">Simpan Perubahan</button>
                    </div>
                    @endif
                </form>

                @endif
            </div>
        </div>
    </div>

    <!-- TEST EMAIL MODAL -->
    <div x-show="openTestModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
        <div class="bg-slate-900 border border-slate-800 text-slate-200 rounded-2xl p-6 w-full max-w-md space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white">🚀 Kirim Test Email</h3>
                <button @click="openTestModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Email Penerima Test (Recipient Target)</label>
                <input type="email" x-model="testRecipient" placeholder="rezagaming800@gmail.com" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button @click="openTestModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Batal</button>
                <button @click="runTestEmail()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30">
                    Kirim Test Email
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function emailSettings() {
    return {
        openTestModal: false,
        testRecipient: 'rezagaming800@gmail.com',

        runTestEmail() {
            if (!this.testRecipient) {
                alert('Masukkan email penerima test!');
                return;
            }

            fetch("{{ route('settings.test_email') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ recipient: this.testRecipient })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    this.openTestModal = false;
                }
            })
            .catch(err => {
                alert('Gagal mengirim test email: ' + err.message);
            });
        }
    }
}
</script>
@endsection
