@extends('layouts.app')
@section('header', 'Detail Persetujuan')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header title="Detail Persetujuan" description="Tinjau permintaan sebelum mengambil tindakan." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Persetujuan' => route('approvals.index'), 'Detail' => '#']" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="font-bold text-slate-800">Informasi Permintaan</h3>
                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-xs font-bold rounded uppercase tracking-widest">{{ $approval['Status'] ?? 'Menunggu' }}</span>
                </div>
                
                <table class="w-full text-sm">
                    <tbody>
                        <tr>
                            <td class="py-2 text-slate-500 w-1/3">Tipe Referensi</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Reference_Type'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">ID Referensi</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Reference_ID'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Pemohon</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Requester_ID'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Prioritas</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Priority'] ?? 'Normal' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Diajukan Pada</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Submitted_At'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Approval History -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-6">Riwayat Persetujuan</h3>
                <div class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
                    @forelse($history as $h)
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-slate-100 text-slate-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800 text-sm">{{ $h['Action'] ?? 'Pembaruan' }}</span>
                                <span class="text-[10px] font-bold text-slate-400">{{ \Carbon\Carbon::parse($h['Created_At'])->format('d M H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Status berubah dari <span class="font-semibold">{{ $h['Old_Status'] ?? '-' }}</span> menjadi <span class="font-semibold">{{ $h['New_Status'] ?? '-' }}</span></p>
                            <p class="text-xs text-slate-500 mt-1">Oleh: <span class="font-semibold">{{ $h['Performed_By'] ?? '-' }}</span></p>
                            @if(!empty($h['Remarks']))
                                <div class="mt-2 p-2 bg-slate-50 rounded text-xs italic text-slate-600 border border-slate-200">"{{ $h['Remarks'] }}"</div>
                            @endif
                        </div>
                    </div>
                    @empty
                        <p class="text-sm text-slate-500 text-center relative z-10 bg-white inline-block px-4 mx-auto w-max left-1/2 -translate-x-1/2">Tidak ada riwayat yang dicatat.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar / Actions -->
        <div class="space-y-6">
            @if(($approval['Status'] ?? '') === 'Waiting Approval' && ($approval['Current_Approver'] ?? '') === (session('role') ?? 'GUEST'))
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4">Tindakan Diperlukan</h3>
                
                <form action="{{ route('approvals.approve', $approval['Approval_ID']) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan (Opsional)</label>
                        <textarea name="remarks" rows="2" class="w-full text-sm rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500" placeholder="Tambahkan catatan persetujuan..."></textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 font-bold rounded-xl text-sm border border-emerald-200 transition-colors shadow-sm" onsubmit="return confirm('Setujui permintaan ini?');">Setujui Permintaan</button>
                </form>

                <form action="{{ route('approvals.reject', $approval['Approval_ID']) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Penolakan</label>
                        <textarea name="remarks" rows="2" class="w-full text-sm rounded-lg border-slate-200 focus:border-red-500 focus:ring-red-500" placeholder="Mengapa ini ditolak?" required></textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-white text-red-600 hover:bg-red-50 font-bold rounded-xl text-sm border border-red-200 transition-colors" onsubmit="return confirm('Tolak permintaan ini?');">Tolak Permintaan</button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection



