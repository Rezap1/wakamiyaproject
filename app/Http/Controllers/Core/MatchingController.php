<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\MatchingService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreMatchingRequest;
use App\Http\Requests\UpdateMatchingRequest;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\InterviewRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MatchingController extends Controller
{
    protected MatchingService $matchingService;
    protected ActivityLogService $activityLogService;
    protected JobOrderRepositoryInterface $jobOrderRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected InterviewRepositoryInterface $interviewRepository;

    public function __construct(
        MatchingService $matchingService, 
        ActivityLogService $activityLogService,
        JobOrderRepositoryInterface $jobOrderRepository,
        StudentRepositoryInterface $studentRepository,
        InterviewRepositoryInterface $interviewRepository
    ) {
        $this->matchingService = $matchingService;
        $this->activityLogService = $activityLogService;
        $this->jobOrderRepository = $jobOrderRepository;
        $this->studentRepository = $studentRepository;
        $this->interviewRepository = $interviewRepository;
    }

    public function index()
    {
        try {
            $matchings = $this->matchingService->getAllMatchings();
            $matchings = collect($matchings)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $jobOrders = collect($this->jobOrderRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $interviews = collect($this->interviewRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('matchings.index', compact('matchings', 'jobOrders', 'students', 'interviews'));
        } catch (\Exception $e) {
            Log::error('Error fetching matchings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data Matching: ' . $e->getMessage());
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

            $interviews = collect($this->interviewRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('matchings.create', compact('jobOrders', 'students', 'interviews'));
        } catch (\Exception $e) {
            Log::error('Error loading create matching form: ' . $e->getMessage());
            return redirect()->route('matchings.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreMatchingRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->matchingService->createMatching($data, $currentUser);

            $this->activityLogService->log(
                'MATCHING',
                'CREATE',
                "Menambahkan data matching baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('matchings.index')->with('success', 'Data Matching berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating matching: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data Matching: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $matching = $this->matchingService->getMatchingById($id);

            if (!$matching || ($matching['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('matchings.index')->with('error', 'Data Matching tidak ditemukan.');
            }

            $this->activityLogService->log(
                'MATCHING',
                'VIEW',
                "Melihat detail matching: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('matchings.show', compact('matching'));
        } catch (\Exception $e) {
            Log::error('Error viewing matching: ' . $e->getMessage());
            return redirect()->route('matchings.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $matching = $this->matchingService->getMatchingById($id);

            if (!$matching || ($matching['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('matchings.index')->with('error', 'Data Matching tidak ditemukan.');
            }

            $jobOrders = collect($this->jobOrderRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            $interviews = collect($this->interviewRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('matchings.edit', compact('matching', 'jobOrders', 'students', 'interviews'));
        } catch (\Exception $e) {
            Log::error('Error loading edit matching form: ' . $e->getMessage());
            return redirect()->route('matchings.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateMatchingRequest $request, string $id)
    {
        try {
            $oldData = $this->matchingService->getMatchingById($id);
            if (!$oldData) {
                return redirect()->route('matchings.index')->with('error', 'Data Matching tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->matchingService->updateMatching($id, $data, $currentUser);

            $this->activityLogService->log(
                'MATCHING',
                'UPDATE',
                "Memperbarui data matching: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('matchings.index')->with('success', 'Data Matching berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating matching: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $matching = $this->matchingService->getMatchingById($id);
            if (!$matching) {
                return redirect()->route('matchings.index')->with('error', 'Data Matching tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->matchingService->deleteMatching($id, $currentUser);

            $this->activityLogService->log(
                'MATCHING',
                'DELETE',
                "Menghapus data matching: {$id}",
                $matching,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('matchings.index')->with('success', 'Data Matching berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting matching: ' . $e->getMessage());
            return redirect()->route('matchings.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
