<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\CoeService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreCoeRequest;
use App\Http\Requests\UpdateCoeRequest;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoeController extends Controller
{
    protected CoeService $coeService;
    protected ActivityLogService $activityLogService;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected CompanyRepositoryInterface $companyRepository;

    public function __construct(
        CoeService $coeService, 
        ActivityLogService $activityLogService,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository
    ) {
        $this->coeService = $coeService;
        $this->activityLogService = $activityLogService;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
    }

    public function index()
    {
        try {
            $coes = $this->coeService->getAllCoes();
            $coes = collect($coes)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $applications = collect($this->applicationRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $companies = collect($this->companyRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('coes.index', compact('coes', 'applications', 'students', 'companies'));
        } catch (\Exception $e) {
            Log::error('Error fetching coes: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data COE: ' . $e->getMessage());
        }
    }

    public function create()
    {
        try {
            $applications = collect($this->applicationRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $companies = collect($this->companyRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('coes.create', compact('applications', 'students', 'companies'));
        } catch (\Exception $e) {
            Log::error('Error loading create coe form: ' . $e->getMessage());
            return redirect()->route('coes.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreCoeRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->coeService->createCoe($data, $currentUser);

            $this->activityLogService->log(
                'COE',
                'CREATE',
                "Menambahkan data COE baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('coes.index')->with('success', 'Data COE berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating coe: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data COE: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $coe = $this->coeService->getCoeById($id);

            if (!$coe || ($coe['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('coes.index')->with('error', 'Data COE tidak ditemukan.');
            }

            $this->activityLogService->log(
                'COE',
                'VIEW',
                "Melihat detail COE: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('coes.show', compact('coe'));
        } catch (\Exception $e) {
            Log::error('Error viewing coe: ' . $e->getMessage());
            return redirect()->route('coes.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $coe = $this->coeService->getCoeById($id);

            if (!$coe || ($coe['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('coes.index')->with('error', 'Data COE tidak ditemukan.');
            }

            $applications = collect($this->applicationRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $companies = collect($this->companyRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('coes.edit', compact('coe', 'applications', 'students', 'companies'));
        } catch (\Exception $e) {
            Log::error('Error loading edit coe form: ' . $e->getMessage());
            return redirect()->route('coes.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateCoeRequest $request, string $id)
    {
        try {
            $oldData = $this->coeService->getCoeById($id);
            if (!$oldData) {
                return redirect()->route('coes.index')->with('error', 'Data COE tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->coeService->updateCoe($id, $data, $currentUser);

            $this->activityLogService->log(
                'COE',
                'UPDATE',
                "Memperbarui data COE: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('coes.index')->with('success', 'Data COE berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating coe: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $coe = $this->coeService->getCoeById($id);
            if (!$coe) {
                return redirect()->route('coes.index')->with('error', 'Data COE tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->coeService->deleteCoe($id, $currentUser);

            $this->activityLogService->log(
                'COE',
                'DELETE',
                "Menghapus data COE: {$id}",
                $coe,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('coes.index')->with('success', 'Data COE berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting coe: ' . $e->getMessage());
            return redirect()->route('coes.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
