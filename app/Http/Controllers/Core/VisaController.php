<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\VisaService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreVisaRequest;
use App\Http\Requests\UpdateVisaRequest;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CoeRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VisaController extends Controller
{
    protected VisaService $visaService;
    protected ActivityLogService $activityLogService;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected CoeRepositoryInterface $coeRepository;

    public function __construct(
        VisaService $visaService, 
        ActivityLogService $activityLogService,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository,
        CoeRepositoryInterface $coeRepository
    ) {
        $this->visaService = $visaService;
        $this->activityLogService = $activityLogService;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
        $this->coeRepository = $coeRepository;
    }

    public function index()
    {
        try {
            $visas = $this->visaService->getAllVisas();
            $visas = collect($visas)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $applications = collect($this->applicationRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $coes = collect($this->coeRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('visas.index', compact('visas', 'applications', 'students', 'coes'));
        } catch (\Exception $e) {
            Log::error('Error fetching visas: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data VISA: ' . $e->getMessage());
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

            $coes = collect($this->coeRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('visas.create', compact('applications', 'students', 'coes'));
        } catch (\Exception $e) {
            Log::error('Error loading create visa form: ' . $e->getMessage());
            return redirect()->route('visas.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreVisaRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->visaService->createVisa($data, $currentUser);

            $this->activityLogService->log(
                'VISA',
                'CREATE',
                "Menambahkan data VISA baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('visas.index')->with('success', 'Data VISA berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating visa: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data VISA: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $visa = $this->visaService->getVisaById($id);

            if (!$visa || ($visa['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('visas.index')->with('error', 'Data VISA tidak ditemukan.');
            }

            $this->activityLogService->log(
                'VISA',
                'VIEW',
                "Melihat detail VISA: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('visas.show', compact('visa'));
        } catch (\Exception $e) {
            Log::error('Error viewing visa: ' . $e->getMessage());
            return redirect()->route('visas.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $visa = $this->visaService->getVisaById($id);

            if (!$visa || ($visa['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('visas.index')->with('error', 'Data VISA tidak ditemukan.');
            }

            $applications = collect($this->applicationRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $coes = collect($this->coeRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('visas.edit', compact('visa', 'applications', 'students', 'coes'));
        } catch (\Exception $e) {
            Log::error('Error loading edit visa form: ' . $e->getMessage());
            return redirect()->route('visas.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateVisaRequest $request, string $id)
    {
        try {
            $oldData = $this->visaService->getVisaById($id);
            if (!$oldData) {
                return redirect()->route('visas.index')->with('error', 'Data VISA tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->visaService->updateVisa($id, $data, $currentUser);

            $this->activityLogService->log(
                'VISA',
                'UPDATE',
                "Memperbarui data VISA: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('visas.index')->with('success', 'Data VISA berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating visa: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $visa = $this->visaService->getVisaById($id);
            if (!$visa) {
                return redirect()->route('visas.index')->with('error', 'Data VISA tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->visaService->deleteVisa($id, $currentUser);

            $this->activityLogService->log(
                'VISA',
                'DELETE',
                "Menghapus data VISA: {$id}",
                $visa,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('visas.index')->with('success', 'Data VISA berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting visa: ' . $e->getMessage());
            return redirect()->route('visas.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
