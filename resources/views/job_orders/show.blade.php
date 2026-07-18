@extends('layouts.app')

@section('header', 'Detail Job Order')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('job-orders.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Detail Job Order</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Informasi lengkap data permintaan pekerjaan.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('job-orders.edit', $jobOrder['Job_Order_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="flex items-center mb-8 pb-8 border-b border-gray-100">
                <div class="h-20 w-20 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-3xl shadow-inner border-4 border-white ring-4 ring-indigo-50 flex-shrink-0">
                    {{ substr($jobOrder['Company_Name'] ?? 'J', 0, 1) }}
                </div>
                <div class="ml-6 flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-extrabold text-gray-900">{{ $jobOrder['Job_Title'] ?? '-' }}</h3>
                            <p class="text-gray-500 font-medium">{{ $jobOrder['Company_Name'] ?? '-' }} ({{ $jobOrder['Company_Code'] ?? '-' }})</p>
                        </div>
                        <div class="text-right">
                            <span class="block text-lg font-bold text-gray-900">{{ $jobOrder['Job_Order_Number'] ?? '-' }}</span>
                            <span class="block text-xs font-medium text-gray-500">{{ $jobOrder['Job_Order_ID'] ?? '-' }}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 mt-3">
                        @if(($jobOrder['Is_Active'] ?? 'TRUE') === 'TRUE')
                            @if(($jobOrder['Job_Order_Status'] ?? 'OPEN') === 'OPEN')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> OPEN
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'CLOSED')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> CLOSED
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'DRAFT')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-1.5"></span> DRAFT
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'CANCELLED')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gray-50 text-gray-700 border border-gray-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500 mr-1.5"></span> CANCELLED
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200 shadow-sm">
                                INACTIVE
                            </span>
                        @endif
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                            Kuota: {{ $jobOrder['Recruitment_Quantity'] ?? '0' }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold {{ ($jobOrder['Remaining_Quota'] ?? 0) > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }} shadow-sm">
                            Sisa: {{ $jobOrder['Remaining_Quota'] ?? '0' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Data Utama Pekerjaan -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Detail Pekerjaan
                    </h4>
                    <dl class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Kategori</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Job_Category'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Lokasi Kerja</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Work_Location'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Prefektur</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Prefecture'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Tipe Pekerjaan</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Employment_Type'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Tipe Visa</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Visa_Type'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-gray-200 pt-4 mt-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Deskripsi</dt>
                            <dd class="text-sm text-gray-800 col-span-2 whitespace-pre-line">{{ $jobOrder['Job_Description'] ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Kualifikasi -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Persyaratan & Kualifikasi
                    </h4>
                    <dl class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Gender</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Gender_Requirement'] ?? 'Bebas' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Rentang Usia</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Minimum_Age'] ?? 'Tidak ditentukan' }} - {{ $jobOrder['Maximum_Age'] ?? 'Tidak ditentukan' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Pendidikan</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Education_Requirement'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Level Bahasa</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                    {{ $jobOrder['Japanese_Level'] ?? 'Bebas' }}
                                </span>
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-gray-200 pt-4 mt-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Keahlian Khusus</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Required_Skill'] ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>
                
                <!-- Kompensasi & Fasilitas -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Gaji & Fasilitas
                    </h4>
                    <dl class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Gaji Pokok</dt>
                            <dd class="text-sm font-extrabold text-green-600 col-span-2">¥ {{ number_format((float)($jobOrder['Basic_Salary'] ?? 0), 0, ',', '.') }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Lembur</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Overtime_Pay'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Jam/Hari Kerja</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Working_Hours'] ?? '-' }} / {{ $jobOrder['Working_Days'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Libur</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Holiday'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-gray-200 pt-4 mt-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Akomodasi</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Accommodation'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Makan & Transport</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Meal'] ?? '-' }} | {{ $jobOrder['Transportation'] ?? '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Asuransi</dt>
                            <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $jobOrder['Insurance'] ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
                
                <!-- Jadwal & Sistem -->
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 flex flex-col gap-6">
                    <div>
                        <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Jadwal Pelaksanaan
                        </h4>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Tgl Wawancara</dt>
                                <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ !empty($jobOrder['Interview_Date']) ? \Carbon\Carbon::parse($jobOrder['Interview_Date'])->format('d F Y') : 'Belum ditentukan' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Target Berangkat</dt>
                                <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ !empty($jobOrder['Departure_Target']) ? \Carbon\Carbon::parse($jobOrder['Departure_Target'])->format('d F Y') : 'Belum ditentukan' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">PIC Internal WMS</dt>
                                <dd class="text-sm font-semibold text-gray-900 col-span-2">{{ $employee['Full_Name'] ?? ($jobOrder['PIC_Employee_ID'] ?: '-') }}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Informasi Sistem
                        </h4>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Dibuat Pada</dt>
                                <dd class="text-sm font-semibold text-gray-900 col-span-2">
                                    {{ !empty($jobOrder['Created_At']) ? \Carbon\Carbon::parse($jobOrder['Created_At'])->format('d M Y, H:i') : '-' }}<br>
                                    <span class="text-xs text-gray-500 font-normal">Oleh: {{ $jobOrder['Created_By'] ?? '-' }}</span>
                                </dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-xs font-bold text-gray-500 uppercase col-span-1">Diperbarui Pada</dt>
                                <dd class="text-sm font-semibold text-gray-900 col-span-2">
                                    {{ !empty($jobOrder['Updated_At']) ? \Carbon\Carbon::parse($jobOrder['Updated_At'])->format('d M Y, H:i') : '-' }}<br>
                                    <span class="text-xs text-gray-500 font-normal">Oleh: {{ $jobOrder['Updated_By'] ?? '-' }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="lg:col-span-2 bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h4 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Catatan Internal WMS
                    </h4>
                    <div class="text-sm text-gray-800 whitespace-pre-line bg-white p-4 rounded-xl border border-gray-200 shadow-sm min-h-[100px]">{{ $jobOrder['Notes'] ?: 'Tidak ada catatan.' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
