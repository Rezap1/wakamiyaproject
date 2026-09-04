<div class="my-6">
    <div class="text-center mb-8 border-b-2 border-slate-800 pb-4">
        <h2 class="text-xl font-black text-slate-900 tracking-wider uppercase">SALARY SLIP</h2>
        <p class="text-sm font-bold text-slate-600 mt-1">Period: {{ \Carbon\Carbon::parse($document['Generated_At'] ?? now())->format('F Y') }}</p>
    </div>

    <!-- Employee Information -->
    <div class="mb-8">
        <table class="w-full text-sm">
            <tr>
                <td class="py-1 w-1/4 font-bold text-slate-700">No. Pegawai</td>
                <td class="py-1 w-1/4">: {{ $document['Employee_Number'] ?? $document['NIP'] ?? '-' }}</td>
                <td class="py-1 w-1/4 font-bold text-slate-700">Department</td>
                <td class="py-1 w-1/4">: {{ $document['Department'] ?? 'General' }}</td>
            </tr>
            <tr>
                <td class="py-1 font-bold text-slate-700">Nama</td>
                <td class="py-1">: {{ $document['Title'] ?? 'Employee Name' }}</td>
                <td class="py-1 font-bold text-slate-700">Position</td>
                <td class="py-1">: {{ $document['Role'] ?? 'Staff' }}</td>
            </tr>
        </table>
    </div>

    <!-- Salary Details -->
    <div class="flex gap-8 mb-8">
        <!-- Earnings -->
        <div class="flex-1">
            <h3 class="font-bold border-b border-slate-300 pb-2 mb-2 text-slate-800 uppercase text-xs">Earnings</h3>
            <table class="w-full text-sm">
                <tr>
                    <td class="py-1 text-slate-700">Basic Salary</td>
                    <td class="py-1 text-right font-mono">Rp 5.000.000</td>
                </tr>
                <tr>
                    <td class="py-1 text-slate-700">Transport Allowance</td>
                    <td class="py-1 text-right font-mono">Rp 500.000</td>
                </tr>
                <tr>
                    <td class="py-1 text-slate-700">Meal Allowance</td>
                    <td class="py-1 text-right font-mono">Rp 500.000</td>
                </tr>
                <tr>
                    <td class="py-2 font-bold text-slate-900 border-t border-slate-200 mt-1">Total Earnings (A)</td>
                    <td class="py-2 text-right font-bold font-mono border-t border-slate-200 mt-1">Rp 6.000.000</td>
                </tr>
            </table>
        </div>

        <!-- Deductions -->
        <div class="flex-1">
            <h3 class="font-bold border-b border-slate-300 pb-2 mb-2 text-slate-800 uppercase text-xs">Deductions</h3>
            <table class="w-full text-sm">
                <tr>
                    <td class="py-1 text-slate-700">BPJS Kesehatan (1%)</td>
                    <td class="py-1 text-right font-mono text-red-600">Rp 50.000</td>
                </tr>
                <tr>
                    <td class="py-1 text-slate-700">BPJS Ketenagakerjaan (2%)</td>
                    <td class="py-1 text-right font-mono text-red-600">Rp 100.000</td>
                </tr>
                <tr>
                    <td class="py-1 text-slate-700">PPh 21 Tax</td>
                    <td class="py-1 text-right font-mono text-red-600">Rp 150.000</td>
                </tr>
                <tr>
                    <td class="py-2 font-bold text-slate-900 border-t border-slate-200 mt-1">Total Deductions (B)</td>
                    <td class="py-2 text-right font-bold font-mono text-red-600 border-t border-slate-200 mt-1">Rp 300.000</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Take Home Pay -->
    <div class="bg-slate-100 p-4 border border-slate-300 rounded text-center">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Take Home Pay (A - B)</p>
        <h3 class="text-2xl font-black text-emerald-700 font-mono">Rp 5.700.000</h3>
        <p class="text-xs text-slate-500 italic mt-1">Five Million Seven Hundred Thousand Rupiah</p>
    </div>
    
    <div class="mt-8 text-xs text-slate-500 italic text-justify leading-relaxed">
        This is a computer-generated document. No physical signature is required unless stated otherwise. 
        For any discrepancies, please contact the HR Department within 3 working days from the generated date.
    </div>
</div>



