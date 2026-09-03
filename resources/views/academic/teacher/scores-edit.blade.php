@extends('layouts.app')
@section('header', 'Edit Penilaian')
@section('content')
@php
    $details = is_array($score['Parsed_Details'] ?? null) ? $score['Parsed_Details'] : (json_decode($score['Evaluation_Details'] ?? '', true) ?: []);
    $currentCategory = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
    $currentDate = $score['Assessment_Date'] ?? substr((string) ($score['Created_At'] ?? now()->toDateString()), 0, 10);
@endphp
<div class="max-w-3xl mx-auto space-y-6" x-data="teacherScoreEdit(@js($currentCategory), @js($details))">
    <x-page-header title="Edit Penilaian" description="Perbarui penilaian siswa yang berada dalam scope pengajaran Anda." :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Penilaian' => route('teacher.workspace.scores'), 'Edit' => '#']" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form action="{{ route('teacher.workspace.scores.update', $score['Score_ID']) }}" method="POST" class="space-y-6 p-4 sm:p-6 md:p-8">
            @csrf
            @method('PUT')

            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Siswa</p>
                <p class="mt-1 font-extrabold text-slate-800">{{ $score['Student_ID'] }}</p>
                <p class="text-xs text-slate-500">Identitas siswa dikunci oleh server untuk mencegah perubahan lintas scope.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="edit-date" class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Penilaian <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <input id="edit-date" type="date" name="Date" value="{{ old('Date', $currentDate) }}" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold outline-none focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100">
                </div>
                <div>
                    <label for="edit-category" class="mb-1.5 block text-sm font-bold text-slate-700">Kategori Penilaian <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <select id="edit-category" name="Assessment_Category" x-model="category" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold outline-none focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100">
                        @foreach($assessmentConfigs as $config)
                            <option value="{{ $config['Category_ID'] }}">{{ strtoupper($config['Category_Name']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-4 border-t border-slate-100 pt-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Aspek Penilaian</h2>
                <template x-for="aspect in activeAspects" :key="aspect.id">
                    <div>
                        <label :for="'aspect-' + aspect.id" class="mb-1.5 block text-sm font-semibold text-slate-700"><span x-text="aspect.label"></span> <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <select :id="'aspect-' + aspect.id" :name="aspect.id" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold outline-none focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100">
                            <option value="">-- Pilih Tingkat --</option>
                            <template x-for="level in [1, 2, 3, 4, 5]" :key="level">
                                <option :value="level" x-text="level + ' - ' + levelLabels[level]"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <p x-show="activeAspects.length === 0" class="rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">Konfigurasi aspek tidak tersedia. Penyimpanan dinonaktifkan.</p>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label for="edit-notes" class="mb-1.5 block text-sm font-bold text-slate-700">Catatan (Opsional)</label>
                <textarea id="edit-notes" name="Notes" rows="3" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 outline-none focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100">{{ old('Notes', $details['notes'] ?? '') }}</textarea>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('teacher.workspace.scores') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl px-5 py-2.5 font-bold text-slate-600 hover:bg-slate-100">Batal</a>
                <button type="submit" x-bind:disabled="activeAspects.length === 0" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-blue-600 px-6 py-2.5 font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('teacherScoreEdit', (category, details) => ({
        category,
        details,
        configs: @json($assessmentConfigs ?? []),
        levelLabels: {1: 'Sangat Kurang', 2: 'Kurang', 3: 'Cukup', 4: 'Baik', 5: 'Sangat Baik'},
        get activeConfig() { return this.configs.find(config => String(config.Category_ID).toUpperCase() === String(this.category).toUpperCase()); },
        get activeAspects() {
            if (!this.activeConfig || !this.activeConfig.Aspects_JSON) return [];
            try { return JSON.parse(this.activeConfig.Aspects_JSON); } catch (error) { return []; }
        },
        init() {
            this.$nextTick(() => {
                this.activeAspects.forEach(aspect => {
                    const field = document.getElementById('aspect-' + aspect.id);
                    if (field && this.details[aspect.id] !== undefined) field.value = this.details[aspect.id];
                });
            });
        }
    }));
});
</script>
@endsection
