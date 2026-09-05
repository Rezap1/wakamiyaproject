<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\TransactionService;
use App\Services\Finance\AccountService;
use App\Services\Finance\TransactionPresentationService;
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

        $presented = $this->transactionPresentationService->presentCollection($transactions);
        
        return [
            'moduleName' => 'Transaksi (Transactions)',
            'data' => $presented,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Tipe', 'Sumber', 'Pihak Terkait', 'Akun', 'Nominal', 'Status/Referensi'],
            'mapRow' => function($row) {
                return [
                    $row['date_label'] ?? '-',
                    $row['type_label'] ?? '-',
                    $row['source']['label'] ?? '-',
                    $row['party']['name'] ?? '-',
                    $row['account']['label'] ?? '-',
                    $row['amount_label'] ?? '-',
                    $row['source']['status_label'] ?? ($row['reference_label'] ?? '-'),
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$transactions->count().'</td></tr>'
        ];
    }

    protected $transactionService, $accountService, $transactionPresentationService;

    public function __construct(
        TransactionService $transactionService,
        AccountService $accountService,
        TransactionPresentationService $transactionPresentationService
    )
    {
        $this->transactionService = $transactionService;
        $this->accountService = $accountService;
        $this->transactionPresentationService = $transactionPresentationService;
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

        $presentedTransactions = $this->transactionPresentationService->presentCollection($transactions);

        $transactionGroups = $presentedTransactions
            ->groupBy(function ($transaction) {
                $date = $transaction['raw']['Transaction_Date'] ?? null;
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
                $income = $group->filter(fn ($item) => strcasecmp($item['type'] ?? '', 'Income') === 0)->sum('amount');
                $expense = $group->filter(fn ($item) => strcasecmp($item['type'] ?? '', 'Expense') === 0)->sum('amount');

                return [
                    'id' => $date,
                    'title' => $date === 'NO_DATE' ? 'Tanpa Tanggal' : \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j F Y'),
                    'total' => $group->count(),
                    'income' => (float) $income,
                    'expense' => (float) $expense,
                    'net' => (float) $income - (float) $expense,
                    'items' => $group->sortByDesc('transaction_id')->values(),
                ];
            })
            ->sortByDesc('id')
            ->values();

        $transactions = \App\Helpers\CollectionHelper::paginate($presentedTransactions, 15)->withQueryString();
        $canMutateTransactions = $this->canMutateTransactions();

        return view('finance.transactions.index', compact('transactions', 'transactionGroups', 'canMutateTransactions'));
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
        if (!$transaction || strtoupper(trim((string) ($transaction['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            abort(404, 'Transaksi tidak ditemukan.');
        }

        $presentation = $this->transactionPresentationService->presentDetail($transaction);
        $canAccessPayments = $this->canAccessPayments();
        $canAccessInvoices = $this->canAccessInvoices();
        $canMutateTransactions = $this->canMutateTransactions();

        return view('finance.transactions.show', compact('presentation', 'canAccessPayments', 'canAccessInvoices', 'canMutateTransactions'));
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

    private function canAccessPayments(): bool
    {
        return in_array($this->authenticatedRoleName(), ['MASTER', 'ADMINISTRATOR', 'FINANCE'], true);
    }

    private function canAccessInvoices(): bool
    {
        return in_array($this->authenticatedRoleName(), ['MASTER', 'ADMINISTRATOR', 'FINANCE'], true);
    }

    private function canMutateTransactions(): bool
    {
        return in_array($this->authenticatedRoleName(), ['MASTER', 'ADMINISTRATOR', 'FINANCE'], true);
    }

    private function authenticatedRoleName(): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $role = trim((string) ($user->Role ?? $user->Role_Name ?? ''));
        if ($role !== '') {
            return strtoupper($role);
        }

        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return '';
        }

        try {
            return strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)->getRoleById($roleId)['Role_Name'] ?? '')));
        } catch (\Throwable) {
            return '';
        }
    }
}
