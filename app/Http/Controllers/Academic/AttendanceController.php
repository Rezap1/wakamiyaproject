<?php

namespace App\Http\Controllers\Academic;

use App\Helpers\AttendanceStatusHelper;
use App\Helpers\CollectionHelper;
use App\Helpers\ReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\EmployeeRepository;
use App\Repositories\GoogleSheets\ScheduleRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Academic\AttendanceService;
use App\Support\Presentation\IndonesianPresentation;
use App\Support\Reporting\HumanReadableResolver;
use App\Traits\Exportable;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Attendance_Date';

    protected function getExportConfig(Request $request)
    {
        $attendances = $this->attendanceService->getAll();

        $studentRepo = app(StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');

        $classRepo = app(ClassRepository::class);
        $classRows = $classRepo->fetchAll();
        $classes = $classRows->keyBy('Class_ID');

        $scheduleRepo = app(ScheduleRepositoryInterface::class);
        $scheduleRows = $scheduleRepo->fetchAll();
        $schedules = $scheduleRows->keyBy('Schedule_ID');

        $subjectRepo = app(SubjectRepositoryInterface::class);
        $subjects = $subjectRepo->fetchAll()->keyBy('Subject_ID');

        $teacherRepo = app(TeacherRepositoryInterface::class);
        $teachers = $teacherRepo->fetchAll()->keyBy('Teacher_ID');

        $employeeRepo = app(EmployeeRepositoryInterface::class);
        $employees = $employeeRepo->fetchAll()->keyBy('Employee_ID');
        $classifier = new AttendanceLegacyClassifier;

        return [
            'moduleName' => 'Kehadiran (Attendance)',
            'data' => collect(array_values($attendances->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Siswa', 'Kelas / Jadwal', 'Guru', 'Status', 'Time In', 'Time Out', 'Catatan'],
            'mapRow' => function ($row) use ($students, $classes, $classRows, $schedules, $scheduleRows, $subjects, $teachers, $employees, $classifier) {
                $date = '-';
                if (isset($row['Attendance_Date']) && ! empty($row['Attendance_Date'])) {
                    try {
                        $date = Carbon::parse($row['Attendance_Date'])->format('d M Y');
                    } catch (\Exception $e) {
                    }
                }

                $classified = $classifier->classify($row, $classRows, $scheduleRows);
                $scheduleId = trim((string) ($classified['schedule_id'] ?? ''));
                $classId = trim((string) ($classified['class_id'] ?? ($row['Class_ID'] ?? '')));
                $target = ($classified['is_schedule_based'] ?? false) && $scheduleId !== ''
                    ? HumanReadableResolver::scheduleLabel($scheduleId, $schedules, $classes, $subjects, $teachers)
                    : HumanReadableResolver::className($classId, $classes).' / Absensi Kelas';

                $teacherId = trim((string) ($row['Teacher_ID'] ?? ''));
                $employeeId = trim((string) ($row['Employee_ID'] ?? ''));
                $actorName = $teacherId !== ''
                    ? HumanReadableResolver::teacherName($teacherId, $teachers)
                    : ($employeeId !== '' ? HumanReadableResolver::employeeName($employeeId, $employees) : '-');

                return [
                    $date,
                    HumanReadableResolver::studentName($row['Student_ID'] ?? '', $students),
                    $target,
                    $actorName,
                    AttendanceStatusHelper::label($row['Status'] ?? ''),
                    $row['Check_In_Time'] ?? $row['Time_In'] ?? '-',
                    $row['Check_Out_Time'] ?? $row['Time_Out'] ?? '-',
                    $row['Notes'] ?? '-',
                ];
            },
            'isLandscape' => true,
        ];
    }

    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function index(Request $request)
    {
        $attendances = $this->attendanceService->getAll();

        $classRepo = app(ClassRepositoryInterface::class);
        $allClasses = $classRepo->fetchAll();
        $classes = $allClasses->filter(function ($c) {
            $isActive = strtoupper(trim($c['Is_Active'] ?? ''));

            return $isActive === 'TRUE' || $isActive === '';
        })->values();

        $classOptions = [];
        foreach ($classes as $c) {
            $cid = trim((string) ($c['Class_ID'] ?? ''));
            if ($cid !== '') {
                $classOptions[$cid] = ($c['Class_Name'] ?? $cid).(! empty($c['Class_Code']) ? ' ('.$c['Class_Code'].')' : '');
            }
        }

        $scheduleRepo = app(ScheduleRepositoryInterface::class);
        $schedules = $scheduleRepo->fetchAll()->keyBy('Schedule_ID');

        $studentRepo = app(StudentRepositoryInterface::class);
        $studentRows = $studentRepo->fetchAll();

        $dateFilter = $request->input('date', date('Y-m-d'));
        $dateEndFilter = $request->input('date_end');
        $classFilter = $request->input('class_id');
        $statusFilter = $request->input('status');
        $search = strtolower($request->input('search', ''));

        $classSummary = $this->attendanceService->buildClassAttendanceGroups(
            $classes,
            $studentRows,
            $attendances,
            $schedules,
            $dateFilter,
            $dateEndFilter,
            $classFilter,
            $statusFilter,
            $search
        );

        $paginatedClasses = CollectionHelper::paginate($classSummary, 10)->withQueryString();

        return view('academic.attendances.index', compact('paginatedClasses', 'classOptions', 'dateFilter', 'dateEndFilter', 'search', 'statusFilter', 'classFilter'));
    }

    /** Export the same filtered class-roster read model shown by index(). */
    public function exportPdf(Request $request)
    {
        return $this->attendanceReportResponse($request, 'pdf');
    }

    public function previewPdf(Request $request)
    {
        return $this->attendanceReportResponse($request, 'preview');
    }

    private function attendanceReportResponse(Request $request, string $format)
    {
        $report = $this->buildAttendanceReport($request);

        return ReportHelper::export(
            $format,
            'Laporan Presensi Akademik',
            collect($report['rows']),
            [
                'scopeLabel' => $report['scopeLabel'],
                'summary' => $report['summary'],
                'filterLabel' => $report['filterLabel'],
            ],
            'pdf.academic_attendance_report',
            [],
            null,
            true
        );
    }

    private function buildAttendanceReport(Request $request): array
    {
        $dateFilter = $request->input('date', date('Y-m-d'));
        $dateEndFilter = $request->input('date_end');
        $classFilter = $request->input('class_id');
        $statusFilter = $request->input('status');
        $search = strtolower($request->input('search', ''));

        $classRows = collect(app(ClassRepositoryInterface::class)->fetchAll());
        $classes = $classRows->filter(fn ($c) => in_array(strtoupper(trim((string) ($c['Is_Active'] ?? ''))), ['TRUE', ''], true))->values();
        $schedules = collect(app(ScheduleRepositoryInterface::class)->fetchAll());
        $students = collect(app(StudentRepositoryInterface::class)->fetchAll());
        $attendances = $this->attendanceService->getAll();
        $groups = $this->attendanceService->buildClassAttendanceGroups($classes, $students, $attendances, $schedules, $dateFilter, $dateEndFilter, $classFilter, $statusFilter, $search);

        $classesById = $classes->keyBy('Class_ID');
        $schedulesById = $schedules->keyBy('Schedule_ID');
        $subjectsById = collect(app(SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');
        $rows = [];
        foreach ($groups as $group) {
            foreach (($group['Students'] ?? collect()) as $student) {
                $attendance = $student['Attendance'] ?? null;
                $date = $attendance['Normalized_Attendance_Date'] ?? ($attendance['Attendance_Date'] ?? null);
                $scheduleId = trim((string) ($attendance['Resolved_Schedule_ID'] ?? ($attendance['Schedule_ID'] ?? '')));
                $scheduleLabel = $scheduleId !== ''
                    ? HumanReadableResolver::scheduleLabel($scheduleId, $schedulesById, $classesById, $subjectsById, $teachersById)
                    : 'Absensi Kelas / QR';
                $rows[] = [
                    'date' => $date,
                    'day' => $date ? IndonesianPresentation::day(Carbon::parse($date)->format('l')) : '-',
                    'student_name' => $student['Student_Name'] ?? '-',
                    'student_number' => $student['Student_Number'] ?? '-',
                    'class_name' => $group['Class_Name'] ?? '-',
                    'schedule' => $scheduleLabel,
                    'check_in' => $student['Check_In_Time'] ?? '-',
                    'check_out' => $student['Check_Out_Time'] ?? '-',
                    'status' => $student['Display_Status'] ?? AttendanceStatusHelper::label($student['Status'] ?? ''),
                    'notes' => $student['Notes'] ?? '-',
                ];
            }
        }
        $rows = collect($rows)->sortBy(fn ($row) => [
            $row['date'] ? strtotime((string) $row['date']) : PHP_INT_MAX,
            $row['student_name'], $row['class_name'], $row['check_in'], $row['student_number'],
        ])->values()->all();

        $counts = collect($groups)->flatMap(fn ($group) => $group['Students'] ?? [])->countBy('Status_Key');
        $summary = '<tr><td>Total Siswa</td><td>: '.count($rows).'</td></tr>'
            .'<tr><td>Hadir</td><td>: '.($counts['PRESENT'] ?? 0).'</td></tr>'
            .'<tr><td>Terlambat</td><td>: '.($counts['LATE'] ?? 0).'</td></tr>'
            .'<tr><td>Sakit</td><td>: '.($counts['SICK'] ?? 0).'</td></tr>'
            .'<tr><td>Izin</td><td>: '.($counts['PERMITTED'] ?? 0).'</td></tr>'
            .'<tr><td>Alpa</td><td>: '.($counts['ABSENT'] ?? 0).'</td></tr>'
            .'<tr><td>Belum Absen</td><td>: '.($counts['NOT_ATTENDED'] ?? 0).'</td></tr>';
        $classLabel = $classFilter ? HumanReadableResolver::className($classFilter, $classesById) : 'Semua kelas aktif';
        $period = $dateEndFilter && $dateEndFilter !== $dateFilter ? $dateFilter.' - '.$dateEndFilter : $dateFilter;

        return [
            'rows' => $rows,
            'summary' => $summary,
            'scopeLabel' => 'Kelas: '.$classLabel.' | Periode: '.$period,
            'filterLabel' => 'Pencarian: '.($search ?: 'Semua').' | Status: '.($statusFilter ?: 'Semua'),
        ];
    }

    public function create()
    {
        try {
            $classRepo = app(ClassRepositoryInterface::class);
            $allClasses = $classRepo->fetchAll();

            $classes = $allClasses->filter(function ($c) {
                $isActive = strtoupper(trim($c['Is_Active'] ?? ''));

                return $isActive === 'TRUE' || $isActive === '';
            })->values();

            // Build classOptions in controller to avoid any Blade issues
            $classOptions = [];
            foreach ($classes as $c) {
                $cid = trim((string) ($c['Class_ID'] ?? ''));
                if ($cid !== '') {
                    $classOptions[$cid] = ($c['Class_Name'] ?? $cid).(! empty($c['Class_Code']) ? ' ('.$c['Class_Code'].')' : '');
                }
            }

            return view('academic.attendances.create', ['classes' => $classes, 'classOptions' => $classOptions]);
        } catch (\Exception $e) {
            \Log::error('AttendanceController@create error: '.$e->getMessage());

            return view('academic.attendances.create', ['classes' => collect([]), 'classOptions' => []]);
        }
    }

    public function store(StoreAttendanceRequest $request)
    {
        try {
            $students = $request->input('students', []);
            $classId = $request->input('Class_ID');
            $date = $request->input('Attendance_Date');
            $studentRepo = app(StudentRepositoryInterface::class);
            $validStudentIds = collect($studentRepo->fetchAll())
                ->filter(function ($student) use ($classId) {
                    return ($student['Class_ID'] ?? '') === $classId
                        && strtoupper(trim($student['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                })
                ->pluck('Student_ID')
                ->filter()
                ->values()
                ->all();

            // Validate the entire batch before the first non-transactional
            // Sheets write. A bad row later in the payload must not produce a
            // rejected response after earlier students were already persisted.
            foreach ($students as $student) {
                if (! isset($student['Student_ID'], $student['Status'])
                    || ! in_array($student['Student_ID'], $validStudentIds, true)) {
                    return back()
                        ->withErrors(['error' => 'Data siswa tidak valid untuk kelas yang dipilih.'])
                        ->withInput();
                }
            }

            $count = 0;
            foreach ($students as $student) {
                if (isset($student['Student_ID']) && isset($student['Status'])) {
                    $attendanceData = [
                        'Student_ID' => $student['Student_ID'],
                        'Status' => $student['Status'],
                        'Attendance_Date' => $date,
                        'Class_ID' => $classId,
                        'Schedule_ID' => $classId, // Legacy compatibility for historical rows that used the class as schedule scope.
                        'Teacher_ID' => auth()->user()->Employee_ID ?? auth()->user()->User_ID,
                        'Notes' => $student['Notes'] ?? '',
                    ];
                    $this->attendanceService->markAttendance($attendanceData);
                    $count++;
                }
            }

            return redirect()->route('attendances.index')->with('success', "Kehadiran $count siswa berhasil dicatat.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $attendance = $this->attendanceService->getById($id);
        $studentsById = collect(app(StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
        $employeesById = collect(app(EmployeeRepositoryInterface::class)->fetchAll())->keyBy('Employee_ID');
        $classesById = collect(app(ClassRepositoryInterface::class)->fetchAll())->keyBy('Class_ID');
        $schedulesById = collect(app(ScheduleRepositoryInterface::class)->fetchAll())->keyBy('Schedule_ID');
        $subjectsById = collect(app(SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

        $studentId = trim((string) ($attendance['Student_ID'] ?? ''));
        $employeeId = trim((string) ($attendance['Employee_ID'] ?? ''));
        $scheduleId = trim((string) ($attendance['Schedule_ID'] ?? ''));

        $attendance['Target_Name'] = $studentId !== ''
            ? HumanReadableResolver::studentName($studentId, $studentsById)
            : HumanReadableResolver::employeeName($employeeId, $employeesById);
        $attendance['Target_Number'] = $studentId !== ''
            ? HumanReadableResolver::studentNumber($studentId, $studentsById)
            : (($employeesById->get($employeeId)['Employee_Number'] ?? $employeesById->get($employeeId)['NIP'] ?? '') ?: '');
        $attendance['Target_Type'] = $studentId !== '' ? 'Siswa' : 'Karyawan';
        $attendance['Schedule_Label'] = $scheduleId !== ''
            ? HumanReadableResolver::scheduleLabel($scheduleId, $schedulesById, $classesById, $subjectsById, $teachersById)
            : HumanReadableResolver::className($attendance['Class_ID'] ?? '', $classesById);
        $attendance['Status_Label'] = AttendanceStatusHelper::label($attendance['Status'] ?? '');

        return view('academic.attendances.show', compact('attendance'));
    }

    public function edit(
        $id,
        StudentRepository $studentRepo,
        EmployeeRepository $employeeRepo,
        ScheduleRepository $scheduleRepo
    ) {
        $attendance = $this->attendanceService->getById($id);
        $students = $studentRepo->fetchAll();
        $employees = $employeeRepo->fetchAll();
        $schedules = $scheduleRepo->fetchAll();

        return view('academic.attendances.edit', compact('attendance', 'students', 'employees', 'schedules'));
    }

    public function update(UpdateAttendanceRequest $request, $id)
    {
        $this->attendanceService->update($id, $request->validated());

        return redirect()->route('attendances.index')->with('success', 'Attendance Updated.');
    }

    public function exportCSV()
    {
        $attendances = $this->attendanceService->getAll();
        $classes = collect(app(ClassRepositoryInterface::class)->fetchAll());
        $schedules = collect(app(ScheduleRepositoryInterface::class)->fetchAll());
        $studentsById = collect(app(StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
        $classesById = $classes->keyBy('Class_ID');
        $schedulesById = $schedules->keyBy('Schedule_ID');
        $subjectsById = collect(app(SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');
        $classifier = new AttendanceLegacyClassifier;
        $file = fopen('php://temp', 'r+');
        $sanitize = fn ($value) => ReportHelper::sanitizeCsvCell($value ?? '');

        fputcsv($file, array_map($sanitize, [
            'Tanggal',
            'Siswa',
            'Kelas',
            'Jadwal',
            'Klasifikasi',
            'Status',
        ]));

        foreach ($attendances as $att) {
            $classified = $classifier->classify($att, $classes, $schedules);
            $scheduleId = $classified['schedule_id'] ?? '';
            $classId = $classified['class_id'] ?? ($att['Class_ID'] ?? '');
            fputcsv($file, array_map($sanitize, [
                $att['Attendance_Date'] ?? '',
                HumanReadableResolver::studentName($att['Student_ID'] ?? '', $studentsById),
                HumanReadableResolver::className($classId, $classesById),
                $scheduleId !== '' ? HumanReadableResolver::scheduleLabel($scheduleId, $schedulesById, $classesById, $subjectsById, $teachersById) : 'Absensi Kelas / QR',
                $classified['classification'] ?? 'UNKNOWN',
                AttendanceStatusHelper::label($att['Status'] ?? ''),
            ]));
        }

        rewind($file);
        $csvData = stream_get_contents($file);
        fclose($file);

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="attendance_export.csv"');
    }

    public function destroy($id)
    {
        try {
            $this->attendanceService->delete($id);

            return redirect()->route('attendances.index')->with('success', 'Data kehadiran berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('attendances.index')->withErrors(['error' => 'Gagal menghapus data: '.$this->safeExceptionMessage($e)]);
        }
    }
}
