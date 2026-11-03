<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\ScoreService;
use App\Support\Academic\ScoreDateResolver;
use App\Support\Academic\ScoreValueFormatter;
use App\Support\Reporting\HumanReadableResolver;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = '_Assessment_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $dateFilters = $this->validatedDateFilters($request);
        $scores = $this->scoreService->getAll()
            ->map(function ($score) {
                $score['_Assessment_Date'] = ScoreDateResolver::value((array) $score);

                return $score;
            });
        $assessmentConfigService = app(\App\Services\Academic\AssessmentConfigService::class);
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $scores = $scores->filter(function($item) use ($search, $assessmentConfigService) {
                $categoryLabel = strtolower($assessmentConfigService->categoryLabel($item['Assessment_Category'] ?? ''));
                return str_contains(strtolower($item['Score_ID'] ?? ''), $search) ||
                       str_contains(strtolower($item['Student_ID'] ?? ''), $search) ||
                       str_contains(strtolower($item['Assessment_Category'] ?? ''), $search) ||
                       str_contains($categoryLabel, $search);
            })->values();
        }

        if ($request->filled('category')) {
            $category = strtoupper((string) $request->input('category'));
            $scores = $scores->filter(fn ($score) => strtoupper((string) ($score['Assessment_Category'] ?? '')) === $category)->values();
        }

        $scores = ScoreDateResolver::filter($scores, $dateFilters['date_from'] ?? null, $dateFilters['date_to'] ?? null)
            ->sortByDesc(fn ($score) => ($score['_Assessment_Date'] ?? '').'|'.($score['Created_At'] ?? ''))
            ->values();
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        $assessments = collect(app(\App\Repositories\GoogleSheets\AssessmentRepository::class)->fetchAll())->keyBy('Assessment_ID');
        $assignments = collect(app(\App\Services\Core\AssignmentService::class)->getAll())->keyBy('Assignment_ID');
        
        return [
            'moduleName' => 'Nilai (Score)',
            'data' => collect(array_values($scores->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Siswa', 'Kategori', 'Penilaian', 'Nilai', 'Grade', 'Status', 'Metrik Evaluasi'],
            'mapRow' => function($row) use ($students, $assessments, $assignments, $assessmentConfigService) {
                $category = strtoupper($row['Assessment_Category'] ?? 'GENERAL');
                $categoryLabel = $assessmentConfigService->categoryLabel($category);
                $details = $this->scoreService->parseEvaluationDetails($row);
                $metricSummary = '-';
                
                if (!empty($details) && count($details) > 1) { // more than just 'category'
                    $summaryArr = [];
                    foreach ($details as $k => $v) {
                        if (in_array(strtolower($k), ['category', 'notes', 'subject_id'])) continue;
                        $summaryArr[] = ucfirst(str_replace('_', ' ', $k)) . ": " . $v;
                    }
                    if (!empty($summaryArr)) {
                        $metricSummary = implode(', ', $summaryArr);
                    } else {
                        $metricSummary = $details['notes'] ?? $row['Remarks'] ?? '-';
                    }
                } else {
                    $metricSummary = $details['notes'] ?? $row['Remarks'] ?? '-';
                }

                $assessmentId = trim((string) ($row['Assessment_ID'] ?? ''));
                $assignmentId = trim((string) ($row['Assignment_ID'] ?? ''));
                $assessmentTitle = $assessmentId !== ''
                    ? HumanReadableResolver::assessmentTitle($assessmentId, $assessments)
                    : ($assignmentId !== '' ? HumanReadableResolver::assignmentTitle($assignmentId, $assignments) : 'Penilaian tidak ditemukan');

                return [
                    HumanReadableResolver::studentName($row['Student_ID'] ?? '', $students),
                    $categoryLabel,
                    $assessmentTitle,
                    ScoreValueFormatter::format($row['Score'] ?? $row['Score_Value'] ?? null),
                    $row['Grade'] ?? '-',
                    $row['Status'] ?? '-',
                    $metricSummary
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data Nilai</td><td>: '.$scores->count().'</td></tr>',
            'metadata' => array_filter([
                'scopeLabel' => $this->scorePeriodLabel($dateFilters, $request),
            ]),
        ];
    }

    protected $scoreService;

    public function __construct(ScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    private function resolveCurrentTeacherId(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
        $teacher = collect($teacherRepo->fetchAll())->firstWhere('User_ID', $user->User_ID ?? '');

        return $teacher['Teacher_ID'] ?? null;
    }

    public function index(Request $request)
    {
        $dateFilters = $this->validatedDateFilters($request);
        $scores = $this->scoreService->getAll();
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');

        $assessmentRepo = app(\App\Repositories\GoogleSheets\AssessmentRepository::class);
        $assessments = $assessmentRepo->fetchAll()->keyBy('Assessment_ID');
        $assessmentConfigService = app(\App\Services\Academic\AssessmentConfigService::class);
        
        $scores = $scores->map(function($item) use ($students, $assessments, $assessmentConfigService) {
            $studentId = $item['Student_ID'] ?? null;
            $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : 'Data siswa tidak ditemukan';
            
            $asmId = $item['Assessment_ID'] ?? null;
            $asmTitle = $asmId && isset($assessments[$asmId]) ? ($assessments[$asmId]['Title'] ?? $assessments[$asmId]['Assessment_Name'] ?? 'Penilaian tidak ditemukan') : 'Penilaian tidak ditemukan';

            $item['Student_Name'] = $studentName;
            $item['Student_Display'] = $studentName;
            $item['Assessment_Title'] = $asmTitle;
            $item['Assessment_Category'] = strtoupper($item['Assessment_Category'] ?? 'GENERAL');
            $item['Assessment_Category_Label'] = $assessmentConfigService->categoryLabel($item['Assessment_Category'] ?? '');
            $item['_Assessment_Date'] = ScoreDateResolver::value((array) $item);
            
            return $item;
        });

        if ($request->filled('category')) {
            $cat = strtoupper($request->input('category'));
            $scores = $scores->where('Assessment_Category', $cat)->values();
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $scores = \App\Helpers\CollectionHelper::search($scores, $search, ['Score_ID', 'Student_Name', 'Student_ID', 'Assessment_ID', 'Assessment_Title', 'Assessment_Category', 'Assessment_Category_Label', 'Score', 'Score_Value']);
        }

        $scores = ScoreDateResolver::filter($scores, $dateFilters['date_from'] ?? null, $dateFilters['date_to'] ?? null);

        $sortDirection = $request->input('sort', 'desc');
        abort_unless(in_array($sortDirection, ['asc', 'desc'], true), 422, 'Urutan data nilai tidak valid.');
        $sortCallback = fn ($score) => ($score['_Assessment_Date'] ?? '').'|'.($score['Created_At'] ?? '');
        $scores = ($sortDirection === 'asc' ? $scores->sortBy($sortCallback) : $scores->sortByDesc($sortCallback))->values();

        $scoreGroups = $scores
            ->groupBy(fn ($score) => strtoupper(trim((string) ($score['Assessment_Category'] ?? 'GENERAL'))))
            ->map(function ($group, $category) use ($assessmentConfigService) {
                $numericScores = $group->map(fn ($score) => $score['Score'] ?? $score['Score_Value'] ?? null)->filter(fn ($value) => is_numeric($value));

                return [
                    'id' => $category,
                    'title' => $assessmentConfigService->categoryLabel($category),
                    'total' => $group->count(),
                    'average' => $numericScores->count() > 0 ? round($numericScores->avg(), 1) : null,
                    'items' => $group->sortBy('Student_Name')->values(),
                ];
            })
            ->sortBy('title')
            ->values();

        $scores = \App\Helpers\CollectionHelper::paginate($scores, 10)->withQueryString();
        
        $assessmentConfigs = collect($assessmentConfigService->getActiveCategories())->keyBy('Category_ID')->toArray();
        
        return view('academic.scores.index', compact('scores', 'scoreGroups', 'assessmentConfigs'));
    }

    private function validatedDateFilters(Request $request): array
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:export_filter,range'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_if:export_filter,range', 'after_or_equal:start_date'],
        ], [
            'date_from.date_format' => 'Tanggal Mulai harus menggunakan format tanggal yang valid.',
            'date_to.date_format' => 'Tanggal Selesai harus menggunakan format tanggal yang valid.',
            'date_to.after_or_equal' => 'Tanggal Selesai tidak boleh sebelum Tanggal Mulai.',
            'start_date.required_if' => 'Tanggal Mulai ekspor wajib dipilih.',
            'end_date.required_if' => 'Tanggal Selesai ekspor wajib dipilih.',
            'end_date.after_or_equal' => 'Tanggal Selesai ekspor tidak boleh sebelum Tanggal Mulai.',
        ]);
    }

    private function scorePeriodLabel(array $filters, Request $request): ?string
    {
        $usesExportRange = $request->input('export_filter') === 'range';
        $from = $usesExportRange ? ($filters['start_date'] ?? null) : ($filters['date_from'] ?? null);
        $to = $usesExportRange ? ($filters['end_date'] ?? null) : ($filters['date_to'] ?? null);
        if (! $from && ! $to) {
            return null;
        }

        if ($from && $to) {
            return 'Periode penilaian: '.\App\Helpers\DateHelper::format($from, 'd F Y')
                .' s.d. '.\App\Helpers\DateHelper::format($to, 'd F Y');
        }

        return $from
            ? 'Periode penilaian: mulai '.\App\Helpers\DateHelper::format($from, 'd F Y')
            : 'Periode penilaian: sampai '.\App\Helpers\DateHelper::format($to, 'd F Y');
    }

    public function create(
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AssessmentRepository $assessmentRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $user = auth()->user();
        $userRole = strtoupper($user->Role ?? '');
        
        // H8.21: Teacher scope authorization
        if ($userRole === 'TEACHER') {
            $teacherId = $this->resolveCurrentTeacherId();
            if ($teacherId) {
                $scopedStudents = $this->scoreService->getStudentsInTeacherScope($teacherId);
                $students = collect($scopedStudents);
            } else {
                $students = collect([]);
            }
        } else {
            $students = $studentRepo->fetchAll();
        }
        
        $teachers = $teacherRepo->fetchAll();
        $assessments = $assessmentRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        $assessmentConfigService = app(\App\Services\Academic\AssessmentConfigService::class);
        $assessmentConfigs = $assessmentConfigService->getActiveCategories();
        return view('academic.scores.create', compact('students', 'teachers', 'assessments', 'subjects', 'assessmentConfigs'));
    }

    public function store(\App\Http\Requests\StoreScoreRequest $request)
    {
        try {
            $user = auth()->user();
            $userRole = strtoupper($user->Role ?? '');
            $validated = $request->validated();
            
            // H8.21: Server-side teacher scope authorization
            if ($userRole === 'TEACHER') {
                $teacherId = $this->resolveCurrentTeacherId();
                if (!$teacherId || !$this->scoreService->isStudentInTeacherScope($validated['Student_ID'], $teacherId)) {
                    return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menilai siswa ini.'])->withInput();
                }
            }
            
            // Set Assessment_Date
            if (!empty($validated['Assessment_Date'])) {
                $validated['Assessment_Date'] = $validated['Assessment_Date'];
            } else {
                $validated['Assessment_Date'] = now()->format('Y-m-d');
            }
            
            // Auto-set Assessment_ID if not provided
            if (empty($validated['Assessment_ID'])) {
                $validated['Assessment_ID'] = 'DIRECT-' . now()->format('Ymd');
            }
            
            $this->scoreService->create($validated);
            return redirect()->route('scores.index')->with('success', 'Nilai berhasil dicatat dan dipublikasikan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $score = $this->scoreService->getById($id);
        if (!$score) {
            return redirect()->route('scores.index')->with('error', 'Data nilai tidak ditemukan.');
        }

        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $student = $studentRepo->findById($score['Student_ID'] ?? '');
        $score['Student_Name'] = $student ? $student['Full_Name'] : 'Data siswa tidak ditemukan';

        $assessmentRepo = app(\App\Repositories\GoogleSheets\AssessmentRepository::class);
        $assessment = $assessmentRepo->getById($score['Assessment_ID'] ?? '');
        $score['Assessment_Title'] = $assessment ? ($assessment['Title'] ?? $assessment['Assessment_Name'] ?? 'Penilaian tidak ditemukan') : 'Penilaian tidak ditemukan';

        $score['Parsed_Details'] = $this->scoreService->parseEvaluationDetails($score);
        $score['Assessment_ID'] = $score['Assessment_ID'] ?? ($score['Assignment_ID'] ?? null);
        $assessmentConfig = app(\App\Services\Academic\AssessmentConfigService::class)->getCategoryConfig($score['Assessment_Category'] ?? '');

        return view('academic.scores.show', compact('score', 'assessmentConfig'));
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AssessmentRepository $assessmentRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $score = $this->scoreService->getById($id);
        if (!$score) {
            return redirect()->route('scores.index')->with('error', 'Data nilai tidak ditemukan.');
        }

        $students = $studentRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $assessments = $assessmentRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        
        $score['Parsed_Details'] = $this->scoreService->parseEvaluationDetails($score);
        $assessmentConfigService = app(\App\Services\Academic\AssessmentConfigService::class);
        $assessmentConfigs = $assessmentConfigService->getActiveCategories();

        return view('academic.scores.edit', compact('score', 'students', 'teachers', 'assessments', 'subjects', 'assessmentConfigs'));
    }

    public function update(\App\Http\Requests\UpdateScoreRequest $request, $id)
    {
        try {
            $this->scoreService->update($id, $request->validated());
            return redirect()->route('scores.index')->with('success', 'Nilai berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->scoreService->delete($id);
            return redirect()->route('scores.index')->with('success', 'Data nilai berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('scores.index')->with('error', 'Gagal menghapus data nilai: ' . $this->safeExceptionMessage($e));
        }
    }

    public function exportCSV()
    {
        $scores = $this->scoreService->getAll();
        $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
        $assessmentsById = collect(app(\App\Repositories\GoogleSheets\AssessmentRepository::class)->fetchAll())->keyBy('Assessment_ID');
        $assignmentsById = collect(app(\App\Services\Core\AssignmentService::class)->getAll())->keyBy('Assignment_ID');
        $assessmentConfigService = app(\App\Services\Academic\AssessmentConfigService::class);
        $file = fopen('php://temp', 'r+');
        $sanitize = fn($value) => \App\Helpers\ReportHelper::sanitizeCsvCell($value ?? '');

        fputcsv($file, array_map($sanitize, [
            'Siswa',
            'Kategori',
            'Penilaian',
            'Nilai',
            'Grade',
            'Status',
            'Ringkasan Evaluasi',
        ]));
        
        foreach ($scores as $s) {
            $category = strtoupper($s['Assessment_Category'] ?? 'GENERAL');
            $categoryLabel = $assessmentConfigService->categoryLabel($category);
            $details = $this->scoreService->parseEvaluationDetails($s);
            $summary = '-';
            
            if (!empty($details) && count($details) > 1) { // more than just 'category'
                $metricSummary = [];
                foreach ($details as $k => $v) {
                    if (in_array(strtolower($k), ['category', 'notes', 'subject_id'])) continue;
                    $metricSummary[] = ucfirst(str_replace('_', ' ', $k)) . ": " . $v;
                }
                if (!empty($metricSummary)) {
                    $summary = implode("; ", $metricSummary);
                } else {
                    $summary = str_replace([",", "\n", "\r"], [" ", " ", " "], $details['notes'] ?? $s['Remarks'] ?? '');
                }
            } else {
                $summary = str_replace([",", "\n", "\r"], [" ", " ", " "], $details['notes'] ?? $s['Remarks'] ?? '');
            }

            $assessmentId = trim((string) ($s['Assessment_ID'] ?? ''));
            $assignmentId = trim((string) ($s['Assignment_ID'] ?? ''));
            $assessmentTitle = $assessmentId !== ''
                ? HumanReadableResolver::assessmentTitle($assessmentId, $assessmentsById)
                : ($assignmentId !== '' ? HumanReadableResolver::assignmentTitle($assignmentId, $assignmentsById) : 'Penilaian tidak ditemukan');

            fputcsv($file, array_map($sanitize, [
                HumanReadableResolver::studentName($s['Student_ID'] ?? '', $studentsById),
                $categoryLabel,
                $assessmentTitle,
                ScoreValueFormatter::format($s['Score'] ?? $s['Score_Value'] ?? null, ''),
                $s['Grade'] ?? '',
                $s['Status'] ?? '',
                $summary,
            ]));
        }

        rewind($file);
        $csv = stream_get_contents($file);
        fclose($file);

        return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename="scores_export.csv"');
    }
}
