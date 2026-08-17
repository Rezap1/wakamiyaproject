<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\ScoreService;

class ScoreController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $scores = $this->scoreService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $scores = $scores->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Score_ID'] ?? ''), $search) ||
                       str_contains(strtolower($item['Student_ID'] ?? ''), $search) ||
                       str_contains(strtolower($item['Assessment_Category'] ?? ''), $search);
            })->values();
        }
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        return [
            'moduleName' => 'Nilai (Score)',
            'data' => collect(array_values($scores->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Nilai', 'Siswa', 'Kategori', 'Penilaian', 'Nilai', 'Grade', 'Status', 'Metrik Evaluasi'],
            'mapRow' => function($row) use ($students) {
                $studentId = $row['Student_ID'] ?? null;
                $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
                
                $category = strtoupper($row['Assessment_Category'] ?? 'GENERAL');
                $details = $this->scoreService->parseEvaluationDetails($row);
                $metricSummary = '-';
                
                if ($category === 'SPORTS') {
                    $metricSummary = sprintf("Lari: %skm/%s m, PushUp: %s, SitUp: %s", 
                        $details['running_distance'] ?? 0, 
                        $details['running_time'] ?? 0, 
                        $details['push_up'] ?? 0, 
                        $details['sit_up'] ?? 0
                    );
                } elseif ($category === 'LANGUAGE') {
                    $metricSummary = sprintf("Spk: %s, Wrt: %s, Lst: %s, Rdg: %s, Eth: %s, Mtv: %s, Att: %s", 
                        $details['speaking'] ?? 0,
                        $details['writing'] ?? 0,
                        $details['listening'] ?? 0,
                        $details['reading'] ?? 0,
                        $details['ethics'] ?? 0,
                        $details['motivation'] ?? 0,
                        $details['attendance'] ?? 0
                    );
                } else {
                    $metricSummary = $details['notes'] ?? $row['Remarks'] ?? '-';
                }

                return [
                    $row['Score_ID'] ?? '-',
                    $studentName . ($studentId ? " ($studentId)" : ''),
                    $category,
                    $row['Assessment_ID'] ?? $row['Assignment_ID'] ?? '-',
                    $row['Score'] ?? $row['Score_Value'] ?? '-',
                    $row['Grade'] ?? '-',
                    $row['Status'] ?? '-',
                    $metricSummary
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data Nilai</td><td>: '.$scores->count().'</td></tr>'
        ];
    }

    protected $scoreService;

    public function __construct(ScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request)
    {
        $scores = $this->scoreService->getAll();
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');

        $assessmentRepo = app(\App\Repositories\GoogleSheets\AssessmentRepository::class);
        $assessments = $assessmentRepo->fetchAll()->keyBy('Assessment_ID');
        
        $scores = $scores->map(function($item) use ($students, $assessments) {
            $studentId = $item['Student_ID'] ?? null;
            $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
            
            $asmId = $item['Assessment_ID'] ?? null;
            $asmTitle = $asmId && isset($assessments[$asmId]) ? ($assessments[$asmId]['Title'] ?? $assessments[$asmId]['Assessment_Name'] ?? $asmId) : $asmId;

            $item['Student_Name'] = $studentName;
            $item['Student_Display'] = $studentName . ($studentId ? " ($studentId)" : '');
            $item['Assessment_Title'] = $asmTitle;
            $item['Assessment_Category'] = strtoupper($item['Assessment_Category'] ?? 'GENERAL');
            
            return $item;
        });

        if ($request->filled('category')) {
            $cat = strtoupper($request->input('category'));
            $scores = $scores->where('Assessment_Category', $cat)->values();
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $scores = \App\Helpers\CollectionHelper::search($scores, $search, ['Score_ID', 'Student_Name', 'Student_ID', 'Assessment_ID', 'Assessment_Title', 'Assessment_Category', 'Score_Value']);
        }

        $scores = \App\Helpers\CollectionHelper::paginate($scores, 10)->withQueryString();
        
        return view('academic.scores.index', compact('scores'));
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
            $teacherId = $user->Employee_ID ?? null;
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
        return view('academic.scores.create', compact('students', 'teachers', 'assessments', 'subjects'));
    }

    public function store(\App\Http\Requests\StoreScoreRequest $request)
    {
        try {
            $user = auth()->user();
            $userRole = strtoupper($user->Role ?? '');
            $validated = $request->validated();
            
            // H8.21: Server-side teacher scope authorization
            if ($userRole === 'TEACHER') {
                $teacherId = $user->Employee_ID ?? null;
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
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
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
        $score['Student_Name'] = $student ? $student['Full_Name'] : ($score['Student_ID'] ?? 'Siswa Tidak Diketahui');

        $assessmentRepo = app(\App\Repositories\GoogleSheets\AssessmentRepository::class);
        $assessment = $assessmentRepo->getById($score['Assessment_ID'] ?? '');
        $score['Assessment_Title'] = $assessment ? ($assessment['Title'] ?? $assessment['Assessment_Name'] ?? $score['Assessment_ID']) : ($score['Assessment_ID'] ?? '-');

        $score['Parsed_Details'] = $this->scoreService->parseEvaluationDetails($score);

        return view('academic.scores.show', compact('score'));
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

        return view('academic.scores.edit', compact('score', 'students', 'teachers', 'assessments', 'subjects'));
    }

    public function update(\App\Http\Requests\UpdateScoreRequest $request, $id)
    {
        try {
            $this->scoreService->update($id, $request->validated());
            return redirect()->route('scores.index')->with('success', 'Nilai berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->scoreService->delete($id);
            return redirect()->route('scores.index')->with('success', 'Data nilai berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('scores.index')->with('error', 'Gagal menghapus data nilai: ' . $e->getMessage());
        }
    }

    public function exportCSV()
    {
        $scores = $this->scoreService->getAll();
        $csv = "Score_ID,Student_ID,Assessment_Category,Assessment_ID,Score,Grade,Status,Evaluation_Details_Summary\n";
        
        foreach ($scores as $s) {
            $category = strtoupper($s['Assessment_Category'] ?? 'GENERAL');
            $details = $this->scoreService->parseEvaluationDetails($s);
            $summary = '-';
            
            if ($category === 'SPORTS') {
                $summary = sprintf("Lari: %skm/%sm; PushUp: %s; SitUp: %s", 
                    $details['running_distance'] ?? 0, 
                    $details['running_time'] ?? 0, 
                    $details['push_up'] ?? 0, 
                    $details['sit_up'] ?? 0
                );
            } elseif ($category === 'LANGUAGE') {
                $summary = sprintf("Speaking: %s; Writing: %s; Listening: %s; Reading: %s; Ethics: %s; Motivation: %s; Attendance: %s", 
                    $details['speaking'] ?? 0,
                    $details['writing'] ?? 0,
                    $details['listening'] ?? 0,
                    $details['reading'] ?? 0,
                    $details['ethics'] ?? 0,
                    $details['motivation'] ?? 0,
                    $details['attendance'] ?? 0
                );
            } else {
                $summary = str_replace([",", "\n", "\r"], [" ", " ", " "], $details['notes'] ?? $s['Remarks'] ?? '');
            }

            $csv .= sprintf("%s,%s,%s,%s,%s,%s,%s,\"%s\"\n",
                $s['Score_ID'] ?? '',
                $s['Student_ID'] ?? '',
                $category,
                $s['Assessment_ID'] ?? $s['Assignment_ID'] ?? '',
                $s['Score'] ?? $s['Score_Value'] ?? '',
                $s['Grade'] ?? '',
                $s['Status'] ?? '',
                $summary
            );
        }

        return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename="scores_export.csv"');
    }
}
