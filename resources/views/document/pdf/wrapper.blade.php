<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen - {{ $document['Document_Number'] ?? 'Pratinjau' }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background-color: #525659; display: flex; flex-direction: column; align-items: center; padding: 2rem; font-family: 'Inter', sans-serif; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: 20mm; box-shadow: 0 4px 10px rgba(0,0,0,0.5); position: relative; margin-bottom: 2rem; overflow: hidden; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.05); font-weight: 900; white-space: nowrap; pointer-events: none; z-index: 1; user-select: none; text-align: center; line-height: 1; }
        .content { position: relative; z-index: 10; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 20px; }
        .footer { margin-top: 50px; border-top: 1px dashed #cbd5e1; padding-top: 20px; font-size: 10px; color: #64748b; display: flex; justify-content: space-between; align-items: flex-end;}
        .qr-box { text-align: right; }
        .qr-box img { width: 80px; height: 80px; margin-bottom: 5px;}
        
        @media print {
            body { background: none; padding: 0; }
            .page { width: 100%; box-shadow: none; padding: 0; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print bg-white p-4 rounded-xl shadow-md flex gap-4 mb-6 w-[210mm] justify-between items-center">
        <div>
            <h3 class="font-bold text-slate-800">Pratinjau Mesin PDF</h3>
            <p class="text-xs text-slate-500">Versi: {{ $document['Version'] ?? 'Draf' }} | Status: {{ $document['Signature_Status'] ?? 'Belum Ditandatangani' }}</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white font-bold text-sm rounded-lg hover:bg-emerald-700 shadow-sm">Cetak / Simpan PDF</button>
            <button onclick="window.close()" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-200">Tutup</button>
        </div>
    </div>

    <div class="page">
        @if($watermark)
            <div class="watermark">{{ $watermark }}</div>
        @endif
        
        <div class="content">
            <div class="header">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">WAKAMIYA</h1>
                    <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Management System</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold text-slate-800">{{ $document['Document_Type'] ?? 'Dokumen' }}</p>
                    <p class="text-xs text-slate-500">{{ $document['Document_Number'] ?? 'DOC-XXXX-XXXXXX' }}</p>
                </div>
            </div>

            <!-- Injected View -->
            {!! $html !!}

            <!-- Footer with Signature & QR -->
            <div class="footer">
                <div>
                    <p class="mb-4 text-xs font-bold text-slate-800">Tanda Tangan Sah</p>
                    @if(($document['Signature_Status'] ?? '') === 'Signed')
                        <div class="border border-blue-200 bg-blue-50 text-blue-800 px-4 py-2 rounded mb-2 w-max text-center">
                            <p class="text-[10px] uppercase font-bold tracking-widest">Ditandatangani Secara Digital</p>
                            <p class="font-mono text-xs">{{ $document['Digital_Signature'] ?? 'DSIG-XXXX' }}</p>
                        </div>
                    @else
                        <div class="border border-slate-200 bg-slate-50 text-slate-400 px-4 py-4 rounded mb-2 w-max text-center italic text-xs">
                            ( Tanpa Tanda Tangan Digital )
                        </div>
                    @endif
                    <p class="font-bold text-slate-800">{{ $document['Signature_By'] ?? 'Dibuat Oleh Sistem' }}</p>
                    <p>Dibuat Pada: {{ $document['Generated_At'] ?? now() }}</p>
                </div>
                <div class="qr-box">
                    @if(!empty($document['QR_Code']))
                        <!-- Placeholder for actual QR rendering library like simple-qrcode, using SVG mock for now -->
                        <div class="w-20 h-20 bg-slate-100 border border-slate-300 mx-auto flex items-center justify-center mb-1 text-[8px] text-slate-400 text-center font-bold">QR<br>PLACEHOLDER</div>
                        <p class="font-mono font-bold text-slate-600 text-[9px]">{{ $document['Verification_Code'] ?? 'VER-XXXX' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>



