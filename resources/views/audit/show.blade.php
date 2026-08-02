@extends('layouts.app')
@section('header', 'Detail Audit')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header title="Detail Audit" description="Jejak detail dari log aktivitas." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Jejak Audit' => route('audit.index'), 'Detail' => '#']" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Event Details -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2">Informasi Kejadian</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Audit ID</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Audit_ID'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Stempel Waktu</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Created_At'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Modul</span>
                    <span class="font-semibold text-blue-600 text-sm">{{ $log['Module'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Aksi</span>
                    <span class="font-semibold text-slate-800 text-sm">{{ $log['Action'] }}</span>
                </div>
            </div>
            
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mt-6">Target Referensi</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Tipe</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Reference_Type'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">ID</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Reference_ID'] }}</span>
                </div>
            </div>
        </div>

        <!-- Actor & Client -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2">Aktor (Pengguna)</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <span class="block text-xs font-bold text-slate-400 uppercase">User ID (Email)</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['User_ID'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Peran</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Role'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Departemen</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Department'] }}</span>
                </div>
            </div>

            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mt-6">Perangkat Klien</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">IP Address</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['IPAddress'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Lokasi</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Location'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Browser</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Browser'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">OS</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Operating_System'] }}</span>
                </div>
                <div class="col-span-2">
                    <span class="block text-xs font-bold text-slate-400 uppercase">User Agent</span>
                    <span class="font-mono text-slate-600 text-xs break-all mt-1 block bg-slate-50 p-2 rounded border border-slate-200">{{ $log['Device'] }}</span>
                </div>
            </div>
        </div>

        <!-- Data Changes -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 md:col-span-2">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">Payload & Perubahan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase mb-2">Nilai Lama</span>
                    <pre class="bg-slate-50 p-4 rounded-xl text-xs text-slate-600 overflow-x-auto border border-slate-200 whitespace-pre-wrap">{{ empty($log['Old_Value']) ? '(kosong)' : $log['Old_Value'] }}</pre>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase mb-2">Nilai Baru</span>
                    <pre class="bg-slate-50 p-4 rounded-xl text-xs text-slate-600 overflow-x-auto border border-slate-200 whitespace-pre-wrap">{{ empty($log['New_Value']) ? '(kosong)' : $log['New_Value'] }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



