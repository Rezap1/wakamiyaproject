@extends('layouts.app')
@section('header', 'Preview QR Presensi Permanen')
@section('content')

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('attendance.qr.index') }}" class="text-sm font-bold text-slate-500 hover:text-sky-600 transition-colors flex items-center gap-1">
            &larr; Kembali ke Daftar QR
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('attendance.qr.print', $qr['QR_ID']) }}" target="_blank" class="px-4 py-2 bg-sky-100 text-sky-700 hover:bg-sky-200 font-bold rounded-xl text-sm transition-colors">
                🖨️ Cetak A4
            </a>
            <a href="{{ route('attendance.qr.pdf', $qr['QR_ID']) }}" class="px-4 py-2 bg-slate-800 text-white hover:bg-slate-900 font-bold rounded-xl text-sm transition-colors shadow-sm">
                ⬇️ Download PDF
            </a>
        </div>
    </div>

    <!-- Scaled Down Preview -->
    <div class="bg-slate-200 p-8 rounded-3xl flex justify-center shadow-inner overflow-hidden">
        <!-- Scale container for visual preview without breaking A4 ratio -->
        <div style="transform: scale(0.65); transform-origin: top center;" class="shadow-2xl ring-1 ring-slate-900/5">
            <iframe src="{{ route('attendance.qr.print', $qr['QR_ID']) }}" width="794" height="1123" frameborder="0" class="bg-white" scrolling="no"></iframe>
        </div>
    </div>
</div>

@endsection
