<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\AccountService;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;

class AccountController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {
        $accounts = $this->accountService->getAll();
        
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $accounts = $accounts->filter(function($acc) use ($search) {
                return str_contains(strtolower($acc['Account_Code'] ?? ''), $search) ||
                        str_contains(strtolower($acc['Account_Name'] ?? ''), $search) ||
                        str_contains(strtolower($acc['Account_Category'] ?? ''), $search);
            })->values();
        }
        
        return [
            'moduleName' => 'Master Akun (Chart of Accounts)',
            'data' => collect(array_values($accounts->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Akun', 'Nama Akun', 'Kategori Akun', 'Normal Balance', 'Akun Induk', 'Keterangan', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Account_Code'] ?? '-', 
                    $row['Account_Name'] ?? '-', 
                    $row['Account_Category'] ?? '-', 
                    $row['Normal_Balance'] ?? 'DEBIT',
                    $row['Parent_Account_ID'] ?? '-', 
                    $row['Description'] ?? '-',
                    (($row['Is_Active'] ?? 'TRUE') === 'TRUE') ? 'Aktif' : 'Tidak Aktif'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Akun Master</td><td>: '.$accounts->count().'</td></tr>'
        ];
    }

    protected $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index(Request $request)
    {
        $accounts = $this->accountService->getAll();
        
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $accounts = $accounts->filter(function($acc) use ($search) {
                return str_contains(strtolower($acc['Account_Code'] ?? ''), $search) ||
                        str_contains(strtolower($acc['Account_Name'] ?? ''), $search) ||
                        str_contains(strtolower($acc['Account_Category'] ?? ''), $search);
            })->values();
        }

        $accounts = \App\Helpers\CollectionHelper::paginate($accounts, 15)->withQueryString();

        return view('finance.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $accounts = $this->accountService->getAll();
        return view('finance.accounts.create', compact('accounts'));
    }

    public function store(StoreAccountRequest $request)
    {
        try {
            $this->accountService->create($request->validated());
            return redirect()->route('accounts.index')->with('success', 'Master Akun berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $account = $this->accountService->getById($id);
        if (!$account) {
            return redirect()->route('accounts.index')->with('error', 'Akun tidak ditemukan.');
        }
        $accounts = $this->accountService->getAll()->where('Account_ID', '!=', $id);
        return view('finance.accounts.edit', compact('account', 'accounts'));
    }

    public function edit($id)
    {
        $account = $this->accountService->getById($id);
        if (!$account) {
            return redirect()->route('accounts.index')->with('error', 'Akun tidak ditemukan.');
        }
        $accounts = $this->accountService->getAll()->where('Account_ID', '!=', $id);
        return view('finance.accounts.edit', compact('account', 'accounts'));
    }

    public function update(UpdateAccountRequest $request, $id)
    {
        try {
            $this->accountService->update($id, $request->validated());
            return redirect()->route('accounts.index')->with('success', 'Master Akun berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->accountService->delete($id);
            return redirect()->route('accounts.index')->with('success', 'Master Akun berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
