<?php
$fileRoute = 'routes/web.php';
$routeContent = file_get_contents($fileRoute);

if (strpos($routeContent, 'PdfController') === false) {
    $useStatement = "use App\Http\Controllers\Core\PdfController;";
    $routeContent = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\n" . $useStatement, $routeContent, 1);

    $routes = <<<'EOT'
        // PDF Engine Routes
        Route::get('/document/verify/{code}', [PdfController::class, 'verify'])->name('document.verify')->withoutMiddleware(['auth']);
        Route::get('/document/{id}/pdf/preview', [PdfController::class, 'preview'])->name('pdf.preview');
        Route::post('/document/{id}/pdf/generate', [PdfController::class, 'generate'])->name('pdf.generate');
        Route::get('/document/{id}/pdf/download', [PdfController::class, 'download'])->name('pdf.download');
EOT;

    $routeContent = str_replace("Route::resource('employees', EmployeeController::class);", $routes . "\n        Route::resource('employees', EmployeeController::class);", $routeContent);
    file_put_contents($fileRoute, $routeContent);
    echo "PDF Routes added.\n";
} else {
    echo "PDF Routes already exist.\n";
}

$dirPdf = 'resources/views/document/pdf';
if(!is_dir($dirPdf)) mkdir($dirPdf, 0755, true);

// Wrapper for PDF Preview
$wrapper = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document - {{ $document['Document_Number'] ?? 'Preview' }}</title>
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
            <h3 class="font-bold text-slate-800">PDF Engine Preview</h3>
            <p class="text-xs text-slate-500">Version: {{ $document['Version'] ?? 'Draft' }} | Status: {{ $document['Signature_Status'] ?? 'Unsigned' }}</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white font-bold text-sm rounded-lg hover:bg-blue-700 shadow-sm">Print / Save PDF</button>
            <button onclick="window.close()" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-200">Close</button>
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
                    <p class="text-sm font-bold text-slate-800">{{ $document['Document_Type'] ?? 'Document' }}</p>
                    <p class="text-xs text-slate-500">{{ $document['Document_Number'] ?? 'DOC-XXXX-XXXXXX' }}</p>
                </div>
            </div>

            <!-- Injected View -->
            {!! $html !!}

            <!-- Footer with Signature & QR -->
            <div class="footer">
                <div>
                    <p class="mb-4 text-xs font-bold text-slate-800">Authorized Signature</p>
                    @if(($document['Signature_Status'] ?? '') === 'Signed')
                        <div class="border border-blue-200 bg-blue-50 text-blue-800 px-4 py-2 rounded mb-2 w-max text-center">
                            <p class="text-[10px] uppercase font-bold tracking-widest">Digitally Signed</p>
                            <p class="font-mono text-xs">{{ $document['Digital_Signature'] ?? 'DSIG-XXXX' }}</p>
                        </div>
                    @else
                        <div class="border border-slate-200 bg-slate-50 text-slate-400 px-4 py-4 rounded mb-2 w-max text-center italic text-xs">
                            ( No Digital Signature )
                        </div>
                    @endif
                    <p class="font-bold text-slate-800">{{ $document['Signature_By'] ?? 'System Generated' }}</p>
                    <p>Generated At: {{ $document['Generated_At'] ?? now() }}</p>
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
EOT;
file_put_contents("$dirPdf/wrapper.blade.php", $wrapper);

// 1. Default Template
$default = <<<'EOT'
<div class="my-8">
    <h2 class="text-lg font-bold text-slate-800 mb-4">{{ $document['Title'] ?? 'General Document' }}</h2>
    <div class="text-sm text-slate-700 leading-relaxed space-y-4">
        {{ $document['Message'] ?? 'This document was automatically generated by the WMS Document Engine. Content layout is set to default.' }}
    </div>
    
    <table class="w-full text-left text-xs mt-8 border-collapse">
        <tbody>
            <tr>
                <th class="py-2 border-b border-slate-200 w-1/3">Reference Module</th>
                <td class="py-2 border-b border-slate-200">{{ $document['Reference_Module'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="py-2 border-b border-slate-200">Reference ID</th>
                <td class="py-2 border-b border-slate-200">{{ $document['Reference_ID'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="py-2 border-b border-slate-200">Status</th>
                <td class="py-2 border-b border-slate-200">{{ $document['Status'] ?? '-' }}</td>
            </tr>
        </tbody>
    </table>
</div>
EOT;
file_put_contents("$dirPdf/default.blade.php", $default);

// Create stubs for specific ones so we don't crash
$files = ['salary-slip.blade.php', 'invoice.blade.php', 'receipt.blade.php', 'certificate.blade.php', 'coe.blade.php', 'visa.blade.php', 'assessment.blade.php', 'training.blade.php'];
foreach($files as $f) {
    if(!file_exists("$dirPdf/$f")) {
        $content = "<div class='my-8'><h2 class='text-lg font-bold text-slate-800 mb-4 uppercase'>".str_replace('.blade.php', '', $f)."</h2><p class='text-sm text-slate-600'>Layout khusus untuk dokumen ini akan dirender di sini.</p></div>";
        file_put_contents("$dirPdf/$f", $content);
    }
}

// Update the Document Show UI to link to the new PDF Engine
$showView = 'resources/views/document/show.blade.php';
$showContent = file_get_contents($showView);
if(strpos($showContent, 'PDF Engine Ready') === false) {
    $search = '<a href="{{ route(\'documents.preview\', $document[\'Document_ID\']) }}" target="_blank" class="block w-full text-center py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700">Preview Engine (HTML)</a>
                    <button disabled class="w-full py-2.5 bg-slate-100 text-slate-400 font-bold rounded-xl text-sm cursor-not-allowed">Download PDF (Soon)</button>';
    
    $replace = '<div class="flex gap-2">
                        <a href="{{ route(\'pdf.preview\', $document[\'Document_ID\']) }}" target="_blank" class="flex-1 text-center py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-sm hover:bg-slate-200 border border-slate-200">Preview Layout</a>
                        <a href="{{ route(\'pdf.download\', $document[\'Document_ID\']) }}" target="_blank" class="flex-1 text-center py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700 shadow-sm">Download PDF</a>
                    </div>
                    
                    <form action="{{ route(\'pdf.generate\', $document[\'Document_ID\']) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full py-2.5 mt-2 bg-emerald-50 text-emerald-700 font-bold rounded-xl text-sm hover:bg-emerald-100 border border-emerald-200 flex justify-center items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            Generate New Version & Sign
                        </button>
                    </form>';
                    
    $showContent = str_replace($search, $replace, $showContent);
    
    // Also update the metadata box to show PDF Info
    $metadataSearch = '<div class="flex justify-between pb-2">
                        <span class="text-slate-500">Status</span>
                        <span class="font-bold text-blue-600">{{ $document[\'Status\'] ?? \'Draft\' }}</span>
                    </div>';
    $metadataReplace = $metadataSearch . '
                    <div class="flex justify-between border-t border-slate-50 pt-2 pb-2 mt-2">
                        <span class="text-slate-500">Document Version</span>
                        <span class="font-bold text-emerald-600">{{ $document[\'Version\'] ?? \'Draft\' }}</span>
                    </div>
                    <div class="flex justify-between pb-2">
                        <span class="text-slate-500">Signature Status</span>
                        <span class="font-bold {{ ($document[\'Signature_Status\'] ?? \'\') == \'Signed\' ? \'text-emerald-600\' : \'text-slate-500\' }}">{{ $document[\'Signature_Status\'] ?? \'Unsigned\' }}</span>
                    </div>';
                    
    $showContent = str_replace($metadataSearch, $metadataReplace, $showContent);
    
    // Update right panel graphic
    $rightSearch = '<h4 class="font-bold text-slate-600">Document Engine Ready</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm">This document is linked to the DMS Engine. Click \'Preview Engine\' to render the actual HTML view.</p>';
    $rightReplace = '<h4 class="font-bold text-slate-600">Enterprise PDF Engine Ready</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm mb-6">Document Version <b>{{ $document[\'Version\'] ?? \'Draft\' }}</b></p>
                <a href="{{ route(\'pdf.preview\', $document[\'Document_ID\']) }}" target="_blank" class="px-6 py-3 bg-white border border-slate-300 text-slate-700 font-bold rounded-xl shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Open PDF Preview
                </a>';
                
    $showContent = str_replace($rightSearch, $rightReplace, $showContent);

    file_put_contents($showView, $showContent);
    echo "Show View updated with PDF actions.\n";
}

echo "PDF Routes and Views created.\n";
?>
