<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Core\StudentService;
use App\Services\Core\ProgramService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Helpers\CollectionHelper;
use Illuminate\Http\Request;
use Exception;

class AlumniController extends Controller
{
    protected $studentService;
    protected $programService;
    protected $batchService;
    protected $classService;

    public function __construct(
        StudentService $studentService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService
    ) {
        $this->studentService = $studentService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
    }

    public function index(Request $request)
    {
        $alumni = $this->studentService->getAlumniStudents();
        $programs = $this->programService->getAllPrograms();
        $batches = $this->batchService->getAllBatches();
        $classes = $this->classService->getAllClasses();

        // Enrich alumni data
        $alumni = $alumni->map(function ($student) use ($programs, $batches, $classes) {
            $program = $programs->firstWhere('Program_ID', $student['Program_ID'] ?? '');
            $batch = $batches->firstWhere('Batch_ID', $student['Batch_ID'] ?? '');
            $class = $classes->firstWhere('Class_ID', $student['Class_ID'] ?? '');

            $student['Program_Name'] = $program ? $program['Program_Name'] : '-';
            $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : '-';
            $student['Class_Name'] = $class ? $class['Class_Name'] : '-';
            
            // Derive graduation year
            $updatedAt = $student['Updated_At'] ?? $student['Created_At'] ?? '';
            $student['Graduation_Year'] = !empty($updatedAt) ? date('Y', strtotime($updatedAt)) : '-';

            return $student;
        });

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $alumni = CollectionHelper::search($alumni, $search, [
                'Student_ID', 'Student_Number', 'Full_Name', 'Email', 'Program_Name', 'Batch_Name'
            ]);
        }

        // Filters
        if ($request->filled('program')) {
            $alumni = $alumni->where('Program_ID', $request->input('program'));
        }
        if ($request->filled('batch')) {
            $alumni = $alumni->where('Batch_ID', $request->input('batch'));
        }
        if ($request->filled('grad_year')) {
            $alumni = $alumni->where('Graduation_Year', $request->input('grad_year'));
        }

        // Pagination
        $perPage = 15;
        $page = $request->input('page', 1);
        $paginatedAlumni = CollectionHelper::paginate($alumni, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);

        return view('academic.alumni.index', [
            'alumni' => $paginatedAlumni,
            'programs' => $programs,
            'batches' => $batches,
            'totalAlumni' => $alumni->count()
        ]);
    }

    public function show($id)
    {
        $student = $this->studentService->getStudentById($id);
        if (!$student || !$this->studentService->isAlumni($student)) {
            abort(404, 'Data Alumni tidak ditemukan.');
        }

        $program = $this->programService->getProgramById($student['Program_ID'] ?? '');
        $batch = $this->batchService->getBatchById($student['Batch_ID'] ?? '');
        $class = $this->classService->getClassById($student['Class_ID'] ?? '');

        $student['Program_Name'] = $program ? $program['Program_Name'] : '-';
        $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : '-';
        $student['Class_Name'] = $class ? $class['Class_Name'] : '-';

        // Retrieve Score History
        $scores = [];
        try {
            $scoreRepo = app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class);
            $scores = collect($scoreRepo->fetchAll())->where('Student_ID', $id)->values()->toArray();
        } catch (\Exception $e) {}

        // Retrieve Generated Documents
        $documents = [];
        try {
            $docRepo = app(\App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class);
            $documents = collect($docRepo->fetchAll())->where('Entity_ID', $id)->values()->toArray();
        } catch (\Exception $e) {}

        return view('academic.alumni.show', [
            'student' => $student,
            'scores' => $scores,
            'documents' => $documents
        ]);
    }
}
