@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Laporan Piutang</h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-4 rounded shadow mb-6">
                <form method="GET" action="{{ route('reports.finance.outstanding') }}" class="flex flex-col md:flex-row gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Invoice</label>
                        <select name="type" class="form-select rounded-md border-gray-300">
                            <option value="">Semua Tipe</option>
                            <option value="STUDENT" {{ $type == 'STUDENT' ? 'selected' : '' }}>Siswa</option>
                            <option value="COMPANY" {{ $type == 'COMPANY' ? 'selected' : '' }}>Perusahaan</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-700 h-10">Filter</button>
                    <div class="ml-auto h-10 flex items-center">
                        <x-universal.multi-export route-prefix="reports.finance" :extra-params="['report_type' => 'outstanding']" />
                    </div>
                </form>
            </div>

            <div class="bg-white p-4 rounded shadow border-l-4 border-red-500 mb-6">
                <p class="text-gray-500 text-sm">Total Piutang Belum Terbayar</p>
                <p class="text-3xl font-bold text-red-600">Rp {{ number_format($total_outstanding, 0, ',', '.') }}</p>
            </div>

            <div class="bg-white rounded shadow overflow-hidden">
                <div class="p-4 border-b">
                    <h3 class="font-bold">Rincian Tagihan Belum Lunas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Tagihan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tujuan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Tagihan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sudah Dibayar</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa Piutang</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($invoices as $inv)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 {{ $inv['Due_Date'] < date('Y-m-d') ? 'text-red-600 font-bold' : '' }}">{{ $inv['Due_Date'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $inv['Invoice_ID'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($inv['Invoice_Type'] == 'STUDENT')
                                        Siswa: {{ $inv['Student_Name'] ?? $inv['Student_ID'] }}
                                    @elseif($inv['Invoice_Type'] == 'COMPANY')
                                        Perusahaan: {{ $inv['Company_Name'] ?? $inv['Company_ID'] }}
                                    @else
                                        {{ $inv['Invoice_Type'] }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        {{ $inv['Status'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">Rp {{ number_format($inv['Amount'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">Rp {{ number_format($inv['Paid_Amount'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right text-red-600">Rp {{ number_format($inv['Remaining_Amount'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">Tidak ada piutang saat ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection
