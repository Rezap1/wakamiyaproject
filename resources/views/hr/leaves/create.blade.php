@extends('layouts.app')
@section('header', 'Form Pengajuan Cuti Baru')
@section('content')

<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl">
        <div class="border-b border-slate-100 pb-4 mb-6">
            <h2 class="text-lg font-black text-slate-800">Formulir Pengajuan Cuti / Izin HR</h2>
            <p class="text-xs text-slate-500 mt-1">Identitas pemohon secara otomatis diturunkan dari akun yang sedang login di server.</p>
        </div>

        <form action="{{ route('hr.leaves.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tipe Cuti / Izin <span class="text-rose-500">*</span></label>
                <select name="Leave_Type" class="w-full text-xs font-bold rounded-xl border-slate-200 p-3 bg-slate-50 focus:bg-white transition-colors" required>
                    <option value="CUTI_TAHUNAN">Cuti Tahunan</option>
                    <option value="SAKIT">Izin Sakit</option>
                    <option value="IZIN_RESMI">Izin Resmi / Kepentingan Keluarga</option>
                    <option value="CUTI_MELAHIRKAN">Cuti Melahirkan</option>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tanggal Mulai <span class="text-rose-500">*</span></label>
                    <input type="date" name="Start_Date" class="w-full text-xs font-bold rounded-xl border-slate-200 p-3 bg-slate-50 focus:bg-white" value="{{ date('Y-m-d') }}" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tanggal Selesai <span class="text-rose-500">*</span></label>
                    <input type="date" name="End_Date" class="w-full text-xs font-bold rounded-xl border-slate-200 p-3 bg-slate-50 focus:bg-white" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Alasan & Catatan Pengajuan <span class="text-rose-500">*</span></label>
                <textarea name="Reason" rows="3" class="w-full text-xs text-slate-800 rounded-xl border-slate-200 p-3 bg-slate-50 focus:bg-white" placeholder="Jelaskan alasan pengajuan cuti/izin..." required></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('hr.leaves.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-md transition-colors">
                    🚀 Kirim Pengajuan Cuti
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
