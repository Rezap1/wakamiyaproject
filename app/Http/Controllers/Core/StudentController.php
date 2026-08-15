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
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $students = $this->studentService->getAllStudents();
        $programs = $this->programService->getAllPrograms();
        $batches = $this->batchService->getAllBatches();
        $classes = $this->classService->getAllClasses();

        $students = $students->map(function ($student) use ($programs, $batches, $classes) {
            $program = $programs->firstWhere('Program_ID', $student['Program_ID']);
            $batch = $batches->firstWhere('Batch_ID', $student['Batch_ID']);
            $class = $classes->firstWhere('Class_ID', $student['Class_ID']);

            $student['Program_Name'] = $program ? $program['Program_Name'] : '-';
            $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : '-';
            $student['Class_Name'] = $class ? $class['Class_Name'] : '-';
            return $student;
        });

        $search = $request->input('search');
        if (!empty($search)) {
            $students = \App\Helpers\CollectionHelper::search($students, $search, ['NIS', 'Full_Name', 'Program_Name', 'Batch_Name', 'Class_Name']);
        }

        if ($request->filled('program')) { $students = $students->where('Program_ID', $request->input('program')); }
        if ($request->filled('batch')) { $students = $students->where('Batch_ID', $request->input('batch')); }
        if ($request->filled('class')) { $students = $students->where('Class_ID', $request->input('class')); }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $students = $students->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'Siswa (Student)',
            'data' => collect(array_values($students->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['NIS', 'Nama Siswa', 'Program', 'Angkatan', 'Kelas', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['NIS'] ?? '-',
                    $row['Full_Name'] ?? '-',
                    $row['Program_Name'] ?? '-',
                    $row['Batch_Name'] ?? '-',
                    $row['Class_Name'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$students->count().'</td></tr>'
        ];
    }

    protected $studentService;
    protected $programService;
    protected $batchService;
    protected $classService;
    protected $userService;

    public function __construct(
        StudentService $studentService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService,
        \App\Services\Core\UserService $userService
    ) {
        $this->studentService = $studentService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
        $this->userService = $userService;
    }

    public function index(\Illuminate\Http\Request $request)
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

            $search = $request->input('search');
            if (!empty($search)) {
                $students = \App\Helpers\CollectionHelper::search($students, $search, ['Student_ID', 'NIS', 'Full_Name', 'Email', 'Phone', 'Program_Name', 'Batch_Name', 'Class_Name']);
            }

            if ($request->filled('program')) {
                $students = $students->where('Program_ID', $request->input('program'));
            }

            if ($request->filled('batch')) {
                $students = $students->where('Batch_ID', $request->input('batch'));
            }

            if ($request->filled('class')) {
                $students = $students->where('Class_ID', $request->input('class'));
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $students = $students->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            if ($request->filled('date_from')) {
                $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
                $students = $students->filter(function($item) use ($dateFrom) {
                    $dateField = $item['Registration_Date'] ?? $item['Created_At'] ?? null;
                    if (!$dateField) return false;
                    return \Carbon\Carbon::parse($dateField)->startOfDay()->gte($dateFrom);
                });
            }

            if ($request->filled('date_to')) {
                $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
                $students = $students->filter(function($item) use ($dateTo) {
                    $dateField = $item['Registration_Date'] ?? $item['Created_At'] ?? null;
                    if (!$dateField) return false;
                    return \Carbon\Carbon::parse($dateField)->endOfDay()->lte($dateTo);
                });
            }

            // Pagination
            $studentsPaginated = \App\Helpers\CollectionHelper::paginate($students, 10)->withQueryString();
            
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
            
            $allUsers = $this->userService->getAllUsers();
            $allStudents = $this->studentService->getAllStudents();
            $usedUserIds = $allStudents->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds) {
                return !in_array($user['User_ID'], $usedUserIds) && ($user['Is_Active'] ?? 'TRUE') === 'TRUE';
            })->values();
            
            return view('students.create', compact('programs', 'batches', 'classes', 'users'));
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
            
            $allUsers = $this->userService->getAllUsers();
            $allStudents = $this->studentService->getAllStudents();
            $usedUserIds = $allStudents->where('Student_ID', '!=', $id)->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds) {
                return !in_array($user['User_ID'], $usedUserIds);
            })->values();

            return view('students.edit', compact('student', 'programs', 'batches', 'classes', 'users'));
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

            return redirect()->route('students.index')->with('success', 'Data siswa berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting student: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat menghapus data siswa.');
        }
    }

    public function lookup($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (!$student) {
                return response()->json(['error' => 'Data siswa tidak ditemukan.'], 404);
            }

            $program = $this->programService->getProgramById($student['Program_ID']);
            $batch = $this->batchService->getBatchById($student['Batch_ID']);
            $class = $this->classService->getClassById($student['Class_ID']);

            return response()->json([
                'Student_ID' => $student['Student_ID'] ?? '',
                'Student_Number' => $student['Student_Number'] ?? '',
                'Full_Name' => $student['Full_Name'] ?? '',
                'Email' => $student['Email'] ?? '',
                'Phone_Number' => $student['Phone_Number'] ?? '',
                'Enrollment_Status' => $student['Enrollment_Status'] ?? '',
                'Program_Name' => $program ? $program['Program_Name'] : '-',
                'Batch_Name' => $batch ? $batch['Batch_Name'] : '-',
                'Class_Name' => $class ? $class['Class_Name'] : '-',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan internal.'], 500);
        }
    }
}
