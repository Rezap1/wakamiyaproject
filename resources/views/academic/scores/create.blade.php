@extends('layouts.app')
@section('header', 'Input Nilai & Penilaian Siswa')
@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="scoreEntryForm(@js($assessmentConfigs ?? []))">
    <x-page-header title="Input Nilai" description="Kategori dan aspek penilaian berasal dari MASTER_ASSESSMENT_CONFIG." :breadcrumbs="['Dasbor' => route('dashboard'), 'Nilai' => route('scores.index'), 'Tambah' => '#']" />
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form x-ref="form" action="{{ route('scores.store') }}" method="POST" class="space-y-6 p-4 sm:p-6 md:p-8">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-bold text-slate-700">Siswa <span class="text-rose-600">*</span></label>
                    <select name="Student_ID" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3">
                        <option value="">-- Pilih Siswa --</option>
                        @foreach($students as $student)
                            <option value="{{ $student['Student_ID'] }}">{{ $student['Full_Name'] ?? $student['Student_ID'] }} ({{ $student['Student_ID'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Penilaian <span class="text-rose-600">*</span></label>
                    <input type="date" name="Assessment_Date" required value="{{ old('Assessment_Date', now()->toDateString()) }}" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-bold text-slate-700">Kategori <span class="text-rose-600">*</span></label>
                <select name="Assessment_Category" x-model="category" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($assessmentConfigs as $config)
                        <option value="{{ $config['Category_ID'] }}">{{ $config['Category_Name'] ?? $config['Category_ID'] }}</option>
                    @endforeach
                </select>
                <p x-show="!activeConfig" class="mt-2 text-xs font-semibold text-amber-700">Konfigurasi kategori tidak tersedia; penyimpanan ditolak.</p>
            </div>
            <div x-show="activeAspects.length > 0" class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <template x-for="aspect in activeAspects" :key="aspect.id">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700"><span x-text="aspect.label"></span> <span class="text-rose-600">*</span></label>
                        <select :name="aspect.id" required class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3">
                            <option value="">-- Pilih Tingkat --</option>
                            <template x-for="level in [1,2,3,4,5]" :key="level"><option :value="level" x-text="level + ' - ' + levelLabels[level]"></option></template>
                        </select>
                    </div>
                </template>
            </div>
            <div x-show="activeConfig && activeAspects.length === 0" class="border-t border-slate-100 pt-5">
                <label class="mb-1.5 block text-sm font-bold text-slate-700">Nilai (0–100) <span class="text-rose-600">*</span></label>
                <input type="number" name="Score_Value" min="0" max="100" step="0.01" :required="!!activeConfig && activeAspects.length === 0" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-lg font-bold">
            </div>
            <div class="border-t border-slate-100 pt-5">
                <label class="mb-1.5 block text-sm font-bold text-slate-700">Catatan (Opsional)</label>
                <textarea name="Notes" rows="3" maxlength="2000" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"></textarea>
            </div>
            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button type="submit" :disabled="!activeConfig || submitting" class="min-h-12 rounded-xl bg-blue-600 px-6 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"><span x-text="submitting ? 'Menyimpan...' : 'Simpan Nilai'"></span></button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('scoreEntryForm', configs => ({
        configs, category: '', submitting: false,
        levelLabels: {1:'Sangat Kurang',2:'Kurang',3:'Cukup',4:'Baik',5:'Sangat Baik'},
        get activeConfig() { return this.configs.find(c => String(c.Category_ID).toUpperCase() === String(this.category).toUpperCase()); },
        get activeAspects() {
            if (!this.activeConfig || !this.activeConfig.Aspects_JSON) return [];
            try { const parsed = JSON.parse(this.activeConfig.Aspects_JSON); return Array.isArray(parsed) ? parsed : []; } catch (e) { return []; }
        },
        init() { this.$refs.form?.addEventListener('submit', () => { if (!this.submitting) this.submitting = true; }); }
    }));
});
</script>
@endsection
