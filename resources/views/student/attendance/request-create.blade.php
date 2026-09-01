@extends('layouts.app')

@section('header', 'Form Pengajuan Presensi')

@section('content')
<div class="max-w-xl mx-auto w-full">
    
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 p-6 border-b border-slate-200">
            <h3 class="text-xl font-black text-slate-800">Ajukan Ketidakhadiran</h3>
            <p class="text-sm text-slate-500 mt-1">Isi form di bawah ini untuk mengajukan sakit atau izin.</p>
        </div>

        <form action="{{ route('student.attendance.requests.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5" onsubmit="document.getElementById('submit-btn').disabled = true; document.getElementById('submit-btn').innerHTML = 'Mengirim...';">
            @csrf

            <!-- Tanggal -->
            <div>
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Tanggal Presensi <span class="text-rose-500">*</span></label>
                <input type="date" name="Attendance_Date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none" value="{{ now()->toDateString() }}">
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mt-3 mb-2">Sampai Tanggal (opsional)</label>
                <input type="date" name="End_Date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none" value="{{ old('End_Date') }}">
                <p class="text-xs text-slate-500 mt-2">Untuk izin beberapa hari, pilih tanggal akhir. Setiap tanggal akan dicatat dan diproses secara terpisah.</p>
            </div>

            <!-- Target attendance -->
            <div>
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Target Presensi <span class="text-rose-500">*</span></label>
                @php
                    $dayMap = [
                        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
                    ];
                @endphp
                <select name="Schedule_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none">
                    <option value="">Absensi Kelas / QR ({{ $student['Class_ID'] }})</option>
                    @foreach($schedules as $s)
                        <option value="{{ $s['Schedule_ID'] }}">{{ $dayMap[$s['Day'] ?? $s['Day_Of_Week'] ?? ''] ?? $s['Day'] ?? $s['Day_Of_Week'] }} ({{ $s['Start_Time'] }} - {{ $s['End_Time'] }}) - {{ $s['Subject_Name'] ?? $s['Subject_ID'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-2">Pilih Absensi Kelas / QR untuk pengajuan umum kelas. Jadwal hanya diperlukan untuk pengajuan berbasis jadwal.</p>
            </div>

            <!-- Tipe Pengajuan -->
            <div>
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Tipe Pengajuan <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="Request_Type" value="SAKIT" required class="peer sr-only">
                        <div class="text-center p-4 rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 transition-all">
                            <div class="text-2xl mb-1">🤒</div>
                            <div class="font-bold text-slate-700 peer-checked:text-sky-700">Sakit</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="Request_Type" value="IZIN" required class="peer sr-only">
                        <div class="text-center p-4 rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 transition-all">
                            <div class="text-2xl mb-1">📝</div>
                            <div class="font-bold text-slate-700 peer-checked:text-sky-700">Izin</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Alasan -->
            <div>
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Alasan <span class="text-rose-500">*</span></label>
                <textarea name="Reason" required rows="3" placeholder="Jelaskan alasan secara singkat..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none resize-none"></textarea>
            </div>

            <!-- Evidence -->
            <div>
                <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Bukti Gambar (Maks 5MB) <span class="text-rose-500">*</span></label>
                <input type="file" name="Evidence" required accept="image/jpeg,image/png,image/webp" id="evidence-input" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                <div class="mt-3 hidden" id="preview-container">
                    <img id="image-preview" src="#" alt="Preview" class="h-32 object-contain rounded-xl border border-slate-200 shadow-sm">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <button type="submit" id="submit-btn" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-extrabold py-3.5 px-4 rounded-xl shadow-lg shadow-sky-600/30 transition-all">
                    Kirim Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('evidence-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Check size max 5MB
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran file maksimal 5MB');
                this.value = '';
                document.getElementById('preview-container').classList.add('hidden');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('preview-container').classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });
</script>
@endsection
