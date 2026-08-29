<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\TeacherService;
use App\Services\Core\ClassService;
use App\Services\Academic\ScheduleService;
use App\Services\Core\StudentService;
use App\Services\Academic\AttendanceService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Academic\SubjectService;
use App\Services\Core\BatchService;
use App\Services\Core\AssignmentService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\AssessmentConfigService;
use Illuminate\Support\Facades\Log;
use App\Helpers\ReportHelper;

class TeacherWorkspaceController extends Controller
{
    protected $teacherService;
    protected $classService;
    protected $scheduleService;
    protected $studentService;
    protected $attendanceService;
    protected $attendanceRequestService;
    protected $subjectService;
    protected $batchService;
    protected $assignmentService;
    protected $scoreService;
    protected $assessmentConfigService;

    public function __construct(
        TeacherService $teacherService,
        ClassService $classService,
        ScheduleService $scheduleService,
        StudentService $studentService,
        AttendanceService $attendanceService,
        AttendanceRequestService $attendanceRequestService,
        SubjectService $subjectService,
        BatchService $batchService,
        AssignmentService $assignmentService,
        ScoreService $scoreService,
        AssessmentConfigService $assessmentConfigService
    ) {
        $this->teacherService = $teacherService;
        $this->classService = $classService;
        $this->scheduleService = $scheduleService;
        $this->studentService = $studentService;
        $this->attendanceService = $attendanceService;
        $this->attendanceRequestService = $attendanceRequestService;
        $this->subjectService = $subjectService;
        $this->batchService = $batchService;
        $this->assignmentService = $assignmentService;
        $this->scoreService = $scoreService;
        $this->assessmentConfigService = $assessmentConfigService;
    }

    private function getTeacherId()
    {
        $userId = auth()->user()->User_ID ?? null;
        if (!$userId) return null;

        $allTeachers = collect($this->teacherService->getAllTeachers());
        $teacher = $allTeachers->firstWhere('User_ID', $userId);
        return $teacher['Teacher_ID'] ?? null;
    }

    private function verifyTeacherAccess()
    {
        $teacherId = $this->getTeacherId();
        if (!$teacherId) {
            abort(403, 'Profil Pengajar Belum Terhubung: Akun Anda belum dikaitkan dengan profil pengajar. Silakan hubungi Administrator.');
        }
        return $teacherId;
    }

    public function schedule()
    {
        $teacherId = $this->verifyTeacherAccess();

        $schedules = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $schedules = $schedules->map(function ($s) use ($classesById, $subjectsById) {
            $cls = $classesById[$s['Class_ID'] ?? ''] ?? null;
            $sub = $subjectsById[$s['Subject_ID'] ?? ''] ?? null;
            $s['Class_Name'] = $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $s['Class_ID']) : ($s['Class_ID'] ?? '-');
            $s['Subject_Name'] = $sub ? ($sub['Subject_Name'] ?? $s['Subject_ID']) : ($s['Subject_ID'] ?? '-');
            return $s;
        });

        return view('academic.teacher.schedule', compact('schedules', 'teacherId'));
    }

    public function myClasses()
    {
        $teacherId = $this->verifyTeacherAccess();

        $mySchedules = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId);

        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique();

        $allClasses = collect($this->classService->getAllClasses());
        $batchesById = collect($this->batchService->getAllBatches())->keyBy('Batch_ID');

        $classes = $allClasses->whereIn('Class_ID', $myClassIds)->map(function ($c) use ($batchesById) {
            $batch = $batchesById[$c['Batch_ID'] ?? ''] ?? null;
            $c['Batch_Name'] = $batch ? ($batch['Batch_Name'] ?? $c['Batch_ID']) : ($c['Batch_ID'] ?? '-');
            return $c;
        })->values();

        return view('academic.teacher.classes', compact('classes', 'teacherId'));
    }

    private function verifyClassAccess($teacherId, $classId)
    {
        $mySchedules = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->where('Class_ID', $classId);

        if ($mySchedules->isEmpty()) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini.');
        }
    }

    public function classStudents($classId)
    {
        $teacherId = $this->verifyTeacherAccess();
        $this->verifyClassAccess($teacherId, $classId);

        $cls = collect($this->classService->getAllClasses())->firstWhere('Class_ID', $classId);
        $className = $cls ? ($cls['Class_Name'] ?? $classId) : $classId;

        $students = collect($this->studentService->getAllStudents())
            ->where('Class_ID', $classId)
            ->map(function ($s) {
                return collect($s)->except(['Password', 'APP_KEY', 'HMAC', 'Token'])->toArray();
            })
            ->values();

        return view('academic.teacher.students', compact('students', 'className', 'classId'));
    }

    public function classAttendance($classId)
    {
        $teacherId = $this->verifyTeacherAccess();
        $this->verifyClassAccess($teacherId, $classId);

        $cls = collect($this->classService->getAllClasses())->firstWhere('Class_ID', $classId);
        $className = $cls ? ($cls['Class_Name'] ?? $classId) : $classId;

        $myScheduleIds = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->where('Class_ID', $classId)
            ->pluck('Schedule_ID')
            ->toArray();

        $attendances = collect($this->attendanceService->getAll())
            ->whereIn('Schedule_ID', $myScheduleIds)
            ->values();

        $attendanceRequests = collect($this->attendanceRequestService->getAll())
            ->filter(function($r) use ($classId) {
                return ($r['Class_ID'] ?? '') == $classId; // Adjust based on actual request schema
            })
            ->values();

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');

        $attendances = $attendances->map(function($a) use ($studentsById) {
            $stu = $studentsById[$a['Student_ID'] ?? ''] ?? null;
            $a['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? $a['Student_ID']) : ($a['Student_ID'] ?? '-');
            return $a;
        });

        $attendanceRequests = $attendanceRequests->map(function($r) use ($studentsById) {
            $stu = $studentsById[$r['Student_ID'] ?? ''] ?? null;
            $r['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? $r['Student_ID']) : ($r['Student_ID'] ?? '-');
            return $r;
        });

        return view('academic.teacher.attendance', compact('attendances', 'attendanceRequests', 'className', 'classId'));
    }

    public function reports()
    {
        $teacherId = $this->verifyTeacherAccess();
        return view('academic.teacher.reports', compact('teacherId'));
    }

    public function exportAttendancesCsv()
    {
        return $this->csvResponse(
            'laporan_kehadiran_guru.csv',
            $this->teacherAttendanceHeaders(),
            $this->teacherAttendanceRows()
        );
    }

    public function exportAttendancesPdf()
    {
        return $this->teacherReportResponse('pdf', 'Laporan Kehadiran Guru', $this->teacherAttendanceHeaders(), $this->teacherAttendanceRows());
    }

    public function printAttendances()
    {
        return $this->teacherReportResponse('print', 'Laporan Kehadiran Guru', $this->teacherAttendanceHeaders(), $this->teacherAttendanceRows());
    }

    public function exportScoresCsv()
    {
        return $this->csvResponse(
            'laporan_nilai_guru.csv',
            $this->teacherScoreHeaders(),
            $this->teacherScoreRows()
        );
    }

    public function exportScoresPdf()
    {
        return $this->teacherReportResponse('pdf', 'Laporan Nilai Guru', $this->teacherScoreHeaders(), $this->teacherScoreRows());
    }

    public function printScores()
    {
        return $this->teacherReportResponse('print', 'Laporan Nilai Guru', $this->teacherScoreHeaders(), $this->teacherScoreRows());
    }

    private function teacherAttendanceHeaders(): array
    {
        return ['Tanggal', 'Siswa', 'Kelas', 'Jadwal', 'Status', 'Masuk', 'Pulang', 'Catatan'];
    }

    private function teacherAttendanceRows(): array
    {
        $teacherId = $this->verifyTeacherAccess();
        $mySchedules = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId);
        $myScheduleIds = $mySchedules->pluck('Schedule_ID')->filter()->values()->all();
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');

        $rows = collect($this->attendanceService->getAll())
            ->whereIn('Schedule_ID', $myScheduleIds)
            ->map(function ($attendance) use ($studentsById, $classesById, $mySchedules) {
                $schedule = $mySchedules->firstWhere('Schedule_ID', $attendance['Schedule_ID'] ?? '');
                $classId = $attendance['Class_ID'] ?? ($schedule['Class_ID'] ?? '');
                $class = $classesById[$classId] ?? null;
                $student = $studentsById[$attendance['Student_ID'] ?? ''] ?? null;

                return [
                    $attendance['Attendance_Date'] ?? $attendance['Date'] ?? '-',
                    $student['Full_Name'] ?? $attendance['Student_ID'] ?? '-',
                    ($class['Class_Name'] ?? $classId) ?: '-',
                    $attendance['Schedule_ID'] ?? '-',
                    $this->translateAttendanceStatus($attendance['Status'] ?? ''),
                    $attendance['Time_In'] ?? $attendance['Check_In_Time'] ?? '-',
                    $attendance['Time_Out'] ?? $attendance['Check_Out_Time'] ?? '-',
                    $attendance['Notes'] ?? '-',
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function teacherScoreHeaders(): array
    {
        return ['Tanggal', 'Siswa', 'Kategori', 'Penilaian', 'Nilai', 'Grade', 'Status', 'Detail'];
    }

    private function teacherScoreRows(): array
    {
        $teacherId = $this->verifyTeacherAccess();
        $studentsById = collect($this->studentService->getAllStudents())
            ->keyBy('Student_ID');

        $rows = $this->teacherScopedScores($teacherId)
            ->map(function ($score) use ($studentsById) {
                $student = $studentsById[$score['Student_ID'] ?? ''] ?? null;
                $details = $score['Parsed_Details'] ?? $this->scoreService->parseEvaluationDetails($score);
                $summary = $this->summarizeScoreDetails($details);

                return [
                    $score['Created_At'] ?? $score['Assessment_Date'] ?? '-',
                    $student['Full_Name'] ?? $score['Student_ID'] ?? '-',
                    strtoupper($score['Assessment_Category'] ?? 'GENERAL'),
                    $score['Assessment_ID'] ?? $score['Assignment_ID'] ?? '-',
                    $score['Score'] ?? $score['Score_Value'] ?? '-',
                    $score['Grade'] ?? '-',
                    $score['Status'] ?? '-',
                    $summary,
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function teacherReportResponse(string $format, string $title, array $headers, array $rows)
    {
        return ReportHelper::export(
            $format,
            $title,
            collect($rows),
            ['summary' => '<tr><td>Total Data</td><td>: ' . count($rows) . '</td></tr>'],
            'pdf.generic_table',
            $headers,
            fn ($row) => $row,
            true
        );
    }

    private function translateAttendanceStatus($status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'PRESENT', 'HADIR' => 'Hadir',
            'LATE', 'TERLAMBAT' => 'Terlambat',
            'SICK', 'SAKIT' => 'Sakit',
            'PERMITTED', 'IZIN' => 'Izin',
            'ABSENT', 'ALPHA', 'ALPA' => 'Alpa',
            default => $status ?: '-',
        };
    }

    private function summarizeScoreDetails(array $details): string
    {
        $summary = [];
        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                continue;
            }
            $summary[] = ucwords(str_replace('_', ' ', (string) $key)) . ': ' . $value;
        }

        return implode('; ', $summary) ?: ($details['notes'] ?? '-');
    }

    private function csvResponse(string $filename, array $headers, array $rows)
    {
        $file = fopen('php://temp', 'r+');
        $sanitize = [ReportHelper::class, 'sanitizeCsvCell'];

        fputcsv($file, array_map($sanitize, $headers));
        foreach ($rows as $row) {
            fputcsv($file, array_map($sanitize, $row));
        }

        rewind($file);
        $csv = stream_get_contents($file);
        fclose($file);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // ==========================================
    // OPERATIONAL ROUTES
    // ==========================================

    public function students()
    {
        $teacherId = $this->verifyTeacherAccess();

        $mySchedules = collect($this->scheduleService->getAll())->where('Teacher_ID', $teacherId);
        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique();

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Class_ID', $myClassIds)
            ->map(function ($s) {
                return collect($s)->except(['Password', 'APP_KEY', 'HMAC', 'Token'])->toArray();
            })
            ->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');

        $students = $students->map(function ($s) use ($classesById) {
            $cls = $classesById[$s['Class_ID'] ?? ''] ?? null;
            $s['Class_Name'] = $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $s['Class_ID']) : ($s['Class_ID'] ?? '-');
            return $s;
        });

        return view('academic.teacher.students', compact('students', 'teacherId'));
    }

    public function attendances()
    {
        $teacherId = $this->verifyTeacherAccess();

        $myScheduleIds = collect($this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->pluck('Schedule_ID')
            ->toArray();

        $attendances = collect($this->attendanceService->getAll())
            ->whereIn('Schedule_ID', $myScheduleIds)
            ->values();

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');

        $attendances = $attendances->map(function($a) use ($studentsById) {
            $stu = $studentsById[$a['Student_ID'] ?? ''] ?? null;
            $a['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? $a['Student_ID']) : ($a['Student_ID'] ?? '-');
            return $a;
        });

        return view('academic.teacher.attendances', compact('attendances', 'teacherId'));
    }

    public function attendanceRequests()
    {
        $teacherId = $this->verifyTeacherAccess();

        $mySchedules = collect($this->scheduleService->getAll())->where('Teacher_ID', $teacherId);
        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique()->toArray();
        $myStudentIds = collect($this->studentService->getAllStudents())->whereIn('Class_ID', $myClassIds)->pluck('Student_ID')->toArray();

        $attendanceRequests = collect($this->attendanceRequestService->getAll())
            ->filter(function($r) use ($myStudentIds) {
                return in_array($r['Student_ID'] ?? '', $myStudentIds);
            })
            ->values();

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');

        $attendanceRequests = $attendanceRequests->map(function($r) use ($studentsById, $classesById) {
            $stu = $studentsById[$r['Student_ID'] ?? ''] ?? null;
            $r['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? $r['Student_ID']) : ($r['Student_ID'] ?? '-');
            $classId = $stu ? ($stu['Class_ID'] ?? '-') : '-';
            $cls = $classesById[$classId] ?? null;
            $r['Class_Name'] = $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $classId) : $classId;
            return $r;
        });

        return view('academic.teacher.attendance-requests', compact('attendanceRequests', 'teacherId'));
    }

    public function scores()
    {
        $teacherId = $this->verifyTeacherAccess();
        $scores = $this->teacherScopedScores($teacherId);

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $scores = $scores->map(function ($s) use ($studentsById) {
            $stu = $studentsById[$s['Student_ID'] ?? ''] ?? null;
            $s['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? $s['Student_ID']) : ($s['Student_ID'] ?? '-');
            return $s;
        });

        return view('academic.teacher.scores', compact('scores', 'teacherId'));
    }

    public function scoresCreate()
    {
        $teacherId = $this->verifyTeacherAccess();

        $mySchedules = collect($this->scheduleService->getAll())->where('Teacher_ID', $teacherId);
        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique()->toArray();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $classes = collect($myClassIds)->map(function ($cid) use ($classesById) {
            $cls = $classesById[$cid] ?? null;
            return [
                'Class_ID' => $cid,
                'Class_Name' => $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $cid) : $cid
            ];
        });

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Class_ID', $myClassIds)
            ->map(function ($s) use ($classesById) {
                $cls = $classesById[$s['Class_ID'] ?? ''] ?? null;
                $s['Class_Name'] = $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $s['Class_ID']) : ($s['Class_ID'] ?? '-');
                return collect($s)->only(['Student_ID', 'Full_Name', 'Username', 'Class_ID', 'Class_Name'])->toArray();
            })
            ->values();

        $assessmentConfigs = $this->assessmentConfigService->getActiveCategories();

        return view('academic.teacher.scores-create', compact('classes', 'students', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresStore(Request $request)
    {
        $teacherId = $this->verifyTeacherAccess();

        $request->validate([
            'Student_ID' => 'required|string',
            'Assessment_Category' => 'required|string',
            'Date' => 'required|date',
            'Notes' => 'nullable|string'
        ]);

        $category = $request->input('Assessment_Category');

        $studentId = $request->input('Student_ID');
        if (!$this->scoreService->isStudentInTeacherScope($studentId, $teacherId)) {
            return redirect()->back()->with('error', 'Siswa tidak berada di dalam kelas yang Anda ajar. (IDOR Protected)')->withInput();
        }

        // Validate aspects against SSOT
        $data = $request->except(['_token', '_method']);
        $aspectKeys = collect($data)->except(['Student_ID', 'Assessment_Category', 'Date', 'Notes'])->keys()->toArray();
        $evaluationDetails = [];
        foreach ($aspectKeys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $evaluationDetails[$key] = $data[$key];
            }
        }

        if (!empty($evaluationDetails)) {
            $isValid = $this->assessmentConfigService->validateAspectPayload($category, $evaluationDetails);
            if (!$isValid) {
                return redirect()->back()->with('error', 'Aspek penilaian tidak valid atau tidak terdaftar untuk kategori ini.')->withInput();
            }
        }

        try {
            // We pass the dynamic data to the score service.
            // The score service needs to properly format it into Evaluation_Details JSON.
            $data['Teacher_ID'] = $teacherId;
            $this->scoreService->create($data);

            return redirect()->route('teacher.workspace.scores')->with('success', 'Penilaian berhasil disimpan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan penilaian: ' . $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function assignments()
    {
        $teacherId = $this->verifyTeacherAccess();

        $mySchedules = collect($this->scheduleService->getAll())->where('Teacher_ID', $teacherId);
        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique()->toArray();

        $assignments = collect($this->assignmentService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->whereIn('Class_ID', $myClassIds)
            ->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');

        $assignments = $assignments->map(function ($a) use ($classesById) {
            $cls = $classesById[$a['Class_ID'] ?? ''] ?? null;
            $a['Class_Name'] = $cls ? ($cls['Class_Name'] ?? $cls['Class_Code'] ?? $a['Class_ID']) : ($a['Class_ID'] ?? '-');
            return $a;
        });

        return view('academic.teacher.assignments', compact('assignments', 'teacherId'));
    }

    public function calendar()
    {
        $teacherId = $this->verifyTeacherAccess();

        $events = [];
        $allSchedules = collect($this->scheduleService->getAll());
        $events = $allSchedules->where('Teacher_ID', $teacherId)->map(function($s) {
            return [
                'date' => $s['Date'] ?? date('Y-m-d'),
                'title' => ($s['Class_ID'] ?? 'Class') . ' - ' . ($s['Topic'] ?? ''),
                'type' => $s['Type'] ?? 'Class'
            ];
        })->values()->toArray();

        return view('academic.teacher.calendar', compact('events', 'teacherId'));
    }

    private function teacherScopedScores(string $teacherId)
    {
        $teacherAssessmentIds = collect([]);

        try {
            $assessmentRepo = app(\App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class);
            try {
                $assessments = $assessmentRepo->getAll();
            } catch (\Throwable $e) {
                $assessments = is_callable([$assessmentRepo, 'fetchAll']) ? $assessmentRepo->fetchAll() : collect([]);
            }

            $teacherAssessmentIds = collect($assessments)
                ->where('Teacher_ID', $teacherId)
                ->pluck('Assessment_ID')
                ->filter()
                ->unique()
                ->values();
        } catch (\Exception $e) {
            $teacherAssessmentIds = collect([]);
        }

        return collect($this->scoreService->getAll())
            ->filter(function ($score) use ($teacherId, $teacherAssessmentIds) {
                $scoreTeacherId = trim((string) ($score['Teacher_ID'] ?? ''));
                if ($scoreTeacherId !== '') {
                    return $scoreTeacherId === $teacherId;
                }

                $assessmentId = trim((string) ($score['Assessment_ID'] ?? ''));
                if ($assessmentId !== '') {
                    return $teacherAssessmentIds->contains($assessmentId);
                }

                return false;
            })
            ->values();
    }
}
