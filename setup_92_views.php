<?php
$dirs = [
    'resources/views/finance/invoices',
    'resources/views/finance/payments',
    'resources/views/student/billing'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// 1. Finance Invoices Index
$invIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'Invoice Management')
@section('content')
<div class="space-y-6">
    <x-page-header title="Invoices" description="Manage student bills and invoices." :breadcrumbs="['Dashboard' => route('dashboard.finance'), 'Invoices' => '#']">
        <x-slot:actions>
            <a href="{{ route('invoices.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl">Create Invoice</a>
        </x-slot:actions>
    </x-page-header>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr><th class="px-6 py-4">Invoice ID</th><th class="px-6 py-4">Student</th><th class="px-6 py-4">Category</th><th class="px-6 py-4 text-center">Amount</th><th class="px-6 py-4 text-center">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($invoices as $item)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Student_ID'] ?? 'Unknown' }}</td>
                            <td class="px-6 py-4 font-semibold">{{ $item['Category'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $item['Status'] ?? 'Draft';
                                    $bg = $status == 'Paid' ? 'bg-emerald-100 text-emerald-700' : ($status == 'Waiting Payment' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('invoices.show', $item['Invoice_ID']) }}" class="text-blue-600 font-bold text-xs hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No invoices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/finance/invoices/index.blade.php', $invIndex);

// 2. Finance Invoices Create
$invCreate = <<<'EOT'
@extends('layouts.app')
@section('header', 'Create Invoice')
@section('content')
<div class="space-y-6">
    <x-page-header title="New Invoice" description="Create a new bill." :breadcrumbs="['Dashboard' => route('dashboard.finance'), 'Invoices' => route('invoices.index'), 'Create' => '#']" />
    <form action="{{ route('invoices.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Student</label>
                    <select name="Student_ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach($students as $s)
                            <option value="{{ $s['Student_ID'] ?? '' }}">{{ $s['Name'] ?? 'Unknown' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Category</label>
                    <select name="Category" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach(config('finance.categories') as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Amount</label>
                    <input type="number" name="Amount" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Due Date</label>
                    <input type="date" name="Due_Date" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" value="{{ date('Y-m-d', strtotime('+7 days')) }}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Status</label>
                    <select name="Status" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3">
                        @foreach(config('finance.invoice_status') as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md">Create Invoice</button>
            </div>
        </div>
    </form>
</div>
@endsection
EOT;
file_put_contents('resources/views/finance/invoices/create.blade.php', $invCreate);

// 3. Finance Payments Index
$payIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'Payment Verification')
@section('content')
<div class="space-y-6">
    <x-page-header title="Payment Verifications" description="Review and verify student payments." :breadcrumbs="['Dashboard' => route('dashboard.finance'), 'Payments' => '#']" />
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr><th class="px-6 py-4">Receipt ID</th><th class="px-6 py-4">Invoice ID</th><th class="px-6 py-4 text-center">Amount Paid</th><th class="px-6 py-4 text-center">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($payments as $item)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Payment_ID'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount_Paid'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $item['Status'] ?? 'Waiting Verification';
                                    $bg = $status == 'Verified' ? 'bg-emerald-100 text-emerald-700' : ($status == 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($status == 'Waiting Verification')
                                    <form action="{{ route('payments.verify', $item['Payment_ID']) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="Status" value="Verified">
                                        <button type="submit" class="px-3 py-1 bg-emerald-500 text-white font-bold rounded-lg text-xs">Verify</button>
                                    </form>
                                @endif
                                <a href="{{ route('payments.show', $item['Payment_ID']) }}" class="text-blue-600 font-bold text-xs hover:underline ml-2">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/finance/payments/index.blade.php', $payIndex);

// 4. Student Billing Index
$stuBillIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'My Billing & Finance')
@section('content')
<div class="space-y-6">
    <x-page-header title="My Billing" description="View your outstanding bills and payment history." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Outstanding</h4>
            <p class="text-3xl font-black text-rose-600 mt-2">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Paid</h4>
            <p class="text-3xl font-black text-emerald-600 mt-2">Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-50"><h3 class="font-bold text-slate-800">My Invoices</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr><th class="px-6 py-4">Invoice ID</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Due Date</th><th class="px-6 py-4 text-center">Amount</th><th class="px-6 py-4 text-center">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($myInvoices as $item)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Category'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Due_Date'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $item['Status'] ?? 'Draft';
                                    $bg = $status == 'Paid' ? 'bg-emerald-100 text-emerald-700' : ($status == 'Waiting Payment' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('student.billing.show', $item['Invoice_ID']) }}" class="px-3 py-1 bg-blue-50 text-blue-600 font-bold rounded-lg hover:bg-blue-100 text-xs">Detail / Pay</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No invoices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/student/billing/index.blade.php', $stuBillIndex);

// 5. Student Billing Show
$stuBillShow = <<<'EOT'
@extends('layouts.app')
@section('header', 'Invoice Detail')
@section('content')
<div class="space-y-6">
    <x-page-header title="Invoice Details" description="View and pay your invoice." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => route('student.billing.index'), 'Invoice' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">{{ $invoice['Invoice_ID'] ?? '' }} | {{ $invoice['Category'] ?? '' }}</p>
                <h2 class="text-5xl font-black text-slate-800 my-4">Rp {{ number_format($invoice['Amount'] ?? 0, 0, ',', '.') }}</h2>
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-blue-100 text-blue-700">{{ $invoice['Status'] ?? '' }}</span>
                <p class="text-sm font-semibold text-slate-500 mt-4">Due on: {{ $invoice['Due_Date'] ?? '' }}</p>
            </div>
            
            @if(($invoice['Status'] ?? '') == 'Waiting Payment')
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Submit Payment</h3>
                <form action="{{ route('student.billing.pay', $invoice['Invoice_ID']) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Amount Paid (Rp)</label>
                            <input type="number" name="Amount_Paid" value="{{ $invoice['Amount'] ?? 0 }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Upload Proof of Payment</label>
                            <input type="file" name="Proof_File" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3" accept="image/*,.pdf">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl shadow-md transition-colors">Submit Payment for Verification</button>
                </form>
            </div>
            @endif
        </div>
        
        <div class="md:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Payment History</h3>
                <div class="space-y-4">
                    @forelse($relatedPayments as $pay)
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-500">{{ $pay['Payment_ID'] ?? '' }}</p>
                            <p class="text-lg font-black text-slate-800">Rp {{ number_format($pay['Amount_Paid'] ?? 0, 0, ',', '.') }}</p>
                            <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">{{ $pay['Payment_Date'] ?? '' }}</p>
                            <div class="mt-2">
                                <span class="px-2 py-1 text-[10px] font-bold rounded bg-blue-100 text-blue-700">{{ $pay['Status'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No payments recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/student/billing/show.blade.php', $stuBillShow);

echo "Views created.\n";
?>
