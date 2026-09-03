@extends('layouts.app')
@section('header', 'Edit Nilai')
@section('content')
@php
    $category = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
    $details = is_array($score['Parsed_Details'] ?? null) ? $score['Parsed_Details'] : (json_decode($score['Evaluation_Details'] ?? '', true) ?: []);
@endphp
<div class="max-w-4xl mx-auto space-y-6" x-data="scoreEditForm(@js($assessmentConfigs ?? []), @js($category), @js($details))">
    <x-page-header title="Edit Nilai" description="Kategori dan aspek berasal dari MASTER_ASSESSMENT_CONFIG." :breadcrumbs="['Dasbor' => route('dashboard'), 'Nilai' => route('scores.index'), 'Edit' => '#']" />
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form x-ref="form" action="{{ route('scores.update', $score['Score_ID'] ?? '') }}" method="POST" class="space-y-6 p-4 sm:p-6 md:p-8">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="mb-1 block text-xs font-bold text-slate-500">ID Nilai</label><input value="{{ $score['Score_ID'] ?? '-' }}" readonly class="w-full rounded-xl border-slate-200 bg-slate-100"></div>
                <div><label class="mb-1 block text-xs font-bold text-slate-500">Siswa</label><input value="{{ $score['Student_ID'] ?? '-' }}" readonly class="w-full rounded-xl border-slate-200 bg-slate-100"></div>
                <div><label class="mb-1 block text-xs font-bold text-slate-500">Kategori</label><select name="Assessment_Category" x-model="category" required class="w-full rounded-xl border-slate-200 bg-slate-50"><option value="">-- Pilih --</option>@foreach($assessmentConfigs as $config)<option value="{{ $config['Category_ID'] }}">{{ $config['Category_Name'] ?? $config['Category_ID'] }}</option>@endforeach</select></div>
            </div>
            <div x-show="activeAspects.length > 0" class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <template x-for="aspect in activeAspects" :key="aspect.id"><div><label class="mb-1 block text-sm font-semibold text-slate-700"><span x-text="aspect.label"></span> <span class="text-rose-600">*</span></label><select :name="aspect.id" required class="w-full rounded-xl border-slate-200 bg-slate-50"><option value="">-- Pilih Tingkat --</option><template x-for="level in [1,2,3,4,5]" :key="level"><option :value="level" x-text="level + ' - ' + levelLabels[level]"></option></template></select></div></template>
            </div>
            <div x-show="activeConfig && activeAspects.length === 0" class="border-t border-slate-100 pt-5"><label class="mb-1 block text-sm font-bold text-slate-700">Nilai (0–100) <span class="text-rose-600">*</span></label><input name="Score_Value" type="number" min="0" max="100" step="0.01" :value="{{ $score['Score_Value'] ?? ($score['Score'] ?? '') }}" :required="!!activeConfig && activeAspects.length === 0" class="w-full rounded-xl border-slate-200 bg-slate-50 text-lg font-bold"></div>
            <div class="border-t border-slate-100 pt-5"><label class="mb-1 block text-sm font-bold text-slate-700">Catatan (Opsional)</label><textarea name="Notes" maxlength="2000" rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50">{{ old('Notes', $details['notes'] ?? ($score['Remarks'] ?? '')) }}</textarea></div>
            <div class="flex justify-end border-t border-slate-100 pt-5"><button type="submit" :disabled="!activeConfig || submitting" class="min-h-12 rounded-xl bg-blue-600 px-6 font-bold text-white disabled:opacity-50"><span x-text="submitting ? 'Menyimpan...' : 'Perbarui Nilai'"></span></button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('scoreEditForm', (configs, category, details) => ({
    configs, category, details, submitting: false,
    levelLabels: {1:'Sangat Kurang',2:'Kurang',3:'Cukup',4:'Baik',5:'Sangat Baik'},
    get activeConfig() { return this.configs.find(c => String(c.Category_ID).toUpperCase() === String(this.category).toUpperCase()); },
    get activeAspects() { if (!this.activeConfig || !this.activeConfig.Aspects_JSON) return []; try { const a = JSON.parse(this.activeConfig.Aspects_JSON); return Array.isArray(a) ? a : []; } catch (e) { return []; } },
    init() { this.$nextTick(() => this.activeAspects.forEach(a => { const f = document.querySelector('[name="' + a.id + '"]'); if (f && this.details[a.id] !== undefined) f.value = this.details[a.id]; })); this.$refs.form?.addEventListener('submit', () => { this.submitting = true; }); }
})));
</script>
@endsection
