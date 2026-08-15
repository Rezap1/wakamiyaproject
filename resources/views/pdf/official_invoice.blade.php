<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>INVOICE TAGIHAN RESMI - {{ $invoice['Invoice_ID'] ?? '' }}</title>
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
            font-size: 20px;
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
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .table-items th {
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
        }
        .table-items td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .summary-box td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .badge-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
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
            margin-top: 25px;
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
                <div class="company-sub">{{ $company['tagline'] ?? 'Enterprise ERP System' }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                    {{ $company['address'] ?? '' }} &bull; {{ $company['contact'] ?? '' }}
                </div>
            </td>
            <td class="doc-title">
                <div class="doc-title-text">INVOICE TAGIHAN</div>
                <div class="doc-subtitle">NO: {{ $invoice['Invoice_ID'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <!-- INFORMATION CARDS -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Ditujukan Kepada (Penerima)</div>
                    <div class="field-row">
                        <span class="field-label">Nama Pihak:</span>
                        <span class="field-value">{{ $customer['name'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Nomor ID:</span>
                        <span class="field-value">{{ $customer['code'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Tipe Subjek:</span>
                        <span class="field-value">{{ $customer['type'] ?? 'STUDENT' }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Detail Tagihan</div>
                    <div class="field-row">
                        <span class="field-label">Kategori Tagihan:</span>
                        <span class="field-value">{{ $invoice['Category'] ?? 'Pendidikan' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Tgl Jatuh Tempo:</span>
                        <span class="field-value" style="color: #b91c1c;">
                            {{ !empty($invoice['Due_Date']) ? \Carbon\Carbon::parse($invoice['Due_Date'])->format('d F Y') : '-' }}
                        </span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Status Tagihan:</span>
                        <span class="badge-status" style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1;">
                            {{ $invoice['Display_Status'] ?? ($invoice['Status'] ?? 'Draft') }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ITEMIZED LINE ITEMS TABLE -->
    <div style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Rincian Komponen Tagihan (Itemized Line Items):</div>
    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">No</th>
                <th>Deskripsi Komponen Tagihan</th>
                <th style="width: 50px; text-align: center;">Qty</th>
                <th style="width: 100px; text-align: right;">Harga Satuan</th>
                <th style="width: 80px; text-align: right;">Diskon</th>
                <th style="width: 80px; text-align: right;">Pajak</th>
                <th style="width: 110px; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice['Parsed_Line_Items'] ?? [] as $index => $item)
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $item['description'] ?? '-' }}</td>
                    <td style="text-align: center;">{{ $item['qty'] ?? 1 }}</td>
                    <td style="text-align: right;">Rp {{ number_format((float)($item['unit_price'] ?? 0), 0, ',', '.') }}</td>
                    <td style="text-align: right; color: #166534;">
                        {{ ($item['discount'] ?? 0) > 0 ? '- Rp ' . number_format((float)$item['discount'], 0, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; color: #b91c1c;">
                        {{ ($item['tax'] ?? 0) > 0 ? '+ Rp ' . number_format((float)$item['tax'], 0, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">
                        Rp {{ number_format((float)($item['subtotal'] ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #94a3b8;">Tidak ada rincian item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SUMMARY RECAPITULATION -->
    <table class="summary-box">
        <tr>
            <td style="width: 60%;"></td>
            <td>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="color: #64748b;">Subtotal Sebelum Potongan/Pajak:</td>
                        <td style="text-align: right; font-weight: bold;">Rp {{ number_format((float)($invoice['Subtotal_Amount'] ?? $invoice['Amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    @if(($invoice['Total_Discount'] ?? 0) > 0)
                        <tr>
                            <td style="color: #166534;">Total Diskon/Potongan:</td>
                            <td style="text-align: right; font-weight: bold; color: #166534;">- Rp {{ number_format((float)$invoice['Total_Discount'], 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    @if(($invoice['Total_Tax'] ?? 0) > 0)
                        <tr>
                            <td style="color: #b91c1c;">Total Pajak:</td>
                            <td style="text-align: right; font-weight: bold; color: #b91c1c;">+ Rp {{ number_format((float)$invoice['Total_Tax'], 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr style="border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; background: #f8fafc;">
                        <td style="font-weight: 900; font-size: 11px; text-transform: uppercase;">GRAND TOTAL TAGIHAN:</td>
                        <td style="text-align: right; font-weight: 900; font-size: 13px; color: #0f172a;">Rp {{ number_format((float)($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="color: #15803d; font-weight: bold;">Sudah Dibayar (Verified):</td>
                        <td style="text-align: right; font-weight: bold; color: #15803d;">Rp {{ number_format((float)($invoice['Paid_Amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="color: #b91c1c; font-weight: 900;">SISA PIUTANG TERKINI:</td>
                        <td style="text-align: right; font-weight: 900; color: #b91c1c; font-size: 12px;">Rp {{ number_format((float)($invoice['Remaining_Amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- FOOTER SIGNATURE & QR CODE -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="qr-box">
                    <div style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Verifikasi Keabsahan Invoice</div>
                    @if(!empty($qrCodeSvg))
                        <div style="margin: 0 auto; display: inline-block;">
                            {!! $qrCodeSvg !!}
                        </div>
                    @else
                        <div style="font-size: 9px; color: #2563eb; font-weight: bold; word-break: break-all; margin: 10px 0;">
                            {{ $verificationUrl }}
                        </div>
                    @endif
                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">Pindai QR Code untuk memverifikasi keaslian dokumen tagihan ini secara publik.</div>
                </div>
            </td>
            <td>
                <div class="signature-box">
                    <div style="font-size: 10px; font-weight: 700; color: #64748b;">Diterbitkan & Disahkan Oleh:</div>
                    <div style="font-size: 9px; color: #94a3b8; margin-top: 2px;">Departemen Keuangan WMS</div>
                    
                    <div class="signature-line"></div>
                    <div style="font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        Finance Officer WMS
                    </div>
                    <div style="font-size: 9px; color: #64748b;">
                        Tanggal: {{ date('d M Y') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- DISCLAIMER -->
    <div class="disclaimer">
        Dokumen ini merupakan Invoice Tagihan Resmi yang diterbitkan secara elektronik oleh Wakamiya Management System (WMS). Pembayaran dianggap sah setelah bukti pembayaran diverifikasi oleh Departemen Keuangan.
    </div>

</body>
</html>
