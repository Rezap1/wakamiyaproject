@extends('layouts.app')
@section('header', 'Kalender Akademik Master')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Jadwal Akademik Institusi</h2>
        <button class="bg-emerald-600 text-white px-3 py-1.5 rounded text-sm font-bold">+ Tambah Acara</button>
    </div>
    <div class="relative border-l-2 border-blue-500 pl-4 space-y-6">
        @foreach($events as $e)
        <div class="relative group">
            <div class="absolute -left-5 top-1 w-3 h-3 rounded-full {{ $e['type']=='Exam'?'bg-red-500':($e['type']=='Deadline'?'bg-yellow-500':'bg-blue-500') }} ring-4 ring-white"></div>
            <p class="text-sm text-gray-500 font-bold">{{ $e['date'] }}</p>
            <p class="font-medium text-lg">{{ $e['title'] }}</p>
            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 mt-1 inline-block">{{ $e['type'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endsection



