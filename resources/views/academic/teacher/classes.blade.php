@extends('layouts.app')
@section('header', 'My Classes')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold mb-4">Kelas Yang Saya Ampu ({{ $teacherId }})</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($classes as $c)
        <div class="p-4 border border-gray-200 rounded-xl hover:shadow-md transition">
            <h3 class="text-lg font-bold text-blue-700">{{ $c['Class_Name'] }}</h3>
            <p class="text-sm text-gray-500">{{ $c['Program'] }} - Batch {{ $c['Batch'] }}</p>
            <div class="mt-4 flex justify-between items-center">
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $c['Students'] }} Students</span>
                <a href="#" class="text-xs bg-emerald-600 text-white px-3 py-1 rounded">Buka Kelas</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection



