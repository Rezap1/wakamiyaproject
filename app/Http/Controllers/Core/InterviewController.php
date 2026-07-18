<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\InterviewService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InterviewController extends Controller
{
    protected InterviewService $interviewService;
    protected ActivityLogService $activityLogService;
    protected JobOrderRepositoryInterface $jobOrderRepository;
    protected StudentRepositoryInterface $studentRepository;

    public function __construct(
        InterviewService $interviewService, 
        ActivityLogService $activityLogService,
        JobOrderRepositoryInterface $jobOrderRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->interviewService = $interviewService;
        $this->activityLogService = $activityLogService;
        $this->jobOrderRepository = $jobOrderRepository;
        $this->studentRepository = $studentRepository;
    }

    public function index()
    {
        try {
            $interviews = $this->interviewService->getAllInterviews();
            $interviews = collect($interviews)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $jobOrders = collect($this->jobOrderRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('interviews.index', compact('interviews', 'jobOrders', 'students'));
        } catch (\Exception $e) {
            Log::error('Error fetching interviews: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data Interview: ' . $e->getMessage());
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

            return view('interviews.create', compact('jobOrders', 'students'));
        } catch (\Exception $e) {
            Log::error('Error loading create interview form: ' . $e->getMessage());
            return redirect()->route('interviews.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreInterviewRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->interviewService->createInterview($data, $currentUser);

            $this->activityLogService->log(
                'INTERVIEW',
                'CREATE',
                "Menambahkan data interview baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('interviews.index')->with('success', 'Data Interview berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating interview: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data Interview: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $interview = $this->interviewService->getInterviewById($id);

            if (!$interview || ($interview['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('interviews.index')->with('error', 'Data Interview tidak ditemukan.');
            }

            $this->activityLogService->log(
                'INTERVIEW',
                'VIEW',
                "Melihat detail interview: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('interviews.show', compact('interview'));
        } catch (\Exception $e) {
            Log::error('Error viewing interview: ' . $e->getMessage());
            return redirect()->route('interviews.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $interview = $this->interviewService->getInterviewById($id);

            if (!$interview || ($interview['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('interviews.index')->with('error', 'Data Interview tidak ditemukan.');
            }

            $jobOrders = collect($this->jobOrderRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('interviews.edit', compact('interview', 'jobOrders', 'students'));
        } catch (\Exception $e) {
            Log::error('Error loading edit interview form: ' . $e->getMessage());
            return redirect()->route('interviews.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateInterviewRequest $request, string $id)
    {
        try {
            $oldData = $this->interviewService->getInterviewById($id);
            if (!$oldData) {
                return redirect()->route('interviews.index')->with('error', 'Data Interview tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            $this->interviewService->updateInterview($id, $data, $currentUser);

            $this->activityLogService->log(
                'INTERVIEW',
                'UPDATE',
                "Memperbarui data interview: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('interviews.index')->with('success', 'Data Interview berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating interview: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $interview = $this->interviewService->getInterviewById($id);
            if (!$interview) {
                return redirect()->route('interviews.index')->with('error', 'Data Interview tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->interviewService->deleteInterview($id, $currentUser);

            $this->activityLogService->log(
                'INTERVIEW',
                'DELETE',
                "Menghapus data interview: {$id}",
                $interview,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('interviews.index')->with('success', 'Data Interview berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting interview: ' . $e->getMessage());
            return redirect()->route('interviews.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
