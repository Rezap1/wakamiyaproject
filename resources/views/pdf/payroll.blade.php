@extends('pdf.layout')

@section('content')
<table style="border: none; margin-bottom: 30px;">
    <tr>
        <td style="border: none; width: 50%;">
            <strong>Detail Karyawan:</strong><br>
            ID: {{ $payroll['Employee_ID'] ?? '-' }}<br>
            Nama: {{ $employee['Full_Name'] ?? 'Tidak diketahui' }}<br>
            Departemen: {{ $employee['Department'] ?? '-' }}
        </td>
        <td style="border: none; width: 50%; text-align: right;">
            <strong>Detail Penggajian:</strong><br>
            No Penggajian: {{ $payroll['Payroll_Number'] ?? '-' }}<br>
            Periode: {{ $payroll['Payroll_Period'] ?? '-' }}<br>
            Tanggal Dibayar: {{ $payroll['Paid_Date'] ?? '-' }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Deskripsi Pendapatan</th>
            <th class="text-right">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Gaji Pokok</td>
            <td class="text-right">{{ number_format($payroll['Basic_Salary'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Tunjangan</td>
            <td class="text-right">{{ number_format($payroll['Allowance'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Bonus</td>
            <td class="text-right">{{ number_format($payroll['Bonus'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Lembur</td>
            <td class="text-right">{{ number_format($payroll['Overtime'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td class="text-right">Pendapatan Kotor</td>
            <td class="text-right">{{ number_format(
                ($payroll['Basic_Salary'] ?? 0) + 
                ($payroll['Allowance'] ?? 0) + 
                ($payroll['Bonus'] ?? 0) + 
                ($payroll['Overtime'] ?? 0), 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th>Deskripsi Potongan</th>
            <th class="text-right">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Pajak</td>
            <td class="text-right">{{ number_format($payroll['Tax'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>BPJS</td>
            <td class="text-right">{{ number_format($payroll['BPJS'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Potongan Lainnya</td>
            <td class="text-right">{{ number_format($payroll['Deduction'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td class="text-right">Total Potongan</td>
            <td class="text-right">{{ number_format(
                ($payroll['Tax'] ?? 0) + 
                ($payroll['BPJS'] ?? 0) + 
                ($payroll['Deduction'] ?? 0), 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<table style="margin-top: 20px;">
    <tr class="total-row" style="font-size: 14px;">
        <td class="text-right"><strong>Gaji Bersih (Take Home Pay)</strong></td>
        <td class="text-right" style="color: #1e3a8a;"><strong>Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}</strong></td>
    </tr>
</table>

<p style="margin-top: 30px; font-style: italic;">
    Gaji ini bersifat rahasia. Jika terdapat ketidaksesuaian, silakan hubungi bagian HR.
</p>
@endsection
