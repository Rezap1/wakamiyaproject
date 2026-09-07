<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }} - {{ $payment['Payment_ID'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
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
        .table-summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 25px;
        }
        .table-summary th {
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 12px;
            text-align: left;
        }
        .table-summary td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .amount-highlight {
            font-size: 16px;
            font-weight: 900;
            color: #166534;
        }
        .badge-verified {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
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

    <!-- SHARED OFFICIAL PDF HEADER (H8.4 / H8.7) -->
    @include('pdf.components.header', ['company' => $company ?? $companyProfile['company'] ?? []])

    <div style="text-align: right; margin-top: -15px; margin-bottom: 15px;">
        <div style="font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 1px;">{{ $documentTitle }}</div>
        <div style="font-size: 11px; font-weight: 700; color: #2563eb; font-family: monospace;">NO: {{ $payment['Payment_ID'] ?? '-' }}</div>
    </div>

    @if($integrityWarning)
        <p style="padding: 10px; border: 1px solid #b45309; color: #92400e;">! Perlu pemeriksaan: {{ $integrityWarning }}</p>
    @endif

    <!-- INFORMATION CARDS -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Identitas Pembayar</div>
                    <div class="field-row">
                        <span class="field-label">Nama {{ $customer['type_label'] }}:</span>
                        <span class="field-value">{{ $customer['name'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Nomor Resmi:</span>
                        <span class="field-value">{{ $customer['code'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Tipe Pihak:</span>
                        <span class="field-value">{{ $customer['type_label'] }}</span>
                    </div>
                    @if(($customer['class_name'] ?? '-') !== '-')
                        <div class="field-row"><span class="field-label">Kelas:</span><span class="field-value">{{ $customer['class_name'] }}</span></div>
                    @endif
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Informasi Pembayaran</div>
                    <div class="field-row">
                        <span class="field-label">Tgl Pembayaran:</span>
                        <span class="field-value">{{ !empty($payment['Payment_Date']) ? \App\Helpers\DateHelper::format($payment['Payment_Date'], 'd F Y') : '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Metode Bayar:</span>
                        <span class="field-value">{{ $paymentMethodLabel }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Akun Penerima:</span>
                        <span class="field-value">{{ $receivingAccount }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Status Verifikasi:</span>
                        <span class="badge-verified">{{ $paymentStatusLabel }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @if(!empty($payment['Invoice_ID']) && !empty($invoice))
    <!-- INVOICE REFERENCE SUMMARY -->
    <div style="margin-bottom: 10px;">
        <span style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Referensi Tagihan:</span>
        <span style="font-size: 11px; font-weight: 700; color: #0f172a; margin-left: 5px;">#{{ $payment['Invoice_ID'] ?? '-' }} &bull; {{ $invoice['Category'] ?? 'Pendidikan' }}</span>
    </div>

    <!-- BALANCE SUMMARY TABLE -->
    <table class="table-summary">
        <thead>
            <tr>
                <th>Deskripsi Komponen Keuangan</th>
                <th style="text-align: right;">Rincian Nominal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Tagihan</td>
                <td style="text-align: right; font-weight: bold;">Rp {{ number_format($balances['invoiceAmount'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Akumulasi Pembayaran Sebelumnya (Terverifikasi)</td>
                <td style="text-align: right; color: #475569;">{{ $balances['prevVerified'] === null ? 'Urutan pembayaran belum dapat dipastikan' : 'Rp ' . number_format($balances['prevVerified'], 0, ',', '.') }}</td>
            </tr>
            <tr style="background: #f0fdf4;">
                <td><strong>PEMBAYARAN INI ({{ $paymentStatusLabel }})</strong></td>
                <td style="text-align: right;" class="amount-highlight">Rp {{ number_format($balances['currentPayment'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Total Telah Dibayar (Terverifikasi, Terkini)</td>
                <td style="text-align: right; font-weight: bold;">Rp {{ number_format((float) ($balances['acceptedPaid'] ?? 0), 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Sisa Tagihan Terkini</strong></td>
                <td style="text-align: right; font-weight: bold; color: {{ ($balances['remainingBalance'] ?? 0) > 0 ? '#b91c1c' : '#15803d' }};">
                    Rp {{ number_format($balances['remainingBalance'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>
    @else
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-title">Pembayaran Tanpa Tagihan</div>
        <div class="field-row"><span class="field-label">Jenis:</span><span class="field-value">{{ $paymentTypeLabel }}</span></div>
        <div class="field-row"><span class="field-label">Nominal Pembayaran:</span><span class="field-value">Rp {{ number_format($balances['currentPayment'] ?? 0, 0, ',', '.') }}</span></div>
        <div class="field-row"><span class="field-label">Status:</span><span class="field-value">{{ $paymentStatusLabel }}</span></div>
    </div>
    @endif

    <!-- SHARED OFFICIAL PDF FOOTER (H8.5 / H8.7) -->
    @include('pdf.components.footer', [
        'document' => $document ?? $companyProfile['document'] ?? [],
        'verificationUrl' => $verificationUrl ?? null,
        'qrCodeSvg' => $qrCodeSvg ?? null,
        'notice' => 'Dokumen menampilkan status pembayaran saat diterbitkan. Pelunasan hanya berasal dari pembayaran terverifikasi yang dialokasikan ke tagihan terkait. Periksa status terkini melalui tautan verifikasi.'
    ])

</body>
</html>
