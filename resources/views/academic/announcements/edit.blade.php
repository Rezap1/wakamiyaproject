@extends('layouts.app')

@section('header', 'Edit Pengumuman')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Edit Pengumuman"
        description="Perbarui isi, target, prioritas, atau jadwal tayang."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Pengumuman' => route('announcements.index'), 'Edit' => '#']"
    />
    @php
        $currentAudience = old('Audience_Type', $announcement['Audience_Type'] ?? (($announcement['Target_Role'] ?? '') === 'CLASS' ? 'CLASS' : 'ALL_STUDENTS'));
        $currentClass = old('Audience_ID', $announcement['Audience_ID'] ?? $announcement['Target_ID'] ?? '');
        $dateValue = function ($value) { try { return $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\\TH:i') : ''; } catch (\Throwable) { return ''; } };
    @endphp
    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('announcements.update', $announcement['Announcement_ID']) }}" method="POST">
            @csrf @method('PUT')
            <div class="px-6 md:px-12">
                <x-form-section title="Isi Pengumuman" description="Gunakan bahasa yang singkat dan mudah dipahami.">
                    <div class="sm:col-span-2"><x-input name="Title" label="Judul Pengumuman" required value="{{ old('Title', $announcement['Title'] ?? '') }}" /></div>
                    <div class="sm:col-span-2"><x-textarea name="Message" label="Pesan Pengumuman" rows="6" required>{{ old('Message', $announcement['Message'] ?? $announcement['Content'] ?? '') }}</x-textarea></div>
                </x-form-section>
                <div class="my-8 border-t border-slate-100"></div>
                <x-form-section title="Pengaturan Publikasi" description="Atur target dan waktu menggunakan zona waktu Indonesia (WIB).">
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <x-select name="Audience_Type" id="audienceType" label="Target Penerima" required>
                            <option value="ALL_STUDENTS" {{ $currentAudience === 'ALL_STUDENTS' ? 'selected' : '' }}>Semua Siswa</option>
                            <option value="CLASS" {{ $currentAudience === 'CLASS' ? 'selected' : '' }}>Kelas Tertentu</option>
                        </x-select>
                        <x-select name="Priority" label="Prioritas" required>
                            <option value="NORMAL" {{ strtoupper((string) old('Priority', $announcement['Priority'] ?? 'NORMAL')) === 'NORMAL' ? 'selected' : '' }}>Informasi</option>
                            <option value="IMPORTANT" {{ strtoupper((string) old('Priority', $announcement['Priority'] ?? '')) === 'IMPORTANT' ? 'selected' : '' }}>Penting</option>
                            <option value="URGENT" {{ strtoupper((string) old('Priority', $announcement['Priority'] ?? '')) === 'URGENT' ? 'selected' : '' }}>Mendesak</option>
                        </x-select>
                    </div>
                    <div id="classTarget" class="sm:col-span-2 {{ $currentAudience === 'CLASS' ? '' : 'hidden' }}">
                        <x-select name="Audience_ID" label="Kelas">
                            <option value="">Pilih kelas</option>
                            @foreach($classes ?? [] as $class)
                                <option value="{{ $class['Class_ID'] }}" {{ $currentClass == $class['Class_ID'] ? 'selected' : '' }}>{{ $class['Class_Code'] ?? $class['Class_ID'] }} — {{ $class['Class_Name'] ?? 'Kelas' }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <x-input type="datetime-local" name="Start_At" label="Mulai Tayang (WIB)" value="{{ old('Start_At', $dateValue($announcement['Start_At'] ?? $announcement['Publish_Date'] ?? null)) }}" />
                        <x-input type="datetime-local" name="Expires_At" label="Berakhir (WIB)" required value="{{ old('Expires_At', $dateValue($announcement['Expires_At'] ?? $announcement['Expired_Date'] ?? null)) }}" />
                        <x-select name="Status" label="Status Publikasi" required>
                            <option value="PUBLISHED" {{ old('Status', $announcement['Status'] ?? '') === 'PUBLISHED' ? 'selected' : '' }}>Terpublikasi</option>
                            <option value="DRAFT" {{ old('Status', $announcement['Status'] ?? '') === 'DRAFT' ? 'selected' : '' }}>Draf</option>
                            <option value="INACTIVE" {{ old('Status', $announcement['Status'] ?? '') === 'INACTIVE' ? 'selected' : '' }}>Nonaktif</option>
                        </x-select>
                    </div>
                </x-form-section>
            </div>
            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-card>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const audience = document.getElementById('audienceType'), target = document.getElementById('classTarget');
    if (audience && target) audience.addEventListener('change', () => target.classList.toggle('hidden', audience.value !== 'CLASS'));
});
</script>
@endsection
