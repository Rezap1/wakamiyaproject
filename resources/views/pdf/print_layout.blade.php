<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle ?? 'Report' }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
            padding: 0;
            background: #fff;
        }
        
        /* Header Enterprise */
        .header-container {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: table;
        }
        .header-logo {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
            text-align: center;
        }
        .header-logo img {
            max-width: 70px;
            height: auto;
        }
        .header-text {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding-left: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
            text-decoration: underline;
            letter-spacing: 1px;
        }
        .company-address {
            font-size: 11px;
            font-style: italic;
            margin: 2px 0;
            color: #444;
        }
        
        /* Metadata */
        .metadata-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .report-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #1f2937;
        }
        .metadata-table {
            width: 100%;
            border: none;
            font-size: 10px;
        }
        .metadata-table td {
            padding: 2px;
            vertical-align: top;
        }
        
        /* Table Content */
        .enterprise-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .enterprise-table th {
            background-color: #e5e7eb;
            color: #111827;
            font-weight: bold;
            padding: 6px 4px;
            border: 1px solid #9ca3af;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        .enterprise-table td {
            padding: 5px 4px;
            border: 1px solid #d1d5db;
            font-size: 10px;
            vertical-align: top;
        }
        .enterprise-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        /* Executive Summary */
        .exec-summary {
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #d1d5db;
            background-color: #f3f4f6;
            padding: 10px;
        }
        .exec-summary table {
            width: 100%;
            font-size: 10px;
            font-weight: bold;
        }
        
        /* Signatures */
        .signature-container {
            width: 100%;
            margin-top: 30px;
            page-break-inside: avoid;
            font-size: 10px;
            text-align: center;
        }
        .signature-box {
            display: inline-block;
            width: 30%;
            vertical-align: top;
        }
        .signature-space {
            height: 60px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto;
            padding-top: 5px;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        
        /* Print Styles */
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .enterprise-table thead { display: table-header-group; }
            .enterprise-table tr { page-break-inside: avoid; }
        }
        
        .print-btn {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body onload="window.print()">
    
    <div class="no-print" style="text-align: center; padding: 10px; background: #fef3c7; margin-bottom: 20px; border-bottom: 1px solid #d97706;">
        If the print dialog doesn't open automatically, click here: 
        <button class="print-btn" onclick="window.print()">Print Report</button>
    </div>

    <!-- Header -->
    <div class="header-container">
        <div class="header-logo">
            @php
                $imagePath = public_path('img/logo.png.jpeg');
                if (file_exists($imagePath)) {
                    $imageData = base64_encode(file_get_contents($imagePath));
                    echo '<img src="data:image/jpeg;base64,'.$imageData.'" alt="Logo">';
                }
            @endphp
        </div>
        <div class="header-text">
            <h1 class="company-name">PT. WAKAMIYA MANDIRI SEJAHTERA</h1>
            <p class="company-address">Perum Graha Samolo Indah Blok B1 No 22, Desa Babakan Caringin Kecamatan Karangtengah</p>
            <p class="company-address">Cianjur, 43281 | Email: info@lpkwakamiya.com | Web: www.lpkwakamiya.com | Telp: 0813-1811-5151</p>
        </div>
    </div>

    <!-- Report Metadata -->
    <div class="metadata-container">
        <div class="report-title">{{ $reportTitle ?? 'Enterprise Report' }}</div>
        
        <table class="metadata-table">
            <tr>
                <td width="15%"><strong>Document Number</strong></td>
                <td width="35%">: {{ $documentNumber ?? 'REP-'.date('Ymd').'-'.rand(100000, 999999) }}</td>
                <td width="15%"><strong>Printed By</strong></td>
                <td width="35%">: {{ $generatedBy ?? auth()->user()->Full_Name ?? 'System Administrator' }}</td>
            </tr>
            <tr>
                <td><strong>Printed At</strong></td>
                <td>: {{ $generatedDate ?? now()->format('d M Y, H:i:s').' WIB' }}</td>
                <td><strong>Total Records</strong></td>
                <td>: {{ $totalRecords ?? 0 }} Data</td>
            </tr>
            <tr>
                <td><strong>WMS Version</strong></td>
                <td>: {{ $version ?? 'v1.0.0 (Enterprise)' }}</td>
                <td><strong>Filter Used</strong></td>
                <td>: {{ $filterUsed ?? 'None (All Records)' }}</td>
            </tr>
        </table>
    </div>

    <!-- Executive Summary -->
    @hasSection('executive_summary')
    <div class="exec-summary">
        <table>
            @yield('executive_summary')
        </table>
    </div>
    @endif

    <!-- Main Content -->
    @yield('content')

    <!-- Signatures -->
    <div class="signature-container" style="text-align: center; margin-top: 40px;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold; text-decoration: underline;">PT. WAKAMIYA MANDIRI SEJAHTERA</h3>
        
        <div style="margin: 15px 0;">
            @php
                // Menggunakan path logo karena sepertinya gambar cap dan tanda tangan tersimpan sebagai logo di database
                $stampPath = public_path('storage/companies/logos/dUBX7qmwgFlSFVCc6yfAkYiHua3lLB8ci1qw2IhS.jpg');
                if (file_exists($stampPath)) {
                    $stampData = base64_encode(file_get_contents($stampPath));
                    echo '<img src="data:image/jpeg;base64,'.$stampData.'" alt="Signature" style="max-height: 120px;">';
                }
            @endphp
        </div>
        
        <h3 style="margin: 10px 0 2px 0; font-size: 16px; font-weight: bold; text-decoration: underline;">HELMI MAULANA</h3>
        <p style="margin: 0; font-size: 14px;">ADMINISTRATION/<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGYAAAAVCAYAAAC0aZsNAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAFeSURBVGhD7ZOLqsMwDEPz/z+9kQsBo8iOnEfXCzkQhuWH3KYrn8srKShc9lBK+TsjzUOrCmhmqmHEjjl2H5yF8QxsLoPVMc1Dq3JAE4wz2N6VOZXWb1+E8lIy+VHtKnS6+kCoY5xh50Pb3dkvI8pVRvndULeTFxPVRF4erB4vAn8RT7coNTtZcsNlMUZG+SzePO8iMM6w0jsDdatL2OOBOYwtUa6iejaUGgXVc5TfTefGFmBaQ3mwKFdheaY1olwGnMNi75xGclhZZLbX6/P0LN6crH4K123nF6L2q55RTsWbkdVPQd1wCYxnGM3APMbIKD8CPwJ7GJ5+is6NLcC0Gbw5TGcaotR4ZHuz9at0bmwBps3CZqkaQ61DvL6sforODReoMWq7wflPeFbQA2NLlDsBdWsvpi3zxIv6hWcFfT1G+d086/bPsJdxL+ZlKP+mEzzveJG4F/NS7sW8lC/wujh8pjWy4gAAAABJRU5ErkJggg==" alt="事務部" style="height: 14px; vertical-align: middle; margin-bottom: 2px;"></p>
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated by WAKAMIYA MANAGEMENT SYSTEM | CONFIDENTIAL | {{ $generatedDate ?? now()->format('d M Y, H:i').' WIB' }}
    </div>

</body>
</html>
