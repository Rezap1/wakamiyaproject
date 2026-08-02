<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\TransactionService;
use App\Services\Finance\AccountService;

class TransactionController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Transaction_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $transactions = $this->transactionService->getAll();
        
        if ($request->filled('type')) {
            $transactions = $transactions->where('Type', $request->type);
        }

        if ($request->filled('date_from')) {
            $transactions = $transactions->filter(function ($item) use ($request) {
                return isset($item['Transaction_Date']) && \Carbon\Carbon::parse($item['Transaction_Date']) >= \Carbon\Carbon::parse($request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $transactions = $transactions->filter(function ($item) use ($request) {
                return isset($item['Transaction_Date']) && \Carbon\Carbon::parse($item['Transaction_Date']) <= \Carbon\Carbon::parse($request->date_to);
            });
        }
        
        return [
            'moduleName' => 'Transaksi (Transactions)',
            'data' => collect(array_values($transactions->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Transaksi', 'Akun', 'Tanggal', 'Tipe', 'Kategori', 'Nominal', 'Deskripsi'],
            'mapRow' => function($row) {
                return [
                    $row['Transaction_ID'] ?? '-', 
                    $row['Account_Code'] ?? '-', 
                    isset($row['Transaction_Date']) ? \Carbon\Carbon::parse($row['Transaction_Date'])->format('d M Y') : '-',
                    $row['Type'] ?? '-',
                    $row['Category'] ?? '-',
                    'Rp ' . number_format($row['Amount'] ?? 0, 0, ',', '.'),
                    $row['Description'] ?? '-'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$transactions->count().'</td></tr>'
        ];
    }

    protected $transactionService, $accountService;

    public function __construct(TransactionService $transactionService, AccountService $accountService)
    {
        $this->transactionService = $transactionService;
        $this->accountService = $accountService;
    }

    public function index(Request $request)
    {
        $transactions = $this->transactionService->getAll();
        
        if ($request->filled('type')) {
            $transactions = $transactions->where('Type', $request->type);
        }

        if ($request->filled('date_from')) {
            $transactions = $transactions->filter(function ($item) use ($request) {
                return isset($item['Transaction_Date']) && \Carbon\Carbon::parse($item['Transaction_Date']) >= \Carbon\Carbon::parse($request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $transactions = $transactions->filter(function ($item) use ($request) {
                return isset($item['Transaction_Date']) && \Carbon\Carbon::parse($item['Transaction_Date']) <= \Carbon\Carbon::parse($request->date_to);
            });
        }

        $transactions = \App\Helpers\CollectionHelper::paginate($transactions, 15)->withQueryString();

        return view('finance.transactions.index', compact('transactions'));
    }

    public function create()
    {
        $accounts = $this->accountService->getAll();
        
        $categories = [
            'Tuition',
            'Registration',
            'Training',
            'Corporate',
            'Salary',
            'Operational',
            'Other'
        ];

        return view('finance.transactions.create', compact('accounts', 'categories'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'Transaction_Date' => 'required|date',
                'Account_ID' => 'required|string',
                'Type' => 'required|in:Income,Expense',
                'Category' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'Description' => 'nullable|string',
                'Reference_Type' => 'required|in:Invoice,Payment,Payroll,Adjustment,Other',
                'Reference_ID' => 'nullable|string'
            ]);

            $this->transactionService->create($request->except('_token'));
            return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dicatat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $transaction = $this->transactionService->getById($id);
        if (!$transaction) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan.');
        }
        $account = $this->accountService->getById($transaction['Account_ID']);
        return view('finance.transactions.show', compact('transaction', 'account'));
    }

    public function edit($id)
    {
        $transaction = $this->transactionService->getById($id);
        if (!$transaction) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan.');
        }
        $accounts = $this->accountService->getAll();
        
        $categories = [
            'Tuition',
            'Registration',
            'Training',
            'Corporate',
            'Salary',
            'Operational',
            'Other'
        ];

        return view('finance.transactions.edit', compact('transaction', 'accounts', 'categories'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'Transaction_Date' => 'required|date',
                'Account_ID' => 'required|string',
                'Type' => 'required|in:Income,Expense',
                'Category' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'Description' => 'nullable|string',
                'Reference_Type' => 'required|in:Invoice,Payment,Payroll,Adjustment,Other',
                'Reference_ID' => 'nullable|string'
            ]);

            $this->transactionService->update($id, $request->except(['_token', '_method']));
            return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil diupdate.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->transactionService->delete($id);
            return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
