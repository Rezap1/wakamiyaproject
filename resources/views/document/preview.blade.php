<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pratinjau - {{ $document['Document_Number'] ?? 'Doc' }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background-color: #525659; font-family: 'Inter', sans-serif; display: flex; justify-content: center; padding: 2rem; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    </style>
</head>
<body>
    <div class="page">
        {!! $html ?? '<h1>Output Pratinjau</h1>' !!}
        
        <div style="margin-top: 50px; border-top: 1px dashed #ccc; padding-top: 20px;">
            <p><strong>Placeholder Kode QR:</strong> {{ $document['QRCode'] ?? '-' }}</p>
            <p><strong>Placeholder Tanda Tangan:</strong> {{ $document['Digital_Signature'] ?? '-' }}</p>
        </div>
    </div>
</body>
</html>



