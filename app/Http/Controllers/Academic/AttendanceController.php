<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\AttendanceStatusHelper;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Core\ActivityLogService;
use App\Support\Reporting\HumanReadableResolver;

class AttendanceController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Attendance_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $attendances = $this->attendanceService->getAll();
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $classRows = $classRepo->fetchAll();
        $classes = $classRows->keyBy('Class_ID');

        $scheduleRepo = app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class);
        $scheduleRows = $scheduleRepo->fetchAll();
        $schedules = $scheduleRows->keyBy('Schedule_ID');

        $subjectRepo = app(\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class);
        $subjects = $subjectRepo->fetchAll()->keyBy('Subject_ID');

        $teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
        $teachers = $teacherRepo->fetchAll()->keyBy('Teacher_ID');

        $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
        $employees = $employeeRepo->fetchAll()->keyBy('Employee_ID');
        $classifier = new AttendanceLegacyClassifier();
        
        return [
            'moduleName' => 'Kehadiran (Attendance)',
            'data' => collect(array_values($attendances->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Siswa', 'Kelas / Jadwal', 'Guru', 'Status', 'Time In', 'Time Out', 'Catatan'],
            'mapRow' => function($row) use ($students, $classes, $classRows, $schedules, $scheduleRows, $subjects, $teachers, $employees, $classifier) {
                $date = '-';
                if (isset($row['Attendance_Date']) && !empty($row['Attendance_Date'])) {
                    try {
                        $date = \Carbon\Carbon::parse($row['Attendance_Date'])->format('d M Y');
                    } catch (\Exception $e) { }
                }

                $classified = $classifier->classify($row, $classRows, $scheduleRows);
                $scheduleId = trim((string) ($classified['schedule_id'] ?? ''));
                $classId = trim((string) ($classified['class_id'] ?? ($row['Class_ID'] ?? '')));
                $target = ($classified['is_schedule_based'] ?? false) && $scheduleId !== ''
                    ? HumanReadableResolver::scheduleLabel($scheduleId, $schedules, $classes, $subjects, $teachers)
                    : HumanReadableResolver::className($classId, $classes) . ' / Absensi Kelas';

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
                    $row['Notes'] ?? '-'
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
        
        $classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
        $allClasses = $classRepo->fetchAll();
        $classes = $allClasses->filter(function($c) {
            $isActive = strtoupper(trim($c['Is_Active'] ?? ''));
            return $isActive === 'TRUE' || $isActive === '';
        })->values();

        $classOptions = [];
        foreach ($classes as $c) {
            $cid = trim((string) ($c['Class_ID'] ?? ''));
            if ($cid !== '') {
                $classOptions[$cid] = ($c['Class_Name'] ?? $cid) . (!empty($c['Class_Code']) ? ' (' . $c['Class_Code'] . ')' : '');
            }
        }

        $scheduleRepo = app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class);
        $schedules = $scheduleRepo->fetchAll()->keyBy('Schedule_ID');

        $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
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

        $paginatedClasses = \App\Helpers\CollectionHelper::paginate($classSummary, 10)->withQueryString();

        return view('academic.attendances.index', compact('paginatedClasses', 'classOptions', 'dateFilter', 'dateEndFilter', 'search', 'statusFilter', 'classFilter'));
    }
public function create()
    {
        try {
            $classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
            $allClasses = $classRepo->fetchAll();
            
            $classes = $allClasses->filter(function($c) {
                $isActive = strtoupper(trim($c['Is_Active'] ?? ''));
                return $isActive === 'TRUE' || $isActive === '';
            })->values();
            
            // Build classOptions in controller to avoid any Blade issues
            $classOptions = [];
            foreach ($classes as $c) {
                $cid = trim((string) ($c['Class_ID'] ?? ''));
                if ($cid !== '') {
                    $classOptions[$cid] = ($c['Class_Name'] ?? $cid) . (!empty($c['Class_Code']) ? ' (' . $c['Class_Code'] . ')' : '');
                }
            }
            
            return view('academic.attendances.create', ['classes' => $classes, 'classOptions' => $classOptions]);
        } catch (\Exception $e) {
            \Log::error('AttendanceController@create error: ' . $e->getMessage());
            return view('academic.attendances.create', ['classes' => collect([]), 'classOptions' => []]);
        }
    }

    public function store(\App\Http\Requests\StoreAttendanceRequest $request)
    {
        try {
            $students = $request->input('students', []);
            $classId = $request->input('Class_ID');
            $date = $request->input('Attendance_Date');
            $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
            $validStudentIds = collect($studentRepo->fetchAll())
                ->filter(function ($student) use ($classId) {
                    return ($student['Class_ID'] ?? '') === $classId
                        && strtoupper(trim($student['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                })
                ->pluck('Student_ID')
                ->filter()
                ->values()
                ->all();
            
            $count = 0;
            foreach ($students as $student) {
                if (isset($student['Student_ID']) && isset($student['Status'])) {
                    if (!in_array($student['Student_ID'], $validStudentIds, true)) {
                        return back()
                            ->withErrors(['error' => 'Data siswa tidak valid untuk kelas yang dipilih.'])
                            ->withInput();
                    }

                    $attendanceData = [
                        'Student_ID' => $student['Student_ID'],
                        'Status' => $student['Status'],
                        'Attendance_Date' => $date,
                        'Class_ID' => $classId,
                        'Schedule_ID' => $classId, // Workaround: Google Sheets lacks Class_ID column, use Schedule_ID
                        'Teacher_ID' => auth()->user()->Employee_ID ?? auth()->user()->User_ID,
                        'Notes' => $student['Notes'] ?? ''
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
        $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
        $employeesById = collect(app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll())->keyBy('Employee_ID');
        $classesById = collect(app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll())->keyBy('Class_ID');
        $schedulesById = collect(app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll())->keyBy('Schedule_ID');
        $subjectsById = collect(app(\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

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
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo,
        \App\Repositories\GoogleSheets\EmployeeRepository $employeeRepo,
        \App\Repositories\GoogleSheets\ScheduleRepository $scheduleRepo
    ) {
        $attendance = $this->attendanceService->getById($id);
        $students = $studentRepo->fetchAll();
        $employees = $employeeRepo->fetchAll();
        $schedules = $scheduleRepo->fetchAll();
        return view('academic.attendances.edit', compact('attendance', 'students', 'employees', 'schedules'));
    }

    public function update(\App\Http\Requests\UpdateAttendanceRequest $request, $id)
    {
        $this->attendanceService->update($id, $request->validated());
        return redirect()->route('attendances.index')->with('success', 'Attendance Updated.');
    }

    public function exportCSV()
    {
        $attendances = $this->attendanceService->getAll();
        $classes = collect(app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll());
        $schedules = collect(app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll());
        $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
        $classesById = $classes->keyBy('Class_ID');
        $schedulesById = $schedules->keyBy('Schedule_ID');
        $subjectsById = collect(app(\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');
        $classifier = new AttendanceLegacyClassifier();
        $file = fopen('php://temp', 'r+');
        $sanitize = fn($value) => \App\Helpers\ReportHelper::sanitizeCsvCell($value ?? '');

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
            return redirect()->route('attendances.index')->withErrors(['error' => 'Gagal menghapus data: ' . $this->safeExceptionMessage($e)]);
        }
    }
}
