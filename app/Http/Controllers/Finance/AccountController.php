<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\AccountService;

class AccountController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $accounts = $this->accountService->getAll();
        
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $accounts = $accounts->filter(function($acc) use ($search) {
                return str_contains(strtolower($acc['Account_Code'] ?? ''), $search) ||
                        str_contains(strtolower($acc['Account_Name'] ?? ''), $search);
            })->values();
        }
        
        return [
            'moduleName' => 'Master Akun (Accounts)',
            'data' => collect(array_values($accounts->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Akun', 'Nama Akun', 'Kategori', 'Keterangan', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Account_Code'] ?? '-', 
                    $row['Account_Name'] ?? '-', 
                    $row['Account_Category'] ?? '-', 
                    $row['Description'] ?? '-',
                    $row['Is_Active'] ? 'Aktif' : 'Tidak Aktif'
                ];
            },
            'isLandscape' => false,
            'summary' => '<tr><td>Total Data</td><td>: '.$accounts->count().'</td></tr>'
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
                        str_contains(strtolower($acc['Account_Name'] ?? ''), $search);
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

    public function store(Request $request)
    {
        try {
            $request->validate([
                'Account_Code' => 'required|string|max:50',
                'Account_Name' => 'required|string|max:255',
                'Account_Category' => 'required|string',
                'Parent_Account_ID' => 'nullable|string',
                'Description' => 'nullable|string'
            ]);

            $this->accountService->create($request->except('_token'));
            return redirect()->route('accounts.index')->with('success', 'Master Account berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
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

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'Account_Code' => 'required|string|max:50',
                'Account_Name' => 'required|string|max:255',
                'Account_Category' => 'required|string',
                'Parent_Account_ID' => 'nullable|string',
                'Description' => 'nullable|string'
            ]);

            $this->accountService->update($id, $request->except(['_token', '_method']));
            return redirect()->route('accounts.index')->with('success', 'Master Account berhasil diupdate.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->accountService->delete($id);
            return redirect()->route('accounts.index')->with('success', 'Master Account berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
