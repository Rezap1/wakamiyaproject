@extends('pdf.layout')

@section('content')
<table style="border: none; margin-bottom: 30px;">
    <tr>
        <td style="border: none; width: 50%;">
            <strong>Ditagihkan Kepada:</strong><br>
            ID: {{ $invoice['Student_ID'] ?? $invoice['Company_ID'] ?? '-' }}<br>
            Nama: {{ $student['Full_Name'] ?? $company['Company_Name'] ?? 'Tidak diketahui' }}
        </td>
        <td style="border: none; width: 50%; text-align: right;">
            <strong>Detail Faktur:</strong><br>
            ID Ref: {{ $invoice['Invoice_ID'] ?? '-' }}<br>
            Tipe: {{ $invoice['Invoice_Type'] ?? '-' }}<br>
            Tanggal Jatuh Tempo: {{ $invoice['Due_Date'] ?? '-' }}<br>
            Status: {{ $invoice['Status'] ?? '-' }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Deskripsi</th>
            <th class="text-right">Kategori</th>
            <th class="text-right">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $invoice['Description'] ?? 'Tagihan Sistem' }}</td>
            <td class="text-right">{{ $invoice['Category'] ?? '-' }}</td>
            <td class="text-right">{{ number_format($invoice['Amount'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td colspan="2" class="text-right">Total Keseluruhan</td>
            <td class="text-right">{{ number_format($invoice['Amount'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p style="margin-top: 30px; font-style: italic;">
    Mohon segera melakukan pembayaran sebelum jatuh tempo. Apabila Anda telah melakukan pembayaran, mohon abaikan tagihan ini.
</p>
@endsection
