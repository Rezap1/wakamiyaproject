@extends('layouts.app')

@section('header', 'Buat Pengumuman')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Buat Pengumuman Baru" 
        description="Publikasikan informasi baru untuk seluruh warga sekolah atau target spesifik."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Academic' => '#', 'Announcement' => route('announcements.index'), 'Buat Baru' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('announcements.store') }}" method="POST">
            @csrf
            
            <div class="px-6 md:px-12">
                <x-form-section title="Isi Pengumuman" description="Tuliskan judul dan konten pengumuman secara jelas.">
                    <div class="sm:col-span-2">
                        <x-input name="Title" label="Judul Pengumuman" required value="{{ old('Title') }}" placeholder="Contoh: Jadwal Ujian Akhir Semester Genap 2026" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="Message" label="Pesan Pengumuman" rows="6" required placeholder="Tuliskan informasi lengkap di sini...">{{ old('Message', old('Content')) }}</x-textarea>
                    </div>
                </x-form-section>
                
                <div class="my-8 border-t border-slate-100"></div>

                <x-form-section title="Pengaturan Publikasi" description="Tentukan siapa yang dapat melihat dan kapan pengumuman tayang.">
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-select name="Audience_Type" id="audienceType" label="Target Penerima" required>
                                <option value="ALL_STUDENTS" {{ old('Audience_Type', 'ALL_STUDENTS') == 'ALL_STUDENTS' ? 'selected' : '' }}>Semua Siswa</option>
                                <option value="CLASS" {{ old('Audience_Type') == 'CLASS' ? 'selected' : '' }}>Kelas Tertentu</option>
                            </x-select>
                        </div>
                        <div>
                            <x-select name="Priority" label="Prioritas" required>
                                <option value="NORMAL" {{ old('Priority', 'NORMAL') == 'NORMAL' ? 'selected' : '' }}>Informasi</option>
                                <option value="IMPORTANT" {{ old('Priority') == 'IMPORTANT' ? 'selected' : '' }}>Penting</option>
                                <option value="URGENT" {{ old('Priority') == 'URGENT' ? 'selected' : '' }}>Mendesak</option>
                            </x-select>
                        </div>
                    </div>

                    <div id="classTarget" class="sm:col-span-2 {{ old('Audience_Type') === 'CLASS' ? '' : 'hidden' }}">
                        <x-select name="Audience_ID" label="Kelas" :value="old('Audience_ID')">
                            <option value="">Pilih kelas</option>
                            @foreach($classes ?? [] as $class)
                                <option value="{{ $class['Class_ID'] }}" {{ old('Audience_ID') == $class['Class_ID'] ? 'selected' : '' }}>{{ $class['Class_Code'] ?? $class['Class_ID'] }} — {{ $class['Class_Name'] ?? 'Kelas' }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <x-input type="datetime-local" name="Start_At" label="Mulai Tayang (WIB)" value="{{ old('Start_At') }}" />
                            <p class="mt-1 text-xs text-slate-500">Kosongkan untuk tayang segera.</p>
                        </div>
                        <div>
                            <x-input type="datetime-local" name="Expires_At" label="Berakhir (WIB)" required value="{{ old('Expires_At') }}" />
                        </div>
                        <div>
                            <x-select name="Status" label="Status Publikasi">
                                <option value="PUBLISHED" {{ old('Status', 'PUBLISHED') === 'PUBLISHED' ? 'selected' : '' }}>Terpublikasi</option>
                                <option value="DRAFT" {{ old('Status') === 'DRAFT' ? 'selected' : '' }}>Draf</option>
                                <option value="INACTIVE" {{ old('Status') === 'INACTIVE' ? 'selected' : '' }}>Nonaktif</option>
                            </x-select>
                        </div>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Publikasikan
                </x-button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const audience = document.getElementById('audienceType');
        const classTarget = document.getElementById('classTarget');
        if (audience && classTarget) audience.addEventListener('change', () => classTarget.classList.toggle('hidden', audience.value !== 'CLASS'));
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
        });
    });
</script>
@endsection



