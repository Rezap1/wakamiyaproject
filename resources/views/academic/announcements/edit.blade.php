@extends('layouts.app')

@section('header', 'Edit Pengumuman')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Formulir Pembaruan Pengumuman" 
        description="Mengubah data pengumuman: {{ $announcement['Announcement_ID'] ?? '' }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Academic' => '#', 'Announcement' => route('announcements.index'), 'Edit' => '#']"
    >
        <x-slot:actions>
            @php
                $status = $announcement['Status'] ?? 'PUBLISHED';
                $statusColor = match($status) {
                    'PUBLISHED' => 'green',
                    'DRAFT' => 'gray',
                    default => 'blue'
                };
            @endphp
            <x-badge color="{{ $statusColor }}" type="solid">
                Status Saat Ini: {{ $status }}
            </x-badge>
        </x-slot:actions>
    </x-page-header>

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('announcements.update', $announcement['Announcement_ID']) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="px-6 md:px-12">
                <x-form-section title="Isi Pengumuman" description="Tuliskan judul dan konten pengumuman secara jelas.">
                    <div class="sm:col-span-2">
                        <x-input name="Title" label="Judul Pengumuman" required value="{{ old('Title', $announcement['Title'] ?? '') }}" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="Content" label="Konten / Isi Pengumuman" rows="6" required>{{ old('Content', $announcement['Content'] ?? '') }}</x-textarea>
                    </div>
                </x-form-section>
                
                <div class="my-8 border-t border-slate-100"></div>

                <x-form-section title="Pengaturan Publikasi" description="Tentukan siapa yang dapat melihat dan kapan pengumuman tayang.">
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-select name="Target_Role" label="Target Penerima" required>
                                <option value="ALL" {{ old('Target_Role', $announcement['Target_Role'] ?? '') == 'ALL' ? 'selected' : '' }}>Semua Pengguna (ALL)</option>
                                <option value="TEACHER" {{ old('Target_Role', $announcement['Target_Role'] ?? '') == 'TEACHER' ? 'selected' : '' }}>Guru Saja (TEACHER)</option>
                                <option value="STUDENT" {{ old('Target_Role', $announcement['Target_Role'] ?? '') == 'STUDENT' ? 'selected' : '' }}>Siswa Saja (STUDENT)</option>
                                <option value="ALL_USERS" {{ old('Target_Role', $announcement['Target_Role'] ?? '') == 'ALL_USERS' ? 'selected' : '' }} class="hidden">Semua Pengguna (ALL_USERS)</option>
                            </x-select>
                        </div>
                        <div>
                            <x-select name="Status" label="Status Publikasi" required>
                                <option value="PUBLISHED" {{ old('Status', $announcement['Status'] ?? '') == 'PUBLISHED' ? 'selected' : '' }}>Published (Terpublikasi)</option>
                                <option value="DRAFT" {{ old('Status', $announcement['Status'] ?? '') == 'DRAFT' ? 'selected' : '' }}>Draft (Belum Dipublikasikan)</option>
                            </x-select>
                        </div>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input type="date" name="Publish_Date" label="Tanggal Publikasi" value="{{ old('Publish_Date', $announcement['Publish_Date'] ?? '') }}" />
                        </div>
                        <div>
                            <x-input type="date" name="Expired_Date" label="Tanggal Berakhir (Opsional)" value="{{ old('Expired_Date', $announcement['Expired_Date'] ?? '') }}" />
                        </div>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Perbarui Pengumuman
                </x-button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
        });
    });
</script>
@endsection



