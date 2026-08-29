@extends('layouts.app')

@section('header', 'Ubah Templat Dokumen')

@section('content')
@php
    $templateId = $template['Template_ID'] ?? '';
@endphp

<div class="space-y-6">
    <x-page-header
        title="Ubah Templat Dokumen"
        description="Perbarui konfigurasi dan konten HTML templat."
        :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Dokumen' => route('documents.index'), 'Templat' => route('templates.index'), 'Ubah' => '#']"
    />

    <form action="{{ route('templates.update', $templateId) }}" method="POST" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Nama Templat</label>
                <input type="text" name="Template_Name" value="{{ old('Template_Name', $template['Template_Name'] ?? '') }}" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                @error('Template_Name')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Kode Templat</label>
                <input type="text" name="Template_Code" value="{{ old('Template_Code', $template['Template_Code'] ?? '') }}" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                @error('Template_Code')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tipe Dokumen</label>
                <input type="text" name="Document_Type" value="{{ old('Document_Type', $template['Document_Type'] ?? 'Custom Document') }}" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                @error('Document_Type')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Status</label>
                @php $status = old('Status', $template['Status'] ?? 'Active'); @endphp
                <select name="Status" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="Active" @selected($status === 'Active')>Active</option>
                    <option value="Inactive" @selected($status === 'Inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Deskripsi</label>
            <textarea name="Description" rows="3" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('Description', $template['Description'] ?? '') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Konten HTML</label>
            <textarea name="Template_Content" rows="12" class="w-full rounded-xl border-slate-200 text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500">{{ old('Template_Content', $template['Template_Content'] ?? '') }}</textarea>
            @error('Template_Content')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col sm:flex-row gap-3 sm:justify-end pt-2">
            <a href="{{ route('templates.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-center">Batal</a>
            <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 shadow-sm">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
