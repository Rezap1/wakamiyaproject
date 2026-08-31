<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\AttendanceStatusHelper;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Core\ActivityLogService;

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
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        
        return [
            'moduleName' => 'Kehadiran (Attendance)',
            'data' => collect(array_values($attendances->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Siswa', 'Kelas / Jadwal', 'Guru', 'Status', 'Time In', 'Time Out', 'Catatan'],
            'mapRow' => function($row) use ($students, $classes) {
                $studentId = $row['Student_ID'] ?? null;
                $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
                
                $classId = $row['Class_ID'] ?? null;
                $className = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : $classId;
                
                $date = '-';
                if (isset($row['Attendance_Date']) && !empty($row['Attendance_Date'])) {
                    try {
                        $date = \Carbon\Carbon::parse($row['Attendance_Date'])->format('d M Y');
                    } catch (\Exception $e) { }
                }

                return [
                    $date,
                    $studentName . ($studentId ? " ($studentId)" : ''),
                    trim($className . ' ' . ($row['Schedule_ID'] ?? '')) ?: '-',
                    $row['Teacher_ID'] ?? $row['Employee_ID'] ?? '-',
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
        $this->attendanceService->update($id, $request->except(['_token', '_method']));
        return redirect()->route('attendances.index')->with('success', 'Attendance Updated.');
    }

    public function exportCSV()
    {
        $attendances = $this->attendanceService->getAll();
        $classes = collect(app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll());
        $schedules = collect(app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll());
        $classifier = new AttendanceLegacyClassifier();
        $file = fopen('php://temp', 'r+');
        $sanitize = fn($value) => \App\Helpers\ReportHelper::sanitizeCsvCell($value ?? '');

        fputcsv($file, array_map($sanitize, [
            'Attendance_ID',
            'Schedule_ID',
            'Original_Schedule_ID',
            'Class_ID',
            'Classification',
            'Student_ID',
            'Status',
            'Date',
        ]));

        foreach ($attendances as $att) {
            $classified = $classifier->classify($att, $classes, $schedules);
            fputcsv($file, array_map($sanitize, [
                $att['Attendance_ID'] ?? '',
                $classified['schedule_id'] ?? '',
                $classified['original_schedule_id'] ?? '',
                $classified['class_id'] ?? '',
                $classified['classification'] ?? 'UNKNOWN',
                $att['Student_ID'] ?? '',
                $att['Status'] ?? '',
                $att['Attendance_Date'] ?? '',
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
