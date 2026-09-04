<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\TransactionService;
use App\Services\Finance\AccountService;
use App\Support\Reporting\HumanReadableResolver;

class TransactionController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Transaction_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $transactions = $this->transactionService->getAll();
        
        if ($request->filled('type')) {
            $type = $request->type;
            $transactions = $transactions->filter(function ($item) use ($type) {
                return strcasecmp(trim($item['Type'] ?? ''), $type) === 0;
            });
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

        $accountsById = collect($this->accountService->getAll())->flatMap(function ($account) {
            $keys = [];
            foreach (['Account_ID', 'Account_Code'] as $field) {
                $key = trim((string) ($account[$field] ?? ''));
                if ($key !== '') {
                    $keys[$key] = $account;
                }
            }

            return $keys;
        });
        
        return [
            'moduleName' => 'Transaksi (Transactions)',
            'data' => collect(array_values($transactions->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Akun', 'Tanggal', 'Tipe', 'Kategori', 'Nominal', 'Deskripsi'],
            'mapRow' => function($row) use ($accountsById) {
                return [
                    HumanReadableResolver::accountName(trim((string) ($row['Account_ID'] ?? '')) ?: ($row['Account_Code'] ?? ''), $accountsById),
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
            $type = $request->type;
            $transactions = $transactions->filter(function ($item) use ($type) {
                return strcasecmp(trim($item['Type'] ?? ''), $type) === 0;
            });
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

        $transactionGroups = $transactions
            ->groupBy(function ($transaction) {
                $date = $transaction['Transaction_Date'] ?? null;
                if (!$date) {
                    return 'NO_DATE';
                }

                try {
                    return \Carbon\Carbon::parse($date)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return 'NO_DATE';
                }
            })
            ->map(function ($group, $date) {
                $income = $group->filter(fn ($item) => strcasecmp($item['Type'] ?? '', 'Income') === 0)->sum('Amount');
                $expense = $group->filter(fn ($item) => strcasecmp($item['Type'] ?? '', 'Expense') === 0)->sum('Amount');

                return [
                    'id' => $date,
                    'title' => $date === 'NO_DATE' ? 'Tanpa Tanggal' : \Carbon\Carbon::parse($date)->format('d M Y'),
                    'total' => $group->count(),
                    'income' => (float) $income,
                    'expense' => (float) $expense,
                    'net' => (float) $income - (float) $expense,
                    'items' => $group->sortByDesc('Transaction_ID')->values(),
                ];
            })
            ->sortByDesc('id')
            ->values();

        $transactions = \App\Helpers\CollectionHelper::paginate($transactions, 15)->withQueryString();

        return view('finance.transactions.index', compact('transactions', 'transactionGroups'));
    }

    public function create()
    {
        $defaultAccount = $this->accountService->getDefaultTransactionAccount();
        if (!$defaultAccount) {
            return redirect()->route('transactions.index')->with('error', 'Data Akun Kas/Bank kosong atau tidak aktif di Ledger Master Account. Harap tambahkan Data Rekening/Kas terlebih dahulu.');
        }

        return view('finance.transactions.create', compact('defaultAccount'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'Transaction_Date' => 'required|date',
                'Type' => 'required|in:Income,Expense',
                'Category' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'Description' => 'nullable|string',
                'Reference_Type' => 'required|in:Invoice,Payment,Payroll,Adjustment,Other',
                'Reference_ID' => 'nullable|string'
            ]);

            $defaultAccount = $this->accountService->getDefaultTransactionAccount();
            if (!$defaultAccount) {
                throw new \Exception('Data Akun Kas/Bank kosong atau tidak aktif di Ledger Master Account. Harap tambahkan Data Rekening/Kas terlebih dahulu.');
            }

            $data = $request->except('_token');
            $data['Account_ID'] = $defaultAccount['Account_ID'];

            $this->transactionService->create($data);
            return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dicatat.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
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

        return view('finance.transactions.edit', compact('transaction', 'accounts'));
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
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->transactionService->delete($id);
            return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }
}
