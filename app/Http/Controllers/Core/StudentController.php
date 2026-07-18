<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Services\Core\StudentService;
use App\Services\Core\ProgramService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    protected $studentService;
    protected $programService;
    protected $batchService;
    protected $classService;
    protected $activityLogService;

    public function __construct(
        StudentService $studentService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService,
        ActivityLogService $activityLogService
    ) {
        $this->studentService = $studentService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $students = $this->studentService->getAllStudents();
            $programs = $this->programService->getAllPrograms();
            $batches = $this->batchService->getAllBatches();
            $classes = $this->classService->getAllClasses();

            // Mapping Names to Students for display
            $students = $students->map(function ($student) use ($programs, $batches, $classes) {
                $program = $programs->firstWhere('Program_ID', $student['Program_ID']);
                $batch = $batches->firstWhere('Batch_ID', $student['Batch_ID']);
                $class = $classes->firstWhere('Class_ID', $student['Class_ID']);

                $student['Program_Name'] = $program ? $program['Program_Name'] : 'Tidak Ditemukan';
                $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : 'Tidak Ditemukan';
                $student['Class_Name'] = $class ? $class['Class_Name'] : 'Tidak Ditemukan';
                
                return $student;
            });

            // Pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $students->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $studentsPaginated = new LengthAwarePaginator($currentItems, count($students), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            // For filter
            $activePrograms = $programs->where('Is_Active', 'TRUE')->values();
            $activeBatches = $batches->where('Is_Active', 'TRUE')->values();
            $activeClasses = $classes->where('Is_Active', 'TRUE')->values();

            return view('students.index', [
                'students' => $studentsPaginated,
                'programs' => $activePrograms,
                'batches' => $activeBatches,
                'classes' => $activeClasses
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching students: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master siswa dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $classes = $this->classService->getAllClasses()->where('Is_Active', 'TRUE')->values();
            
            return view('students.create', compact('programs', 'batches', 'classes'));
        } catch (\Exception $e) {
            Log::error('Error loading create student form: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Gagal memuat form pendaftaran siswa.');
        }
    }

    public function store(StoreStudentRequest $request)
    {
        try {
            $data = $request->validated();
            $student = $this->studentService->createStudent($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_STUDENT',
                "Mendaftarkan siswa baru: {$student['Student_ID']} - {$student['Full_Name']}",
                $request->ip(),
                null,
                $student,
                $request->userAgent()
            );

            return redirect()->route('students.index')->with('success', 'Siswa berhasil didaftarkan.');
        } catch (\Exception $e) {
            Log::error('Error creating student: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (!$student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $program = $this->programService->getProgramById($student['Program_ID']);
            $batch = $this->batchService->getBatchById($student['Batch_ID']);
            $class = $this->classService->getClassById($student['Class_ID']);

            $student['Program_Name'] = $program ? $program['Program_Name'] : 'Tidak Ditemukan';
            $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : 'Tidak Ditemukan';
            $student['Class_Name'] = $class ? $class['Class_Name'] : 'Tidak Ditemukan';

            return view('students.show', compact('student'));
        } catch (\Exception $e) {
            Log::error('Error showing student: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat memuat profil siswa.');
        }
    }

    public function edit($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (!$student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $classes = $this->classService->getAllClasses()->where('Is_Active', 'TRUE')->values();
            
            // Include inactive ones if currently selected
            $currentProgram = $this->programService->getProgramById($student['Program_ID']);
            if ($currentProgram && ($currentProgram['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $programs->push($currentProgram);
            }
            
            $currentBatch = $this->batchService->getBatchById($student['Batch_ID']);
            if ($currentBatch && ($currentBatch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $batches->push($currentBatch);
            }
            
            $currentClass = $this->classService->getClassById($student['Class_ID']);
            if ($currentClass && ($currentClass['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $classes->push($currentClass);
            }

            return view('students.edit', compact('student', 'programs', 'batches', 'classes'));
        } catch (\Exception $e) {
            Log::error('Error editing student: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat memuat form edit siswa.');
        }
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (!$student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $data = $request->validated();
            $this->studentService->updateStudent($id, $data);
            
            $updatedStudent = $this->studentService->getStudentById($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_STUDENT',
                "Memperbarui data profil siswa: {$id}",
                $request->ip(),
                $student,
                $updatedStudent,
                $request->userAgent()
            );

            return redirect()->route('students.index')->with('success', 'Data profil siswa berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating student: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (!$student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $this->studentService->deleteStudent($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_STUDENT',
                "Menonaktifkan data siswa (Soft Delete): {$id}",
                request()->ip(),
                $student,
                array_merge($student, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('students.index')->with('success', 'Data siswa berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting student: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat menghapus data siswa.');
        }
    }
}
