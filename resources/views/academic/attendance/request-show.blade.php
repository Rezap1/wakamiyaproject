@extends('layouts.app')

@section('header', 'Detail Pengajuan Presensi')

@section('content')
@php
    $evidenceInlineUrl = route('academic.attendance.requests.evidence', ['id' => $request['Request_ID'], 'inline' => 1]);
    $evidenceDownloadUrl = route('academic.attendance.requests.evidence', $request['Request_ID']);
@endphp
<div class="max-w-4xl mx-auto w-full">
    
    @if(session('error'))
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 mb-6 text-sm font-bold">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Informasi Detail -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="bg-slate-50 p-6 border-b border-slate-200">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-black text-indigo-700 uppercase tracking-widest">Detail Siswa & Kelas</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border 
                        {{ $request['Status'] === 'PENDING' ? 'bg-amber-50 text-amber-600 border-amber-200' : '' }}
                        {{ $request['Status'] === 'APPROVED' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : '' }}
                        {{ $request['Status'] === 'REJECTED' ? 'bg-rose-50 text-rose-600 border-rose-200' : '' }}">
                        {{ $request['Status'] }}
                    </span>
                </div>
                <h3 class="text-xl font-black text-slate-800">{{ $request['Student_Name'] }}</h3>
                <p class="text-sm text-slate-500 font-semibold">{{ $request['Student_ID'] }}</p>
            </div>
            <div class="p-6 space-y-4 flex-grow">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Kelas & Program</span>
                    <p class="text-sm font-semibold text-slate-700">{{ $request['Class_Name'] }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Tanggal & Materi</span>
                    <p class="text-sm font-semibold text-slate-700">{{ $request['Attendance_Date'] }} — {{ $request['Subject_Name'] }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Tipe Pengajuan</span>
                    <p class="text-sm font-black text-indigo-700">{{ $request['Request_Type'] }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Alasan</span>
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm text-slate-700 font-medium">
                        {{ $request['Reason'] }}
                    </div>
                </div>
                @if(!empty($request['Academic_Notes']))
                    <div>
                        <span class="text-[10px] font-bold text-rose-400 uppercase">Catatan Academic ({{ $request['Reviewed_By'] }})</span>
                        <div class="bg-rose-50 border border-rose-100 rounded-xl p-3 text-sm text-rose-800 font-semibold">
                            {{ $request['Academic_Notes'] }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bukti & Keputusan -->
        <div class="flex flex-col space-y-6">
            <!-- Bukti Gambar -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 p-4 border-b border-slate-200 flex justify-between items-center">
                    <h4 class="text-sm font-black text-slate-800">Bukti Lampiran</h4>
                    <div class="flex items-center gap-2">
                        <a href="{{ $evidenceDownloadUrl }}" class="text-[10px] font-bold bg-slate-900 text-white hover:bg-slate-800 px-3 py-1.5 rounded-lg transition-colors">Download</a>
                        <button type="button" onclick="document.getElementById('evidence-modal').classList.remove('hidden')" class="text-[10px] font-bold bg-white text-slate-700 border border-slate-200 hover:bg-slate-100 px-3 py-1.5 rounded-lg transition-colors">Lihat Penuh</button>
                    </div>
                </div>
                <div class="p-4 flex justify-center bg-slate-100">
                    <img src="{{ $evidenceInlineUrl }}" alt="Bukti" class="max-h-64 object-contain rounded-xl shadow-sm border border-slate-200">
                </div>
            </div>

            <!-- Keputusan (Hanya jika PENDING) -->
            @if($request['Status'] === 'PENDING')
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                    <h4 class="text-sm font-black text-slate-800 mb-4">Keputusan Academic</h4>
                    
                    <form id="approval-form" method="POST" action="">
                        @csrf
                        <textarea name="Academic_Notes" rows="2" placeholder="Catatan opsional (wajib jika ditolak)..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none resize-none mb-4" id="academic_notes"></textarea>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <!-- APPROVE SAKIT -->
                            <button type="button" onclick="submitDecision('{{ route('academic.attendance.requests.approve', $request['Request_ID']) }}', 'SAKIT')" class="bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 font-bold py-2.5 rounded-xl transition-colors text-xs flex items-center justify-center gap-1">
                                <span>🤒</span> Approve Sakit
                            </button>

                            <!-- APPROVE IZIN -->
                            <button type="button" onclick="submitDecision('{{ route('academic.attendance.requests.approve', $request['Request_ID']) }}', 'IZIN')" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold py-2.5 rounded-xl transition-colors text-xs flex items-center justify-center gap-1">
                                <span>📝</span> Approve Izin
                            </button>

                            <!-- REJECT (ALPA) -->
                            <button type="button" onclick="submitReject('{{ route('academic.attendance.requests.reject', $request['Request_ID']) }}')" class="col-span-2 mt-1 bg-rose-600 hover:bg-rose-700 text-white font-extrabold py-3 rounded-xl shadow-lg shadow-rose-500/30 transition-colors text-sm">
                                Tolak Pengajuan (Alpa)
                            </button>
                        </div>
                        
                        <input type="hidden" name="Approve_As" id="approve_as_input" value="">
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function submitDecision(actionUrl, approveType) {
        if (!confirm('Anda yakin ingin menyetujui pengajuan ini sebagai ' + approveType + '?')) return;
        const form = document.getElementById('approval-form');
        form.action = actionUrl;
        document.getElementById('approve_as_input').value = approveType;
        form.submit();
    }

    function submitReject(actionUrl) {
        const notes = document.getElementById('academic_notes').value;
        if (!notes || notes.trim() === '') {
            alert('Alasan penolakan (Catatan Academic) wajib diisi.');
            document.getElementById('academic_notes').focus();
            return;
        }
        if (!confirm('Anda yakin ingin MENOLAK pengajuan ini? Status presensi akan menjadi ALPA.')) return;
        
        const form = document.getElementById('approval-form');
        form.action = actionUrl;
        form.submit();
    }
</script>

<!-- Evidence Modal -->
<div id="evidence-modal" class="fixed inset-0 z-[100] hidden bg-slate-900/90 backdrop-blur-sm flex flex-col">
    <div class="flex justify-between items-center p-4 text-white">
        <h3 class="font-bold">Bukti Lampiran</h3>
        <button onclick="document.getElementById('evidence-modal').classList.add('hidden')" class="bg-white/10 hover:bg-white/20 text-white rounded-lg px-4 py-2 text-sm font-bold flex items-center gap-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali
        </button>
    </div>
    <div class="flex-1 overflow-auto p-4 flex justify-center items-center">
        <img src="{{ $evidenceInlineUrl }}" alt="Bukti Lampiran" class="max-w-full max-h-full object-contain rounded-xl shadow-2xl">
    </div>
</div>
@endsection
