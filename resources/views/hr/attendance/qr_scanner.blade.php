@extends('layouts.app')
@section('header', 'Pemindai Presensi QR Pegawai')
@section('content')

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<div class="max-w-md mx-auto py-4 px-2 space-y-6">

    <!-- USER PROFILES & IDENTITY CARD -->
    <div class="bg-slate-900 text-white rounded-3xl p-6 shadow-xl relative overflow-hidden">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-2xl font-black text-emerald-400 shrink-0">
                👨‍💼
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Pegawai Terverifikasi</span>
                <h3 class="text-base font-black text-white leading-snug">{{ $employee['Full_Name'] ?? $user->Name ?? 'Pegawai WMS' }}</h3>
                <p class="text-xs text-slate-400 font-mono mt-0.5">ID: {{ $employee['Employee_ID'] ?? $user->User_ID ?? '-' }} &bull; {{ $employee['Employee_Number'] ?? '' }}</p>
            </div>
        </div>
    </div>

    <!-- SCANNER CAMERA CONTAINER -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl space-y-4 text-center">
        <div>
            <h2 class="text-lg font-black text-slate-800">Pemindai QR Code Presensi</h2>
            <p class="text-xs text-slate-500 mt-1">Arahkan kamera HP ke layar proyektor/TV kantor yang menampilkan Dynamic QR Code.</p>
        </div>

        <!-- CAMERA DISPLAY BOX -->
        <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 min-h-[260px] flex items-center justify-center">
            <div id="qr-reader" class="w-full"></div>
        </div>

        <!-- FEEDBACK ALERT BANNER -->
        <div id="scanResultBanner" class="hidden p-4 rounded-2xl text-xs font-bold text-left space-y-1">
            <div id="resultTitle" class="font-black text-sm uppercase"></div>
            <div id="resultMessage"></div>
        </div>

        <!-- FALLBACK MANUAL TOKEN ENTRY -->
        <div class="pt-4 border-t border-slate-100 space-y-2">
            <button id="toggleManualBtn" type="button" onclick="toggleManualInput()" class="text-xs font-bold text-blue-600 hover:text-blue-700 underline">
                Kamera Bermasalah? Input Token Manual
            </button>
            <div id="manualBox" class="hidden space-y-2 pt-2">
                <input type="text" id="manualTokenInput" class="w-full text-xs rounded-xl border-slate-200 p-2.5" placeholder="Tempelkan string token QR di sini...">
                <button type="button" onclick="submitManualToken()" class="w-full py-2 bg-slate-900 text-white rounded-xl font-bold text-xs">
                    Kirim Presensi Manual
                </button>
            </div>
        </div>
    </div>

    <!-- NAVIGATION LINK -->
    <div class="text-center">
        <a href="{{ route('dashboard.hr') }}" class="text-xs font-bold text-slate-500 hover:text-slate-700">
            &larr; Kembali ke Dasbor HR
        </a>
    </div>

</div>

<script>
    let html5QrcodeScanner = null;
    let isProcessing = false;

    function onScanSuccess(decodedText, decodedResult) {
        if (isProcessing) return;
        isProcessing = true;

        sendScanToken(decodedText);

        setTimeout(() => {
            isProcessing = false;
        }, 3000);
    }

    function sendScanToken(tokenString) {
        const banner = document.getElementById('scanResultBanner');
        const titleEl = document.getElementById('resultTitle');
        const msgEl = document.getElementById('resultMessage');

        banner.className = "p-4 rounded-2xl text-xs font-bold text-left space-y-1 bg-blue-50 text-blue-800 border border-blue-200 block";
        titleEl.innerText = "⏳ Memproses Presensi...";
        msgEl.innerText = "Sedang memverifikasi enkripsi token QR ke server...";

        fetch("{{ route('hr.attendance.qr.scan') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                token: tokenString,
                device_info: navigator.userAgent
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.data.status;
                if (status === 'PRESENT') {
                    banner.className = "p-4 rounded-2xl text-xs font-bold text-left space-y-1 bg-emerald-50 text-emerald-900 border-2 border-emerald-300 block";
                    titleEl.innerText = "✅ PRESENSI BERHASIL (TEPAT WAKTU)";
                } else {
                    banner.className = "p-4 rounded-2xl text-xs font-bold text-left space-y-1 bg-amber-50 text-amber-900 border-2 border-amber-300 block";
                    titleEl.innerText = "⚠️ PRESENSI TERLATIHAN (TERLAMBAT " + (data.data.late_minutes || 0) + " MENIT)";
                }
                msgEl.innerText = data.message + " Jam Check-In: " + data.data.check_in_time + " WIB";
            } else {
                banner.className = "p-4 rounded-2xl text-xs font-bold text-left space-y-1 bg-rose-50 text-rose-900 border-2 border-rose-300 block";
                titleEl.innerText = "❌ PRESENSI DITOLAK";
                msgEl.innerText = data.message || "Gagal memproses presensi.";
            }
        })
        .catch(err => {
            banner.className = "p-4 rounded-2xl text-xs font-bold text-left space-y-1 bg-rose-50 text-rose-900 border-2 border-rose-300 block";
            titleEl.innerText = "❌ KESALAHAN JARINGAN";
            msgEl.innerText = "Terjadi gangguan jaringan atau server. Silakan coba lagi.";
        });
    }

    function toggleManualInput() {
        const box = document.getElementById('manualBox');
        box.classList.toggle('hidden');
    }

    function submitManualToken() {
        const token = document.getElementById('manualTokenInput').value.trim();
        if (token) {
            sendScanToken(token);
        }
    }

    // Initialize Camera Scanner
    document.addEventListener("DOMContentLoaded", function() {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", 
            { fps: 10, qrbox: { width: 220, height: 220 } },
            /* verbose= */ false
        );
        html5QrcodeScanner.render(onScanSuccess);
    });
</script>
@endsection
