@extends('pdf.layout')

@section('content')
<table style="border: none; margin-bottom: 30px;">
    <tr>
        <td style="border: none; width: 50%;">
            <strong>Profil Siswa:</strong><br>
            No. Siswa: {{ $student['Student_Number'] ?? $student['NIS'] ?? '-' }}<br>
            Nama: {{ $student['Full_Name'] ?? 'Tidak diketahui' }}<br>
            Program: {{ $program['Program_Name'] ?? '-' }}
        </td>
        <td style="border: none; width: 50%; text-align: right;">
            <strong>Detail Laporan:</strong><br>
            Tanggal Laporan: {{ $document_meta['generated_at'] ?? '-' }}<br>
            Status: Lulus
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Judul Penilaian</th>
            <th class="text-center">Skor</th>
            <th class="text-center">Nilai / Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($scores ?? [] as $index => $score)
        <tr>
            <td style="width: 5%;">{{ $index + 1 }}</td>
            <td style="width: 55%;">{{ $score['Assessment_Name'] ?? $score['Assessment_Title'] ?? 'Penilaian tidak ditemukan' }}</td>
            <td class="text-center" style="width: 20%;">{{ $score['Score_Value'] ?? '-' }}</td>
            <td class="text-center font-bold" style="width: 20%;">{{ $score['Status'] ?? 'LULUS' }}</td>
        </tr>
        @endforeach
        
        @if(empty($scores))
        <tr>
            <td colspan="4" class="text-center">Tidak ada skor tersedia</td>
        </tr>
        @endif
    </tbody>
</table>

<div style="margin-top: 30px;">
    <p><strong>Catatan:</strong></p>
    <p>Siswa telah memenuhi semua persyaratan akademik yang diamanatkan oleh kurikulum dan dianggap kompeten dalam modul yang dinilai.</p>
</div>
@endsection
