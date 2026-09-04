@extends('pdf.layout')

@section('content')
<table style="border: none; margin-bottom: 30px;">
    <tr>
        <td style="border: none; width: 50%;">
            <strong>Diterima Dari:</strong><br>
            Nama: {{ $payment['student_name'] ?? $student['Full_Name'] ?? $company['Company_Name'] ?? 'Tidak diketahui' }}
        </td>
        <td style="border: none; width: 50%; text-align: right;">
            <strong>Detail Kwitansi:</strong><br>
            ID Pembayaran: {{ $payment['Payment_ID'] ?? '-' }}<br>
            Ref Faktur: {{ $payment['Invoice_ID'] ?? '-' }}<br>
            Tanggal Pembayaran: {{ $payment['Payment_Date'] ?? '-' }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Deskripsi</th>
            <th class="text-right">Jumlah Dibayar (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Pembayaran untuk Invoice {{ $payment['Invoice_ID'] ?? '-' }}</td>
            <td class="text-right">{{ number_format($payment['Amount_Paid'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td class="text-right">Total Diterima</td>
            <td class="text-right">{{ number_format($payment['Amount_Paid'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p style="margin-top: 30px; font-style: italic;">
    Terima kasih atas pembayaran Anda. Pembayaran ini sah dan telah diverifikasi oleh sistem.
</p>
@endsection
