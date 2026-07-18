@extends('layouts.app')

@section('header', 'Dasbor Eksekutif')

@section('content')
<div class="space-y-8">
    
    <!-- Top KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Total Students -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg transition-all relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50/50 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Total Siswa</p>
                        <h3 class="text-3xl font-extrabold text-gray-900">{{ number_format($kpi['total_students']) }}</h3>
                    </div>
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl shadow-sm border border-blue-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-5 flex items-center text-sm font-medium">
                    <span class="text-green-500 flex items-center bg-green-50 px-2 py-1 rounded-md">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        12%
                    </span>
                    <span class="text-gray-400 ml-3">dari bulan lalu</span>
                </div>
            </div>
        </div>

        <!-- Total Employees -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg transition-all relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50/50 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Total Karyawan</p>
                        <h3 class="text-3xl font-extrabold text-gray-900">{{ number_format($kpi['total_employees']) }}</h3>
                    </div>
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl shadow-sm border border-purple-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                </div>
                <div class="mt-5 flex items-center text-sm font-medium">
                    <span class="text-gray-500 bg-gray-50 px-2 py-1 rounded-md">Personel Aktif</span>
                </div>
            </div>
        </div>

        <!-- Cash & Bank -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg transition-all relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-green-50/50 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-1">Total Likuiditas</p>
                        <h3 class="text-2xl font-extrabold text-gray-900">Rp {{ number_format($kpi['cash'] + $kpi['bank'], 0, ',', '.') }}</h3>
                    </div>
                    <div class="p-3 bg-green-50 text-green-600 rounded-2xl shadow-sm border border-green-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs font-bold text-gray-500 bg-gray-50 p-2 rounded-lg">
                    <span>Kas: <span class="text-gray-800">Rp {{ number_format($kpi['cash']/1000000, 0) }}M</span></span>
                    <span class="mx-2 text-gray-300">|</span>
                    <span>Bank: <span class="text-gray-800">Rp {{ number_format($kpi['bank']/1000000, 0) }}M</span></span>
                </div>
            </div>
        </div>

        <!-- Profit -->
        <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl p-6 shadow-lg shadow-primary-500/20 text-white relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-primary-100 text-sm font-bold uppercase tracking-wider mb-1">Laba Bersih (YTD)</p>
                        <h3 class="text-2xl font-extrabold">Rp {{ number_format($kpi['profit'], 0, ',', '.') }}</h3>
                    </div>
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm border border-white/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>
                <div class="mt-5 flex items-center text-sm font-medium">
                    <span class="flex items-center text-white bg-white/20 px-2 py-1 rounded-md backdrop-blur-sm border border-white/10">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        24%
                    </span>
                    <span class="text-primary-100 ml-3">vs tahun lalu</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Area (Placeholders) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cash Flow Chart -->
        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
            <h4 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                Ringkasan Arus Kas
            </h4>
            <div class="h-64 flex items-end justify-between space-x-2 border-b-2 border-l-2 border-gray-100 p-4 relative">
                <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-400 -ml-8 py-4">
                    <span>100</span><span>75</span><span>50</span><span>25</span><span>0</span>
                </div>
                <!-- Dummy Bars -->
                @foreach([30, 45, 25, 60, 80, 50, 75, 90, 65, 85, 40, 70] as $h)
                <div class="w-full bg-primary-100 rounded-t-md relative group cursor-pointer hover:bg-primary-200 transition-colors" style="height: {{ $h }}%">
                    <div class="absolute bottom-0 w-full bg-primary-500 rounded-t-md shadow-[0_-2px_10px_rgba(0,151,178,0.3)] transition-all group-hover:bg-primary-600" style="height: {{ $h * 0.7 }}%"></div>
                </div>
                @endforeach
            </div>
            <div class="flex justify-between text-xs font-bold text-gray-400 mt-3 px-4">
                <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mei</span><span>Jun</span><span>Jul</span><span>Ags</span><span>Sep</span><span>Okt</span><span>Nov</span><span>Des</span>
            </div>
        </div>

        <!-- Upcoming Schedule -->
        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 flex flex-col">
            <h4 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda Mendatang
            </h4>
            
            <div class="flex-1 space-y-4">
                <div class="p-5 rounded-2xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/50 transition-all flex items-center justify-between group cursor-pointer shadow-sm">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-bold text-gray-900">Keberangkatan ke Jepang</p>
                            <p class="text-xs font-medium text-gray-500 mt-0.5">Dijadwalkan bulan ini</p>
                        </div>
                    </div>
                    <span class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-bold shadow-md shadow-blue-500/30">{{ $kpi['upcoming_departure'] }}</span>
                </div>

                <div class="p-5 rounded-2xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50/50 transition-all flex items-center justify-between group cursor-pointer shadow-sm">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-bold text-gray-900">Wawancara Pengguna</p>
                            <p class="text-xs font-medium text-gray-500 mt-0.5">Dijadwalkan minggu depan</p>
                        </div>
                    </div>
                    <span class="px-4 py-1.5 bg-purple-600 text-white rounded-lg text-sm font-bold shadow-md shadow-purple-500/30">{{ $kpi['upcoming_interview'] }}</span>
                </div>

                <div class="p-5 rounded-2xl border border-red-200 bg-red-50 flex items-center justify-between relative overflow-hidden shadow-sm">
                    <div class="absolute inset-0 bg-red-500/5 pattern-diagonal-lines"></div>
                    <div class="flex items-center relative z-10">
                        <div class="w-12 h-12 rounded-xl bg-red-100 text-red-600 flex items-center justify-center shadow-inner">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-bold text-gray-900">Pembayaran Menunggak</p>
                            <p class="text-xs font-bold text-red-500 mt-0.5">Butuh perhatian segera</p>
                        </div>
                    </div>
                    <span class="font-extrabold text-red-600 bg-white px-3 py-1 rounded-lg border border-red-100 shadow-sm relative z-10">Rp {{ number_format($kpi['outstanding_payment']/1000000, 0) }}M</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
