<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Services\Core\ProgramService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProgramController extends Controller
{
    protected $programService;
    protected $activityLogService;

    public function __construct(
        ProgramService $programService,
        ActivityLogService $activityLogService
    ) {
        $this->programService = $programService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $programs = $this->programService->getAllPrograms();

            // Pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $programs->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $programsPaginated = new LengthAwarePaginator($currentItems, count($programs), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('programs.index', [
                'programs' => $programsPaginated
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching programs: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master program dari Google Sheets.');
        }
    }

    public function create()
    {
        return view('programs.create');
    }

    public function store(StoreProgramRequest $request)
    {
        try {
            $data = $request->validated();
            $program = $this->programService->createProgram($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_PROGRAM',
                "Membuat program baru: {$program['Program_ID']} - {$program['Program_Name']}",
                $request->ip(),
                null,
                $program,
                $request->userAgent()
            );

            return redirect()->route('programs.index')->with('success', 'Program berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating program: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            return view('programs.show', compact('program'));
        } catch (\Exception $e) {
            Log::error('Error showing program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat memuat data program.');
        }
    }

    public function edit($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            return view('programs.edit', compact('program'));
        } catch (\Exception $e) {
            Log::error('Error editing program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat memuat form edit program.');
        }
    }

    public function update(UpdateProgramRequest $request, $id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            $data = $request->validated();
            $this->programService->updateProgram($id, $data);
            
            $updatedProgram = $this->programService->getProgramById($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_PROGRAM',
                "Memperbarui data program: {$id}",
                $request->ip(),
                $program,
                $updatedProgram,
                $request->userAgent()
            );

            return redirect()->route('programs.index')->with('success', 'Data program berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating program: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            $this->programService->deleteProgram($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_PROGRAM',
                "Menonaktifkan program (Soft Delete): {$id}",
                request()->ip(),
                $program,
                array_merge($program, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('programs.index')->with('success', 'Data program berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat menghapus data program.');
        }
    }
}
