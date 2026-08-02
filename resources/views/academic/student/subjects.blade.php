@extends('layouts.app')
@section('header', 'My Subjects')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Mata Kuliah Saya ({{ $studentId }})</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($subjects as $s)
        <div class="p-4 border border-slate-200 rounded-xl">
            <h3 class="text-lg font-bold text-blue-700">{{ $s['name'] }}</h3>
            <p class="text-sm text-slate-500">Kode: {{ $s['code'] }} &bull; SKS: {{ $s['credits'] }}</p>
        </div>
        @endforeach
    </div>
</div>
@endsection



