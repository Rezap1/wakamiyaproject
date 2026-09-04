<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\TeacherService;
use App\Services\Core\ClassService;
use App\Services\Academic\ScheduleService;
use App\Services\Core\StudentService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Academic\SubjectService;
use App\Services\Core\BatchService;
use App\Services\Core\AssignmentService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\AssessmentConfigService;
use Illuminate\Support\Facades\Log;
use App\Helpers\ReportHelper;
use App\Helpers\AttendanceStatusHelper;
use App\Support\Reporting\HumanReadableResolver;

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
            $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);
            $s['Subject_Name'] = HumanReadableResolver::subjectName($s['Subject_ID'] ?? '', $subjectsById);
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

    private function verifyClassAccess($teacherId, $classId, $schedules = null)
    {
        $mySchedules = collect($schedules ?? $this->scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->where('Class_ID', $classId);

        if ($mySchedules->isEmpty()) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini.');
        }
    }

    /**
     * Build the teacher-scoped attendance read model without trusting request
     * identifiers. Ownership is resolved from the authenticated teacher's
     * schedules before class-based attendance is grouped.
     */
    private function teacherAttendanceReadModel($teacherId, ?string $classFilter = null): array
    {
        $allSchedules = collect($this->scheduleService->getAll());
        $mySchedules = $allSchedules
            ->filter(fn ($schedule) => ($schedule['Teacher_ID'] ?? '') === $teacherId)
            ->values();
        $teacherScheduleIds = $mySchedules->pluck('Schedule_ID')->filter()->unique()->values();
        $teacherClassIds = $mySchedules->pluck('Class_ID')->filter()->unique()->values();

        $classes = collect($this->classService->getAllClasses())
            ->filter(function ($class) use ($teacherClassIds) {
                $isActive = strtoupper(trim((string) ($class['Is_Active'] ?? '')));
                return ($isActive === 'TRUE' || $isActive === '')
                    && $teacherClassIds->contains($class['Class_ID'] ?? '');
            })
            ->values();
        $studentRows = collect($this->studentService->getAllStudents());
        $legacyClassifier = new AttendanceLegacyClassifier();
        $attendanceRows = collect($this->attendanceService->getAll())
            ->filter(function ($attendance) use ($legacyClassifier, $classes, $allSchedules, $teacherClassIds, $teacherScheduleIds) {
                $classified = $legacyClassifier->classify($attendance, $classes, $allSchedules);
                if (in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)) {
                    return false;
                }
                if (empty($attendance['Student_ID'])) {
                    return false;
                }
                if ($classified['is_schedule_based']) {
                    return $teacherScheduleIds->contains($classified['schedule_id'] ?? '');
                }
                return $teacherClassIds->contains($classified['class_id'] ?? '');
            })
            ->sortBy(function ($attendance) {
                $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
                return in_array($type, ['CLASS_QR', 'CLASS_MANUAL'], true) ? 0 : 1;
            })
            ->values();
        $dateFilter = request()->input('date', date('Y-m-d'));

        return [
            'groups' => $this->attendanceService->buildClassAttendanceGroups(
                $classes,
                $studentRows,
                $attendanceRows,
                $allSchedules,
                $dateFilter,
                null,
                $classFilter,
                null,
                null
            ),
            'classes' => $classes,
            'students' => $studentRows,
            'schedules' => $mySchedules,
            'teacherClassIds' => $teacherClassIds,
            'teacherScheduleIds' => $teacherScheduleIds,
            'dateFilter' => $dateFilter,
        ];
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
        $readModel = $this->teacherAttendanceReadModel($teacherId, $classId);
        $this->verifyClassAccess($teacherId, $classId, $readModel['schedules']);

        $cls = $readModel['classes']->firstWhere('Class_ID', $classId);
        $className = $cls ? ($cls['Class_Name'] ?? $classId) : $classId;

        $studentsById = collect($readModel['students'])->keyBy('Student_ID');
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $schedulesById = collect($this->scheduleService->getAll())->keyBy('Schedule_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $attendanceRequests = collect($this->attendanceRequestService->getTeacherRequests(auth()->user()))
            ->filter(function ($request) use ($studentsById, $classId) {
                $student = $studentsById[$request['Student_ID'] ?? ''] ?? null;
                return $student && ($student['Class_ID'] ?? '') === $classId;
            })
            ->map(fn ($request) => $this->enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById))
            ->sortByDesc('Created_At')
            ->values();

        return view('academic.teacher.attendance', [
            'attendanceGroups' => $readModel['groups'],
            'dateFilter' => $readModel['dateFilter'],
            'attendanceRequests' => $attendanceRequests,
            'className' => $className,
            'classId' => $classId,
        ]);
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
        $allSchedules = collect($this->scheduleService->getAll());
        $schedulesById = $allSchedules->keyBy('Schedule_ID');
        $mySchedules = $allSchedules->where('Teacher_ID', $teacherId)->values();
        $myScheduleIds = $mySchedules->pluck('Schedule_ID')->filter()->values();
        $myClassIds = $mySchedules->pluck('Class_ID')->filter()->unique()->values();
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $teachersById = collect($this->teacherService->getAllTeachers())->keyBy('Teacher_ID');
        $classifier = new AttendanceLegacyClassifier();

        $rows = collect($this->attendanceService->getAll())
            ->filter(function ($attendance) use ($classifier, $classesById, $allSchedules, $myScheduleIds, $myClassIds) {
                $classified = $classifier->classify($attendance, $classesById->values(), $allSchedules);
                if (in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)) {
                    return false;
                }
                if (empty($attendance['Student_ID'])) {
                    return false;
                }
                if ($classified['is_schedule_based']) {
                    return $myScheduleIds->contains($classified['schedule_id'] ?? '');
                }
                return $myClassIds->contains($classified['class_id'] ?? '');
            })
            ->map(function ($attendance) use ($classifier, $studentsById, $classesById, $allSchedules, $schedulesById, $subjectsById, $teachersById) {
                $classified = $classifier->classify($attendance, $classesById->values(), $allSchedules);
                $scheduleId = $classified['schedule_id'] ?? '';
                $classId = $classified['class_id'] ?? ($attendance['Class_ID'] ?? '');

                return [
                    $attendance['Attendance_Date'] ?? $attendance['Date'] ?? '-',
                    HumanReadableResolver::studentName($attendance['Student_ID'] ?? '', $studentsById),
                    HumanReadableResolver::className($classId, $classesById),
                    $scheduleId ? HumanReadableResolver::scheduleLabel($scheduleId, $schedulesById, $classesById, $subjectsById, $teachersById) : 'Absensi Kelas / QR',
                    AttendanceStatusHelper::label($attendance['Status'] ?? ''),
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
        $assessmentsById = collect(app(\App\Repositories\GoogleSheets\AssessmentRepository::class)->fetchAll())
            ->keyBy('Assessment_ID');
        $assignmentsById = collect($this->assignmentService->getAll())
            ->keyBy('Assignment_ID');

        $rows = $this->teacherScopedScores($teacherId)
            ->map(function ($score) use ($studentsById, $assessmentsById, $assignmentsById) {
                $details = $score['Parsed_Details'] ?? $this->scoreService->parseEvaluationDetails($score);
                $summary = $this->summarizeScoreDetails($details);
                $assessmentId = trim((string) ($score['Assessment_ID'] ?? ''));
                $assignmentId = trim((string) ($score['Assignment_ID'] ?? ''));
                $title = $assessmentId !== ''
                    ? HumanReadableResolver::assessmentTitle($assessmentId, $assessmentsById)
                    : ($assignmentId !== '' ? HumanReadableResolver::assignmentTitle($assignmentId, $assignmentsById) : 'Penilaian tidak ditemukan');

                return [
                    $score['Created_At'] ?? $score['Assessment_Date'] ?? '-',
                    HumanReadableResolver::studentName($score['Student_ID'] ?? '', $studentsById),
                    strtoupper($score['Assessment_Category'] ?? 'GENERAL'),
                    $title,
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
        return AttendanceStatusHelper::label($status);
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
            $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);
            return $s;
        });

        return view('academic.teacher.students', compact('students', 'teacherId'));
    }

    public function attendances()
    {
        $teacherId = $this->verifyTeacherAccess();
        $classFilter = request()->input('class_id');
        $readModel = $this->teacherAttendanceReadModel($teacherId, $classFilter);
        $classOptions = $readModel['classes']->mapWithKeys(function ($class) {
            $classId = trim((string) ($class['Class_ID'] ?? ''));
            $label = trim((string) ($class['Class_Name'] ?? '')) ?: $classId;
            return $classId === '' ? [] : [$classId => $label];
        })->all();

        return view('academic.teacher.attendances', [
            'attendanceGroups' => $readModel['groups'],
            'dateFilter' => $readModel['dateFilter'],
            'classOptions' => $classOptions,
            'classFilter' => trim((string) $classFilter),
            'teacherId' => $teacherId,
        ]);
    }

    public function attendanceRequests()
    {
        $teacherId = $this->verifyTeacherAccess();

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $schedulesById = collect($this->scheduleService->getAll())->keyBy('Schedule_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $attendanceRequests = collect($this->attendanceRequestService->getTeacherRequests(auth()->user()))
            ->map(fn ($request) => $this->enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById))
            ->sortByDesc('Created_At')
            ->values();

        return view('academic.teacher.attendance-requests', compact('attendanceRequests', 'teacherId'));
    }

    public function scores()
    {
        $teacherId = $this->verifyTeacherAccess();
        $scores = $this->teacherScopedScores($teacherId);

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $scores = $scores->map(function ($s) use ($studentsById) {
            $stu = $studentsById[$s['Student_ID'] ?? ''] ?? null;
            $s['Student_Name'] = $stu ? ($stu['Full_Name'] ?? $stu['Username'] ?? 'Data siswa tidak ditemukan') : 'Data siswa tidak ditemukan';
            return $s;
        });

        $assessmentConfigs = collect($this->assessmentConfigService->getActiveCategories())
            ->keyBy(fn ($config) => strtoupper(trim((string) ($config['Category_ID'] ?? ''))))
            ->toArray();

        return view('academic.teacher.scores', compact('scores', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresCreate()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        $myClassIds = $scope['class_ids'];

        $schedulesById = collect($this->scheduleService->getAll())
            ->filter(fn ($schedule) => in_array(trim((string) ($schedule['Schedule_ID'] ?? '')), $scope['schedule_ids'], true))
            ->keyBy('Schedule_ID');
        $schedules = $schedulesById->map(function ($schedule) {
            return [
                'Schedule_ID' => $schedule['Schedule_ID'],
                'Class_ID' => $schedule['Class_ID'] ?? '',
                'Subject_ID' => $schedule['Subject_ID'] ?? '',
                'label' => trim((string) ($schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? 'Jadwal'))
                    . ' — ' . trim((string) ($schedule['Class_ID'] ?? '')),
            ];
        })->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $schedules = $schedules->map(function ($schedule) use ($classesById, $subjectsById) {
            $schedule['label'] = HumanReadableResolver::subjectName($schedule['Subject_ID'] ?? '', $subjectsById)
                . ' - ' . HumanReadableResolver::className($schedule['Class_ID'] ?? '', $classesById);

            return $schedule;
        });
        $classes = collect($myClassIds)->map(function ($cid) use ($classesById) {
            return [
                'Class_ID' => $cid,
                'Class_Name' => HumanReadableResolver::className($cid, $classesById)
            ];
        });

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Class_ID', $myClassIds)
            ->map(function ($s) use ($classesById) {
                $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);
                return collect($s)->only(['Student_ID', 'Full_Name', 'Username', 'Class_ID', 'Class_Name'])->toArray();
            })
            ->values();

        $assessmentConfigs = $this->assessmentConfigService->getActiveCategories();

        return view('academic.teacher.scores-create', compact('classes', 'students', 'schedules', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresStore(Request $request)
    {
        $teacherId = $this->verifyTeacherAccess();

        $validated = $request->validate([
            'Student_ID' => 'required|string',
            'Schedule_ID' => 'required|string',
            'Assessment_Category' => 'required|string',
            'Date' => 'required|date',
            'Assessment_ID' => 'nullable|string',
            'Score_ID' => ['nullable', 'string', 'regex:/^SCR-[0-9a-f-]{36}$/i'],
            'Notes' => 'nullable|string|max:2000'
        ]);

        $category = strtoupper(trim((string) $validated['Assessment_Category']));
        $categoryConfig = $this->assessmentConfigService->getCategoryConfig($category);
        if (!$categoryConfig || empty($this->assessmentConfigService->getAspects($category))) {
            return redirect()->back()->with('error', 'Kategori penilaian belum tersedia di MASTER_ASSESSMENT_CONFIG.')->withInput();
        }

        $studentId = trim((string) $validated['Student_ID']);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        $scheduleId = trim((string) $validated['Schedule_ID']);
        if (!in_array($scheduleId, $scope['schedule_ids'], true)
            || !$this->scoreService->isStudentInSchedule($studentId, $scheduleId, $scope)) {
            return redirect()->back()->with('error', 'Siswa tidak berada di dalam kelas yang Anda ajar. (IDOR Protected)')->withInput();
        }

        $assessmentId = trim((string) ($validated['Assessment_ID'] ?? ''));
        if ($assessmentId !== '' && !in_array($assessmentId, $scope['assessment_ids'], true)) {
            return redirect()->back()->with('error', 'Assessment tidak berada dalam scope pengajaran Anda.')->withInput();
        }

        // Validate aspects against SSOT
        $data = $request->except(['_token', '_method']);
        // Ownership identity is always resolved from the authenticated actor;
        // a client-supplied Teacher_ID is never evidence of authorization.
        $data['Teacher_ID'] = $teacherId;
        $data['Student_ID'] = $studentId;
        $data['Schedule_ID'] = $scheduleId;
        $data['Assessment_Category'] = $category;
        $data['Assessment_Date'] = $validated['Date'];
        $aspectKeys = collect($data)->except([
            'Student_ID', 'Teacher_ID', 'Assessment_Category', 'Date',
            'Assessment_Date', 'Assessment_ID', 'Score_ID', 'Notes',
            'Schedule_ID', 'Class_ID',
        ])->keys()->toArray();
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
        } else {
            return redirect()->back()->with('error', 'Seluruh aspek penilaian wajib diisi.')->withInput();
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

    public function scoresEdit(string $id)
    {
        $teacherId = $this->verifyTeacherAccess();
        $score = $this->scoreService->getById($id);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);

        if (!$score || !$this->scoreService->isScoreInTeacherScope($score, $teacherId, $scope)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah nilai ini.');
        }

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $score['Student_Name'] = HumanReadableResolver::studentName($score['Student_ID'] ?? '', $studentsById);
        $assessmentConfigs = $this->assessmentConfigService->getActiveCategories();
        return view('academic.teacher.scores-edit', compact('score', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresUpdate(Request $request, string $id)
    {
        $teacherId = $this->verifyTeacherAccess();
        $existing = $this->scoreService->getById($id);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);

        if (!$existing || !$this->scoreService->isScoreInTeacherScope($existing, $teacherId, $scope)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah nilai ini.');
        }

        $validated = $request->validate([
            'Assessment_Category' => 'required|string',
            'Date' => 'required|date',
            'Notes' => 'nullable|string|max:2000',
        ]);
        $category = strtoupper(trim((string) $validated['Assessment_Category']));
        if (!$this->assessmentConfigService->getCategoryConfig($category)
            || empty($this->assessmentConfigService->getAspects($category))) {
            return redirect()->back()->with('error', 'Kategori penilaian belum tersedia di MASTER_ASSESSMENT_CONFIG.')->withInput();
        }

        // Scope/ownership references are immutable during a teacher edit;
        // never let a client move an existing score to another schedule or
        // assignment after the authorization check.
        $data = $request->except(['_token', '_method', 'Student_ID', 'Score_ID', 'Teacher_ID', 'Assessment_ID', 'Schedule_ID', 'Class_ID', 'Assignment_ID']);
        $data['Student_ID'] = $existing['Student_ID'];
        $data['Teacher_ID'] = $teacherId;
        $data['Assessment_Category'] = $category;
        $data['Assessment_Date'] = $validated['Date'];
        $evaluationDetails = collect($data)
            ->except(['Student_ID', 'Teacher_ID', 'Assessment_Category', 'Date', 'Assessment_Date', 'Notes'])
            ->filter(fn ($value) => $value !== '' && $value !== null)
            ->toArray();

        if (!$this->assessmentConfigService->validateAspectPayload($category, $evaluationDetails)) {
            return redirect()->back()->with('error', 'Aspek penilaian tidak valid atau belum lengkap.')->withInput();
        }

        try {
            $this->scoreService->update($id, $data);
            return redirect()->route('teacher.workspace.scores')->with('success', 'Penilaian berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui penilaian: ' . $this->safeExceptionMessage($e))->withInput();
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
            $a['Class_Name'] = HumanReadableResolver::className($a['Class_ID'] ?? '', $classesById);
            return $a;
        });

        return view('academic.teacher.assignments', compact('assignments', 'teacherId'));
    }

    public function calendar()
    {
        $teacherId = $this->verifyTeacherAccess();

        $events = [];
        $allSchedules = collect($this->scheduleService->getAll());
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $events = $allSchedules->where('Teacher_ID', $teacherId)->map(function($s) use ($classesById, $subjectsById) {
            return [
                'date' => $s['Date'] ?? date('Y-m-d'),
                'title' => HumanReadableResolver::subjectName($s['Subject_ID'] ?? '', $subjectsById)
                    . ' - ' . HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById)
                    . ' - ' . ($s['Topic'] ?? ''),
                'type' => $s['Type'] ?? 'Class'
            ];
        })->values()->toArray();

        return view('academic.teacher.calendar', compact('events', 'teacherId'));
    }

    private function teacherScopedScores(string $teacherId)
    {
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        return collect($this->scoreService->getAll())
            ->filter(fn ($score) => $this->scoreService->isScoreInTeacherScope($score, $teacherId, $scope))
            ->values();
    }

    private function enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById): array
    {
        $request = is_array($request) ? $request : (array) $request;
        $student = $studentsById[$request['Student_ID'] ?? ''] ?? null;
        $studentClassId = trim((string) ($student['Class_ID'] ?? $request['Class_ID'] ?? ''));
        $attendanceType = strtoupper(trim((string) ($request['Attendance_Type'] ?? '')))
            ?: (empty($request['Schedule_ID']) ? 'CLASS_QR' : 'SCHEDULE');
        $isClassBased = in_array($attendanceType, ['CLASS_QR', 'CLASS_MANUAL'], true);
        $schedule = $isClassBased ? null : ($schedulesById[$request['Schedule_ID'] ?? ''] ?? null);

        $request['Student_Name'] = HumanReadableResolver::studentName($request['Student_ID'] ?? '', $studentsById);
        $request['Class_Name'] = HumanReadableResolver::className($studentClassId, $classesById);
        $request['Attendance_Type'] = $isClassBased ? 'CLASS_QR' : 'SCHEDULE';
        $request['Target_Display'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::scheduleLabel($request['Schedule_ID'] ?? '', $schedulesById, $classesById, $subjectsById);
        $request['Subject_Name'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::subjectName($schedule['Subject_ID'] ?? '', $subjectsById);
        $request['Status_Label'] = match (strtoupper(trim((string) ($request['Status'] ?? 'PENDING')))) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => 'Menunggu Review',
        };
        $request['Request_Type_Label'] = AttendanceStatusHelper::label($request['Request_Type'] ?? '');

        return $request;
    }
}
