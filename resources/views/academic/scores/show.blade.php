@extends('layouts.app')
@section('header', 'Detail Nilai')
@section('content')
@php
    $rawScore = $score['Score_Value'] ?? ($score['Score'] ?? 0);
    $result = \App\Helpers\GradeHelper::calculate($rawScore);
    $statusText = $result['pass'] ? 'PASS (LULUS)' : 'FAIL (TIDAK LULUS)';
    $category = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
    $categoryLabel = $assessmentConfig['Category_Name'] ?? ($category ?: 'Tidak dikategorikan');
    $details = is_array($score['Parsed_Details'] ?? null) ? $score['Parsed_Details'] : (json_decode($score['Evaluation_Details'] ?? '', true) ?: []);
    $aspects = $assessmentConfig && !empty($assessmentConfig['Aspects_JSON']) ? json_decode($assessmentConfig['Aspects_JSON'], true) : [];
    $aspectMap = collect(is_array($aspects) ? $aspects : [])->pluck('label', 'id')->toArray();
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout title="{{ $score['Student_Name'] ?? 'Data siswa tidak ditemukan' }}" description="Assessment: {{ $score['Assessment_Title'] ?? 'Penilaian tidak ditemukan' }}" status="{{ $statusText }}" badgeColor="{{ $result['pass'] ? 'green' : 'red' }}" :breadcrumbs="['Dasbor' => route('dashboard'), 'Nilai' => route('scores.index'), 'Detail' => '#']">
        <x-slot:actions><x-universal.action-button action="edit" url="{{ route('scores.edit', $score['Score_ID']) }}" /><x-universal.action-button action="delete" url="{{ route('scores.destroy', $score['Score_ID']) }}" /></x-slot:actions>
        <x-slot:information>
            <div class="space-y-8">
                <div class="rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white"><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Kategori Assessment</span><h2 class="mt-1 text-xl font-bold">{{ $categoryLabel }}</h2><p class="mt-1 text-xs text-slate-400">Tanggal: {{ !empty($score['Created_At']) ? \Carbon\Carbon::parse($score['Created_At'])->format('d M Y') : '-' }}</p><p class="mt-4 text-4xl font-black">{{ $rawScore }} <span class="text-sm font-normal text-slate-400">/ 100</span></p></div>
                <div>
                    <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Rincian Metrik Evaluasi</h3>
                    @php $rendered = 0; @endphp
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        @foreach($details as $key => $value)
                            @if(!in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true) && isset($aspectMap[$key]))
                                @php $rendered++; @endphp
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-bold uppercase text-slate-500">{{ $aspectMap[$key] }}</p><p class="mt-1 text-lg font-bold text-slate-800">{{ $value }}</p></div>
                            @endif
                        @endforeach
                    </div>
                    @if($rendered === 0)<p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Tidak ada rincian aspek yang tersimpan.</p>@endif
                </div>
                <div><h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Catatan & Umpan Balik</h3><div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm italic text-slate-800">{{ $score['Remarks'] ?? ($score['Notes'] ?? ($details['notes'] ?? 'Tidak ada catatan evaluasi khusus.')) }}</div></div>
            </div>
        </x-slot:information>
        <x-slot:audit><div class="space-y-4"><h3 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Audit Trail</h3><div class="grid grid-cols-1 gap-4 sm:grid-cols-2"><div><p class="text-xs font-bold text-slate-400">Waktu Pencatatan</p><p>{{ $score['Created_At'] ?? '-' }}</p></div><div><p class="text-xs font-bold text-slate-400">Waktu Diperbarui</p><p>{{ $score['Updated_At'] ?? '-' }}</p></div></div></div></x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
