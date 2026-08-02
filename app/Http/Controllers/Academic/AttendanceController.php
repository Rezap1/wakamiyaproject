<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AttendanceService;
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
                    $row['Status'] ?? '-',
                    $row['Time_In'] ?? '-',
                    $row['Time_Out'] ?? '-',
                    $row['Notes'] ?? '-'
                ];
            },
            'isLandscape' => true,
        ];
    }

    protected $attendanceService, $activityLogService;

    public function __construct(AttendanceService $attendanceService, ActivityLogService $activityLogService)
    {
        $this->attendanceService = $attendanceService;
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        $attendances = $this->attendanceService->getAll();
        
        // Load Classes for the filter dropdown
        $classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
        $allClasses = $classRepo->fetchAll();
        $classes = $allClasses->filter(function($c) {
            $isActive = strtoupper(trim($c['Is_Active'] ?? ''));
            return $isActive === 'TRUE' || $isActive === '';
        })->values();

        // Determine user role (kept for potential future use)
        $user = auth()->user();
        $userRole = 'UNKNOWN';
        if ($user && isset($user->Role_ID)) {
            try {
                $roleService = app(\App\Services\Core\RoleService::class);
                $role = $roleService->getRoleById($user->Role_ID);
                $userRole = strtoupper(trim($role['Role_Name'] ?? 'UNKNOWN'));
            } catch (\Exception $e) {}
        }
        
        // Build classOptions for the view (show all active classes)
        $classOptions = [];
        foreach ($classes as $c) {
            $cid = trim((string) ($c['Class_ID'] ?? ''));
            if ($cid !== '') {
                $classOptions[$cid] = ($c['Class_Name'] ?? $cid) . (!empty($c['Class_Code']) ? ' (' . $c['Class_Code'] . ')' : '');
            }
        }

        // Load Students for name mapping
        $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        // Apply filters
        $dateFilter = $request->input('date', date('Y-m-d'));
        $classFilter = $request->input('class_id');
        
        $debug = [
            'raw_count' => $attendances->count(),
            'dateFilter' => $dateFilter,
            'classFilter' => $classFilter,
            'userRole' => $userRole,
        ];
        
        if ($dateFilter) {
            $attendances = $attendances->filter(function($a) use ($dateFilter) {
                try {
                    if (empty($a['Attendance_Date'])) return false;
                    $aDate = \Carbon\Carbon::parse(str_replace('/', '-', $a['Attendance_Date']))->format('Y-m-d');
                    return $aDate === $dateFilter;
                } catch (\Exception $e) {
                    return false;
                }
            });
            $debug['after_date_filter'] = $attendances->count();
        }
        
        if ($classFilter) {
            $attendances = $attendances->filter(function($a) use ($classFilter) {
                $cId = $a['Class_ID'] ?? $a['Schedule_ID'] ?? '';
                return $cId === $classFilter;
            });
            $debug['after_class_filter'] = $attendances->count();
        }

                \Log::info('Attendance Index Debug:', $debug);

        $attendances = \App\Helpers\CollectionHelper::paginate($attendances, 10)->withQueryString();

        return view('academic.attendances.index', compact('attendances', 'classOptions', 'students'));
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
            
            // Debug log
            file_put_contents(storage_path('logs/attendance_debug.json'), json_encode([
                'timestamp' => now()->toDateTimeString(),
                'classes_count' => $classes->count(),
                'classOptions' => $classOptions,
            ], JSON_PRETTY_PRINT));
            
            return view('academic.attendances.create', ['classes' => $classes, 'classOptions' => $classOptions]);
        } catch (\Exception $e) {
            \Log::error('AttendanceController@create error: ' . $e->getMessage());
            return view('academic.attendances.create', ['classes' => collect([])]);
        }
    }

    public function store(\App\Http\Requests\StoreAttendanceRequest $request)
    {
        try {
            $students = $request->input('students', []);
            $classId = $request->input('Class_ID');
            $date = $request->input('Attendance_Date');
            
            $count = 0;
            foreach ($students as $student) {
                if (isset($student['Student_ID']) && isset($student['Status'])) {
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
            
            $this->activityLogService->log(auth()->id(), 'MARK_ATTENDANCE', 'Attendance', "Marked attendance for $count students in class $classId");
            return redirect()->route('attendances.index')->with('success', "Kehadiran $count siswa berhasil dicatat.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
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
        $this->activityLogService->log(auth()->id(), 'UPDATE_ATTENDANCE', 'Attendance', 'Updated attendance ' . $id);
        return redirect()->route('attendances.index')->with('success', 'Attendance Updated.');
    }

    public function exportCSV()
    {
        $attendances = $this->attendanceService->getAll();
        $csvData = "Attendance_ID,Schedule_ID,Student_ID,Status,Date\n";
        foreach ($attendances as $att) {
            $csvData .= ($att['Attendance_ID']??'').",".($att['Schedule_ID']??'').",".($att['Student_ID']??'').",".($att['Status']??'').",".($att['Attendance_Date']??'')."\n";
        }
        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="attendance_export.csv"');
    }

    public function destroy($id)
    {
        try {
            $this->attendanceService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETE_ATTENDANCE', 'Attendance', 'Deleted attendance ' . $id);
            return redirect()->route('attendances.index')->with('success', 'Data kehadiran berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('attendances.index')->withErrors(['error' => 'Gagal menghapus data: ' . $e->getMessage()]);
        }
    }
}
