<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SURAT PERINTAH / PERSETUJUAN LEMBUR - {{ $overtime['Overtime_ID'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
            background: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .company-sub {
            font-size: 10px;
            color: #64748b;
            font-weight: 700;
            margin-top: 2px;
        }
        .doc-title {
            text-align: right;
            vertical-align: bottom;
        }
        .doc-title-text {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-subtitle {
            font-size: 11px;
            font-weight: 700;
            color: #2563eb;
            font-family: monospace;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
            padding: 0 5px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .card-title {
            font-size: 10px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .field-row {
            margin-bottom: 4px;
        }
        .field-label {
            font-size: 10px;
            color: #64748b;
            display: inline-block;
            width: 110px;
        }
        .field-value {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .footer-table td {
            vertical-align: top;
            width: 50%;
        }
        .qr-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            width: 220px;
            text-align: center;
        }
        .signature-box {
            text-align: center;
            padding-top: 10px;
        }
        .signature-line {
            margin-top: 55px;
            border-bottom: 1px solid #0f172a;
            width: 180px;
            display: inline-block;
        }
        .disclaimer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- HEADER BRANDING -->
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ $company['name'] ?? 'WAKAMIYA MANAGEMENT SYSTEM' }}</div>
                <div class="company-sub">{{ $company['tagline'] ?? 'Enterprise Human Resource Engine' }}</div>
            </td>
            <td class="doc-title">
                <div class="doc-title-text">PERSETUJUAN LEMBUR</div>
                <div class="doc-subtitle">NO: {{ $overtime['Document_Number'] ?? $overtime['Overtime_ID'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <!-- INFORMATION CARDS -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Identitas Pegawai Lembur</div>
                    <div class="field-row">
                        <span class="field-label">Nama Lengkap:</span>
                        <span class="field-value">{{ $employee['Full_Name'] ?? $overtime['Employee_Name'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">ID Pegawai:</span>
                        <span class="field-value">{{ $overtime['Employee_ID'] ?? '-' }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Detail Lembur & Perhitungan</div>
                    <div class="field-row">
                        <span class="field-label">Tanggal Lembur:</span>
                        <span class="field-value">{{ $overtime['Date'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Jam Lembur:</span>
                        <span class="field-value">{{ $overtime['Start_Time'] ?? '-' }} s/d {{ $overtime['End_Time'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Durasi Terhitung:</span>
                        <span class="field-value">{{ $overtime['Duration_Hours'] ?? 0 }} Jam</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Estimasi Upah Lembur:</span>
                        <span class="field-value" style="color: #15803d;">
                            Rp {{ number_format((float)($overtime['Overtime_Pay'] ?? 0), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-title">Tugas & Alasan Lembur</div>
        <div style="font-size: 11px; color: #0f172a; font-style: italic;">
            "{{ $overtime['Reason'] ?? 'TIDAK ADA CATATAN' }}"
        </div>
    </div>

    <!-- FOOTER SIGNATURE & QR CODE -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="qr-box">
                    <div style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Verifikasi Surat Lembur</div>
                    @if(!empty($qrCodeSvg))
                        <div style="margin: 0 auto; display: inline-block;">
                            {!! $qrCodeSvg !!}
                        </div>
                    @else
                        <div style="font-size: 9px; color: #2563eb; font-weight: bold; word-break: break-all;">
                            {{ $verificationUrl }}
                        </div>
                    @endif
                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">Pindai QR Code untuk memverifikasi keabsahan lembur ini secara publik.</div>
                </div>
            </td>
            <td>
                <div class="signature-box">
                    <div style="font-size: 10px; font-weight: 700; color: #64748b;">Disetujui Oleh:</div>
                    <div style="font-size: 9px; color: #94a3b8; margin-top: 2px;">Atasan Direct / HR Manager</div>
                    
                    <div class="signature-line"></div>
                    <div style="font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        {{ $overtime['Approved_By'] ?? 'HR Manager' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="disclaimer">
        Dokumen ini diterbitkan secara elektronik oleh Wakamiya Management System (WMS) dan memiliki kekuatan verifikasi sah. Upah lembur akan dicairkan otomatis via Payroll Engine Phase G.
    </div>

</body>
</html>
