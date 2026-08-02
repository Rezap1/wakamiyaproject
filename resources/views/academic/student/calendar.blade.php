@extends('layouts.app')
@section('header', 'Academic Calendar')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-6">Jadwal & Deadline Saya ({{ $studentId }})</h2>
    <div class="relative border-l-2 border-blue-500 pl-4 space-y-6">
        @foreach($events as $e)
        <div class="relative">
            <div class="absolute -left-5 top-1 w-3 h-3 rounded-full {{ $e['type']=='Exam'?'bg-red-500':($e['type']=='Deadline'?'bg-yellow-500':'bg-blue-500') }}"></div>
            <p class="text-sm text-gray-500 font-bold">{{ $e['date'] }}</p>
            <p class="font-medium">{{ $e['title'] }}</p>
            <span class="text-[10px] uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $e['type'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endsection



