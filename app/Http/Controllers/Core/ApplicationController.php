<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\ApplicationService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\MatchingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    protected ApplicationService $applicationService;
    protected ActivityLogService $activityLogService;
    protected JobOrderRepositoryInterface $jobOrderRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected MatchingRepositoryInterface $matchingRepository;

    public function __construct(
        ApplicationService $applicationService, 
        ActivityLogService $activityLogService,
        JobOrderRepositoryInterface $jobOrderRepository,
        StudentRepositoryInterface $studentRepository,
        MatchingRepositoryInterface $matchingRepository
    ) {
        $this->applicationService = $applicationService;
        $this->activityLogService = $activityLogService;
        $this->jobOrderRepository = $jobOrderRepository;
        $this->studentRepository = $studentRepository;
        $this->matchingRepository = $matchingRepository;
    }

    public function index()
    {
        try {
            $applications = $this->applicationService->getAllApplications();
            $applications = collect($applications)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $jobOrders = collect($this->jobOrderRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $matchings = collect($this->matchingRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('applications.index', compact('applications', 'jobOrders', 'students', 'matchings'));
        } catch (\Exception $e) {
            Log::error('Error fetching applications: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data Application: ' . $e->getMessage());
        }
    }

    public function create()
    {
        try {
            $jobOrders = collect($this->jobOrderRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $matchings = collect($this->matchingRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('applications.create', compact('jobOrders', 'students', 'matchings'));
        } catch (\Exception $e) {
            Log::error('Error loading create application form: ' . $e->getMessage());
            return redirect()->route('applications.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreApplicationRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->applicationService->createApplication($data, $currentUser);

            $this->activityLogService->log(
                'APPLICATION',
                'CREATE',
                "Menambahkan data aplikasi kerja baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('applications.index')->with('success', 'Data Aplikasi Kerja berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating application: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data Aplikasi Kerja: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $application = $this->applicationService->getApplicationById($id);

            if (!$application || ($application['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('applications.index')->with('error', 'Data Aplikasi Kerja tidak ditemukan.');
            }

            $this->activityLogService->log(
                'APPLICATION',
                'VIEW',
                "Melihat detail aplikasi kerja: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('applications.show', compact('application'));
        } catch (\Exception $e) {
            Log::error('Error viewing application: ' . $e->getMessage());
            return redirect()->route('applications.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $application = $this->applicationService->getApplicationById($id);

            if (!$application || ($application['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('applications.index')->with('error', 'Data Aplikasi Kerja tidak ditemukan.');
            }

            $jobOrders = collect($this->jobOrderRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $matchings = collect($this->matchingRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('applications.edit', compact('application', 'jobOrders', 'students', 'matchings'));
        } catch (\Exception $e) {
            Log::error('Error loading edit application form: ' . $e->getMessage());
            return redirect()->route('applications.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateApplicationRequest $request, string $id)
    {
        try {
            $oldData = $this->applicationService->getApplicationById($id);
            if (!$oldData) {
                return redirect()->route('applications.index')->with('error', 'Data Aplikasi Kerja tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->applicationService->updateApplication($id, $data, $currentUser);

            $this->activityLogService->log(
                'APPLICATION',
                'UPDATE',
                "Memperbarui data aplikasi kerja: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('applications.index')->with('success', 'Data Aplikasi Kerja berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating application: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $application = $this->applicationService->getApplicationById($id);
            if (!$application) {
                return redirect()->route('applications.index')->with('error', 'Data Aplikasi Kerja tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->applicationService->deleteApplication($id, $currentUser);

            $this->activityLogService->log(
                'APPLICATION',
                'DELETE',
                "Menghapus data aplikasi kerja: {$id}",
                $application,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('applications.index')->with('success', 'Data Aplikasi Kerja berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting application: ' . $e->getMessage());
            return redirect()->route('applications.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
