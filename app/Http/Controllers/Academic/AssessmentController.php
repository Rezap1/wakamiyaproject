<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AssessmentService;
use App\Services\Core\ActivityLogService;
use App\Support\Reporting\HumanReadableResolver;

class AssessmentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Assessment_Date';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $assessments = $this->assessmentService->getAll();
        
        // Apply filters if any
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $assessments = $assessments->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Assessment_ID'] ?? ''), $search) ||
                       str_contains(strtolower($item['Title'] ?? ''), $search);
            })->values();
        }
        
        $classesById = collect(app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll())->keyBy('Class_ID');
        $subjectsById = collect(app(\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

        return [
            'moduleName' => 'Evaluasi/Assessment',
            'data' => collect(array_values($assessments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tipe', 'Tanggal', 'Judul', 'Kelas', 'Mata Pelajaran', 'Guru', 'Status'],
            'mapRow' => function($row) use ($classesById, $subjectsById, $teachersById) {

                return [
                    $row['Type'] ?? '-',
                    isset($row['Assessment_Date']) ? \Carbon\Carbon::parse($row['Assessment_Date'])->format('d M Y') : '-',
                    $row['Title'] ?? '-',
                    HumanReadableResolver::className($row['Class_ID'] ?? '', $classesById),
                    HumanReadableResolver::subjectName($row['Subject_ID'] ?? '', $subjectsById),
                    HumanReadableResolver::teacherName($row['Teacher_ID'] ?? '', $teachersById),
                    $row['Status'] ?? 'Draft'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$assessments->count().'</td></tr>'
        ];
    }

    protected $assessmentService;

    public function __construct(AssessmentService $assessmentService)
    {
        $this->assessmentService = $assessmentService;
    }

    public function index()
    {
        $assessments = $this->assessmentService->getAll();
        
        $subjectRepo = app(\App\Repositories\GoogleSheets\SubjectRepository::class);
        $subjects = $subjectRepo->fetchAll()->keyBy('Subject_ID');
        
        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        
        $assessments = $assessments->map(function($item) use ($subjects, $classes) {
            $subjId = $item['Subject_ID'] ?? null;
            $classId = $item['Class_ID'] ?? null;
            
            $subjName = HumanReadableResolver::subjectName($subjId, $subjects);
            $className = HumanReadableResolver::className($classId, $classes);
            $progId = $item['Program_ID'] ?? '';
            
            $item['Name'] = $item['Title'] ?? '';
            $item['Exam_Date'] = isset($item['Assessment_Date']) ? \Carbon\Carbon::parse($item['Assessment_Date'])->format('d M Y') : '-';
            $item['Subject_Name'] = $subjName;
            $item['Class_Name'] = $className;
            $item['Program_ID'] = $progId;
            
            return $item;
        });

        if (request()->filled('search')) {
            $search = request()->input('search');
            $assessments = \App\Helpers\CollectionHelper::search($assessments, $search, ['Assessment_ID', 'Name', 'Title', 'Subject_ID', 'Class_ID', 'Program_ID']);
        }

        $assessments = \App\Helpers\CollectionHelper::paginate($assessments, 10)->withQueryString();

        return view('academic.assessments.index', compact('assessments'));
    }

    public function create(
        \App\Repositories\GoogleSheets\ProgramRepository $programRepo, 
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo, 
        \App\Repositories\GoogleSheets\ClassRepository $classRepo, 
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $programs = $programRepo->fetchAll();
        $batches = $batchRepo->fetchAll();
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        
        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }
        
        return view('academic.assessments.create', compact('programs', 'batches', 'classes', 'teachers', 'subjects', 'currentTeacherId'));
    }

    public function store(\App\Http\Requests\StoreAssessmentRequest $request)
    {
        try {
            $this->assessmentService->create($request->validated());
            return redirect()->route('assessments.index')->with('success', 'Assessment created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $assessment = $this->assessmentService->getById($id);
        $classesById = collect(app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll())->keyBy('Class_ID');
        $subjectsById = collect(app(\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

        $assessment['Assessment_Name'] = $assessment['Assessment_Name'] ?? $assessment['Title'] ?? 'Penilaian';
        $assessment['Subject_Name'] = HumanReadableResolver::subjectName($assessment['Subject_ID'] ?? '', $subjectsById);
        $assessment['Class_Name'] = HumanReadableResolver::className($assessment['Class_ID'] ?? '', $classesById);
        $assessment['Teacher_Name'] = HumanReadableResolver::teacherName($assessment['Teacher_ID'] ?? '', $teachersById);

        return view('academic.assessments.show', compact('assessment'));
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\ProgramRepository $programRepo, 
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo, 
        \App\Repositories\GoogleSheets\ClassRepository $classRepo, 
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $assessment = $this->assessmentService->getById($id);
        $programs = $programRepo->fetchAll();
        $batches = $batchRepo->fetchAll();
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        
        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }
        
        return view('academic.assessments.edit', compact('assessment', 'programs', 'batches', 'classes', 'teachers', 'subjects', 'currentTeacherId'));
    }

    public function update(\App\Http\Requests\UpdateAssessmentRequest $request, $id)
    {
        try {
            $this->assessmentService->update($id, $request->validated());
            return redirect()->route('assessments.index')->with('success', 'Assessment updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->assessmentService->delete($id);
            return redirect()->route('assessments.index')->with('success', 'Assessment deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }
}
