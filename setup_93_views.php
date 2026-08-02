<?php
$dir = 'resources/views/hr/payroll';
if (!is_dir($dir)) mkdir($dir, 0755, true);

// 1. Index
$index = <<<'EOT'
@extends('layouts.app')
@section('header', 'Enterprise Payroll')
@section('content')
<div class="space-y-6">
    <x-page-header title="Payroll Management" description="Manage employee salaries and slips." :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Payroll' => '#']">
        <x-slot:actions>
            <a href="{{ route('payrolls.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-sm">Generate Payroll</a>
        </x-slot:actions>
    </x-page-header>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Payroll Number</th>
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Period</th>
                        <th class="px-6 py-4 text-center">Net Salary</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($payrolls as $item)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Payroll_Number'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Employee_ID'] ?? 'Unknown' }}</td>
                            <td class="px-6 py-4">{{ $item['Payroll_Period'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Net_Salary'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $item['Status'] ?? 'Draft';
                                    $bg = 'bg-slate-100 text-slate-700';
                                    if($status == 'Paid') $bg = 'bg-emerald-100 text-emerald-700';
                                    elseif($status == 'Approved') $bg = 'bg-blue-100 text-blue-700';
                                    elseif($status == 'Calculated' || $status == 'Generated') $bg = 'bg-indigo-100 text-indigo-700';
                                    elseif($status == 'Waiting Approval') $bg = 'bg-amber-100 text-amber-700';
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('payrolls.show', $item['Payroll_ID']) }}" class="text-blue-600 font-bold text-xs hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No payroll data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/index.blade.php", $index);

// 2. Create
$create = <<<'EOT'
@extends('layouts.app')
@section('header', 'Generate Payroll')
@section('content')
<div class="space-y-6">
    <x-page-header title="Generate Payroll" description="Calculate new payroll." :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Payroll' => route('payrolls.index'), 'Generate' => '#']" />
    <form action="{{ route('payrolls.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Employee</label>
                    <select name="Employee_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($employees as $e)
                            <option value="{{ $e['Employee_ID'] ?? '' }}">{{ $e['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Payroll Period (YYYY-MM)</label>
                    <input type="text" name="Payroll_Period" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="{{ date('Y-m') }}" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Basic Salary</label>
                    <input type="number" name="Basic_Salary" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="0" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Allowance</label>
                    <input type="number" name="Allowance" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="0">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Bonus</label>
                    <input type="number" name="Bonus" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="0">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Overtime</label>
                    <input type="number" name="Overtime" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="0">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Deduction</label>
                    <input type="number" name="Deduction" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="0">
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2 mb-6">* Tax and BPJS will be auto-calculated upon generation.</p>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md">Calculate & Generate</button>
            </div>
        </div>
    </form>
</div>
@endsection
EOT;
file_put_contents("$dir/create.blade.php", $create);

// 3. Show (Employee Slip)
$show = <<<'EOT'
@extends('layouts.app')
@section('header', 'Payroll Detail')
@section('content')
<div class="space-y-6">
    <x-page-header title="Payroll Detail" description="Review salary calculation and document." :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Payroll' => route('payrolls.index'), 'Detail' => '#']" />
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Col: Employee Info -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Employee Information</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Employee ID</span>
                        <span class="font-bold text-slate-800">{{ $payroll['Employee_ID'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Payroll Number</span>
                        <span class="font-bold text-slate-800">{{ $payroll['Payroll_Number'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Period</span>
                        <span class="font-bold text-slate-800">{{ $payroll['Payroll_Period'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between pb-2">
                        <span class="text-slate-500">Status</span>
                        <span class="font-bold text-blue-600">{{ $payroll['Status'] ?? 'Draft' }}</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Actions</h3>
                <div class="space-y-3">
                    @if(($payroll['Status'] ?? '') == 'Draft')
                        <form action="{{ route('payrolls.update', $payroll['Payroll_ID']) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="Status" value="Approved">
                            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm">Approve Payroll</button>
                        </form>
                    @endif
                    @if(($payroll['Status'] ?? '') == 'Approved')
                        <form action="{{ route('payrolls.update', $payroll['Payroll_ID']) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="Status" value="Paid">
                            <button type="submit" class="w-full py-2.5 bg-emerald-500 text-white font-bold rounded-xl text-sm">Mark as Paid</button>
                        </form>
                    @endif
                    
                    <a href="{{ route('payrolls.slip', $payroll['Payroll_ID']) }}" class="block text-center w-full py-2.5 bg-slate-800 text-white font-bold rounded-xl text-sm mt-4">Preview Slip</a>
                    <button disabled class="w-full py-2.5 bg-slate-100 text-slate-400 font-bold rounded-xl text-sm cursor-not-allowed">Download PDF</button>
                </div>
            </div>
        </div>
        
        <!-- Right Col: Salary Breakdown -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-6">Salary Breakdown</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-slate-50 p-4 rounded-xl">
                        <span class="font-bold text-slate-600">Basic Salary</span>
                        <span class="font-black text-slate-800">Rp {{ number_format($payroll['Basic_Salary'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    
                    <!-- Earnings -->
                    <div class="pl-4 border-l-4 border-emerald-400 space-y-3">
                        <h4 class="text-xs font-bold text-emerald-600 uppercase">Earnings</h4>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Allowance</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['Allowance'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Bonus</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['Bonus'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Overtime</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['Overtime'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    
                    <!-- Deductions -->
                    <div class="pl-4 border-l-4 border-rose-400 space-y-3 mt-6">
                        <h4 class="text-xs font-bold text-rose-600 uppercase">Deductions</h4>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Deduction</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['Deduction'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Tax</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['Tax'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">BPJS</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($payroll['BPJS'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div>
                        <h4 class="text-sm font-bold text-slate-400 uppercase">Net Salary</h4>
                        <p class="text-xs text-slate-400">Total Take Home Pay</p>
                    </div>
                    <div class="text-4xl font-black text-slate-900">
                        Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl flex items-center">
                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="text-sm text-blue-800 font-medium">
                    Document Preparation: <span class="font-bold">{{ $payroll['Document_Number'] ?? 'Pending' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dir/show.blade.php", $show);

// 4. Edit (just a placeholder since updating payroll usually means regenerating it or minor tweaks)
$edit = <<<'EOT'
@extends('layouts.app')
@section('header', 'Edit Payroll')
@section('content')
<div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 text-center">
    <p class="text-slate-500">Edit payroll is restricted for audit purposes. Please cancel and recreate.</p>
    <a href="{{ route('payrolls.index') }}" class="mt-4 inline-block px-4 py-2 bg-slate-800 text-white rounded-lg">Back</a>
</div>
@endsection
EOT;
file_put_contents("$dir/edit.blade.php", $edit);

// 5. Slip Preview
$slip = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $slipNumber }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="p-8 flex justify-center items-start min-h-screen">
    <div class="w-[800px] bg-white shadow-xl p-12 relative">
        <div class="absolute top-12 right-12 text-right">
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-widest">Salary Slip</h1>
            <p class="text-sm font-bold text-slate-500 mt-1">{{ $slipNumber }}</p>
        </div>
        
        <div class="flex items-center gap-4 mb-12">
            <div class="w-16 h-16 bg-slate-900 flex items-center justify-center rounded-lg">
                <span class="text-white font-black text-xl">WMS</span>
            </div>
            <div>
                <h2 class="font-bold text-slate-800">LPK Wakamiya</h2>
                <p class="text-sm text-slate-500">Enterprise HR & Payroll System</p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-8 mb-12 text-sm border-b border-slate-200 pb-8">
            <div>
                <p class="font-bold text-slate-400 mb-1">EMPLOYEE</p>
                <p class="font-black text-slate-800 text-lg">{{ $payroll['Employee_ID'] ?? 'Unknown' }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-slate-400 mb-1">PERIOD</p>
                <p class="font-black text-slate-800 text-lg">{{ $payroll['Payroll_Period'] ?? '-' }}</p>
            </div>
        </div>
        
        <table class="w-full text-sm mb-12">
            <tr class="border-b-2 border-slate-800">
                <th class="text-left py-2 uppercase font-bold text-slate-600">Description</th>
                <th class="text-right py-2 uppercase font-bold text-slate-600">Earnings</th>
                <th class="text-right py-2 uppercase font-bold text-slate-600">Deductions</th>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Basic Salary</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Basic_Salary'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Allowance</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Allowance'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Bonus</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Bonus'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Overtime</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Overtime'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Deduction</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Deduction'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Tax</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Tax'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">BPJS</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['BPJS'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
        
        <div class="flex justify-end mb-16">
            <div class="w-72 bg-slate-50 p-6 border border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Net Salary</p>
                <p class="text-2xl font-black text-slate-900">Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        
        <div class="flex justify-between items-end border-t border-slate-200 pt-8 mt-auto">
            <div class="w-32 h-32 border-2 border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400 font-bold bg-slate-50">
                QR Placeholder
            </div>
            <div class="text-center">
                <div class="w-48 h-20 border-b-2 border-slate-800 mb-2 flex items-center justify-center text-slate-300 italic font-medium">Digital Signature</div>
                <p class="font-bold text-slate-800">HR Department</p>
                <p class="text-xs text-slate-500">Generated: {{ date('d M Y') }}</p>
            </div>
        </div>
        
    </div>
    
    <div class="fixed top-8 left-8">
        <button onclick="window.print()" class="px-6 py-3 bg-slate-900 text-white font-bold rounded shadow-xl hover:bg-slate-800 transition-colors">Print PDF Placeholder</button>
        <a href="javascript:history.back()" class="block mt-4 text-center text-sm font-bold text-slate-500 hover:text-slate-800">Back</a>
    </div>
</body>
</html>
EOT;
file_put_contents("$dir/slip.blade.php", $slip);

echo "HR Payroll Views created.\n";
?>
