@extends('layouts.app')
@section('header', 'Detail Dokumen')
@section('content')
<div class="space-y-6">
    <x-page-header title="Detail Dokumen" description="Lihat dan kelola dokumen." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Dokumen' => route('documents.index'), 'Detail' => '#']" />
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Metadata -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Informasi Dokumen</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Nomor Dokumen</span>
                        <span class="font-bold text-slate-800">{{ $document['Document_Number'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Tipe</span>
                        <span class="font-bold text-slate-800">{{ $document['Document_Type'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Referensi</span>
                        <span class="font-bold text-slate-800">{{ $document['Reference_Module'] ?? '-' }} / {{ $document['Reference_ID'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Dibuat Oleh</span>
                        <span class="font-bold text-slate-800">{{ $document['Generated_By'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between pb-2">
                        <span class="text-slate-500">Status</span>
                        <span class="font-bold text-blue-600">{{ $document['Status'] ?? 'Draf' }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-50 pt-2 pb-2 mt-2">
                        <span class="text-slate-500">Versi Dokumen</span>
                        <span class="font-bold text-emerald-600">{{ $document['Version'] ?? 'Draf' }}</span>
                    </div>
                    <div class="flex justify-between pb-2">
                        <span class="text-slate-500">Status Tanda Tangan</span>
                        <span class="font-bold {{ ($document['Signature_Status'] ?? '') == 'Signed' ? 'text-emerald-600' : 'text-slate-500' }}">{{ $document['Signature_Status'] == 'Signed' ? 'Ditandatangani' : 'Belum Ditandatangani' }}</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Keamanan</h3>
                <div class="space-y-3 text-sm">
                    <div class="bg-slate-50 p-3 rounded text-center">
                        <p class="text-xs font-bold text-slate-400">VERIFIKASI QR</p>
                        <p class="font-mono text-slate-800 mt-1">{{ $document['QRCode'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded text-center">
                        <p class="text-xs font-bold text-slate-400">TANDA TANGAN DIGITAL</p>
                        <p class="font-mono text-slate-800 mt-1">{{ $document['Digital_Signature'] ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Aksi</h3>
                <div class="space-y-3">
                    <div class="flex gap-2">
                        <a href="{{ route('pdf.preview', $document['Document_ID']) }}" target="_blank" class="flex-1 text-center py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-sm hover:bg-slate-200 border border-slate-200">Pratinjau Tata Letak</a>
                        <a href="{{ route('pdf.download', $document['Document_ID']) }}" target="_blank" class="flex-1 text-center py-2.5 bg-emerald-600 text-white font-bold rounded-xl text-sm hover:bg-emerald-700 shadow-sm">Unduh PDF</a>
                    </div>
                    
                    <form action="{{ route('pdf.generate', $document['Document_ID']) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full py-2.5 mt-2 bg-emerald-50 text-emerald-700 font-bold rounded-xl text-sm hover:bg-emerald-100 border border-emerald-200 flex justify-center items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            Buat Versi Baru & Tanda Tangani
                        </button>
                    </form>
                    
                    @if(($document['Status'] ?? '') !== 'Archived')
                    <form action="{{ route('documents.destroy', $document['Document_ID']) }}" method="POST" onsubmit="return confirm('Arsipkan dokumen ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full py-2.5 mt-2 bg-rose-50 text-rose-600 font-bold rounded-xl text-sm hover:bg-rose-100">Arsipkan Dokumen</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Right: Small Preview Window -->
        <div class="lg:col-span-2">
            <div class="bg-slate-200 rounded-2xl border border-slate-300 h-full flex flex-col items-center justify-center p-8 text-center min-h-[500px]">
                <svg class="w-16 h-16 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h4 class="font-bold text-slate-600">Mesin PDF Enterprise Siap</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm mb-6">Versi Dokumen <b>{{ $document['Version'] ?? 'Draf' }}</b></p>
                <a href="{{ route('pdf.preview', $document['Document_ID']) }}" target="_blank" class="px-6 py-3 bg-white border border-slate-300 text-slate-700 font-bold rounded-xl shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Buka Pratinjau PDF
                </a>
            </div>
        </div>
    </div>
</div>
@endsection



