@extends('layouts.app')
@section('header', 'My Schedule')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Jadwal Kuliah Saya ({{ $studentId }})</h2>
    <table class="min-w-full">
        <tr class="bg-slate-100">
            <th class="p-3 text-left">Hari</th><th class="p-3 text-left">Waktu</th><th class="p-3 text-left">Mata Kuliah</th><th class="p-3 text-left">Ruang</th>
        </tr>
        @foreach($schedules as $s)
        <tr class="border-b">
            <td class="p-3 font-bold">{{ $s['day'] }}</td>
            <td class="p-3">{{ $s['time'] }}</td>
            <td class="p-3 text-blue-600 font-bold">{{ $s['subject'] }}</td>
            <td class="p-3">{{ $s['room'] }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endsection



