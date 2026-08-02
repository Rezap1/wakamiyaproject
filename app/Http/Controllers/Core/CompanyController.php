<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Services\Core\CompanyService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $companies = $this->companyService->getAllCompanies();

        $search = $request->input('search');
        if (!empty($search)) {
            $companies = \App\Helpers\CollectionHelper::search($companies, $search, ['Company_Code', 'Company_Name', 'Company_Email', 'Company_Phone']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $companies = $companies->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'Mitra Perusahaan (Company)',
            'data' => collect(array_values($companies->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Perusahaan', 'Nama Perusahaan', 'Email', 'Telepon', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Company_Code'] ?? '-',
                    $row['Company_Name'] ?? '-',
                    $row['Email'] ?? '-',
                    $row['Phone_Number'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$companies->count().'</td></tr>'
        ];
    }

    protected $companyService;
    protected $activityLogService;

    public function __construct(
        CompanyService $companyService,
        ActivityLogService $activityLogService
    ) {
        $this->companyService = $companyService;
        $this->activityLogService = $activityLogService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $companies = $this->companyService->getAllCompanies();

            $search = $request->input('search');
            if (!empty($search)) {
                $companies = \App\Helpers\CollectionHelper::search($companies, $search, ['Company_ID', 'Company_Code', 'Company_Name', 'Company_Email', 'Company_Phone']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $companies = $companies->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Pagination
            $companiesPaginated = \App\Helpers\CollectionHelper::paginate($companies, 10)->withQueryString();

            return view('companies.index', [
                'companies' => $companiesPaginated
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching companies: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master perusahaan dari Google Sheets.');
        }
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Add file instances to data if uploaded
            if ($request->hasFile('Company_Logo')) {
                $data['Company_Logo'] = $request->file('Company_Logo');
            }
            if ($request->hasFile('Company_Stamp')) {
                $data['Company_Stamp'] = $request->file('Company_Stamp');
            }

            $company = $this->companyService->createCompany($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_COMPANY',
                "Mendaftarkan perusahaan baru: {$company['Company_Code']} - {$company['Company_Name']}",
                $request->ip(),
                null,
                $company,
                $request->userAgent()
            );

            return redirect()->route('companies.index')->with('success', 'Data Perusahaan berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating company: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $company = $this->companyService->getCompanyById($id);
            if (!$company) {
                return redirect()->route('companies.index')->with('error', 'Data perusahaan tidak ditemukan.');
            }

            return view('companies.show', compact('company'));
        } catch (\Exception $e) {
            Log::error('Error showing company: ' . $e->getMessage());
            return redirect()->route('companies.index')->with('error', 'Terjadi kesalahan saat memuat profil perusahaan.');
        }
    }

    public function edit($id)
    {
        try {
            $company = $this->companyService->getCompanyById($id);
            if (!$company) {
                return redirect()->route('companies.index')->with('error', 'Data perusahaan tidak ditemukan.');
            }

            return view('companies.edit', compact('company'));
        } catch (\Exception $e) {
            Log::error('Error editing company: ' . $e->getMessage());
            return redirect()->route('companies.index')->with('error', 'Terjadi kesalahan saat memuat form edit perusahaan.');
        }
    }

    public function update(UpdateCompanyRequest $request, $id)
    {
        try {
            $company = $this->companyService->getCompanyById($id);
            if (!$company) {
                return redirect()->route('companies.index')->with('error', 'Data perusahaan tidak ditemukan.');
            }

            $data = $request->validated();
            
            // Add file instances to data if uploaded
            if ($request->hasFile('Company_Logo')) {
                $data['Company_Logo'] = $request->file('Company_Logo');
            }
            if ($request->hasFile('Company_Stamp')) {
                $data['Company_Stamp'] = $request->file('Company_Stamp');
            }

            $this->companyService->updateCompany($id, $data);
            
            $updatedCompany = $this->companyService->getCompanyById($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_COMPANY',
                "Memperbarui profil perusahaan: {$id}",
                $request->ip(),
                $company,
                $updatedCompany,
                $request->userAgent()
            );

            return redirect()->route('companies.index')->with('success', 'Profil perusahaan berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating company: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $company = $this->companyService->getCompanyById($id);
            if (!$company) {
                return redirect()->route('companies.index')->with('error', 'Data perusahaan tidak ditemukan.');
            }

            $this->companyService->deleteCompany($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_COMPANY',
                "Menonaktifkan perusahaan (Hard Delete): {$id}",
                request()->ip(),
                $company,
                array_merge($company, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('companies.index')->with('success', 'Data perusahaan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting company: ' . $e->getMessage());
            return redirect()->route('companies.index')->with('error', 'Terjadi kesalahan saat menghapus data perusahaan.');
        }
    }
}
