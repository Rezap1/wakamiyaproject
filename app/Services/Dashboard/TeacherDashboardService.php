<?php
namespace App\Services\Dashboard;

use App\Services\Academic\ScheduleService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;
use App\Services\Core\TeacherService;
use App\Services\Core\StudentService;
use App\Services\Core\ClassService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherDashboardService
{
    protected $scheduleService;
    protected $attendanceService;
    protected $assessmentService;
    protected $scoreService;
    protected $teacherService;
    protected $studentService;
    protected $classService;
    protected $activityLogService;
    protected $notificationService;

    public function __construct(
        ScheduleService $scheduleService,
        AcademicAttendanceService $attendanceService,
        AssessmentService $assessmentService,
        ScoreService $scoreService,
        TeacherService $teacherService,
        StudentService $studentService,
        ClassService $classService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService
    ) {
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
        $this->teacherService = $teacherService;
        $this->studentService = $studentService;
        $this->classService = $classService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    public function getDashboardData()
    {
        $userId = Auth::id() ?? 'U-001';

        // === Resolve Teacher ID (fetch once) ===
        $allTeachers = collect($this->teacherService->getAllTeachers());
        $teacher = $allTeachers->firstWhere('User_ID', $userId);
        $teacherId = $teacher['Teacher_ID'] ?? null;

        // === Fetch data ONCE ===
        $allSchedules = collect($this->scheduleService->getAll());
        $allAttendances = collect($this->attendanceService->getAll());
        $allAssessments = collect($this->assessmentService->getAll());
        $allScores = collect($this->scoreService->getAll());
        $allStudents = collect($this->studentService->getAllStudents())->where('Is_Active', '!=', 'FALSE');

        // === Filter by Teacher ===
        $mySchedules = $teacherId ? $allSchedules->where('Teacher_ID', $teacherId) : $allSchedules;
        $myAssessments = $teacherId ? $allAssessments->where('Teacher_ID', $teacherId) : $allAssessments;

        // === Today's Classes from MASTER_SCHEDULE ===
        $todayIndo = $this->getTodayIndo();
        $todayDate = date('Y-m-d');

        $todayClassesRaw = $mySchedules->filter(function ($s) use ($todayIndo) {
            return strtolower($s['Day'] ?? $s['Day_Of_Week'] ?? '') === strtolower($todayIndo);
        })->values();

        $todayClasses = $todayClassesRaw->map(function ($s) {
            return [
                'time'    => ($s['Start_Time'] ?? '') . ' - ' . ($s['End_Time'] ?? ''),
                'subject' => $s['Subject_ID'] ?? 'Subject',
                'room'    => $s['Room'] ?? 'Room',
                'class'   => $s['Class_ID'] ?? '',
            ];
        })->toArray();

        // === My Students (from classes I teach or homeroom) ===
        $homeroomClassIds = collect($this->classService->getAllClasses())->pluck('Class_ID');
        $myClassIds = $mySchedules->pluck('Class_ID')->merge($homeroomClassIds)->unique()->filter();
        $myStudentCount = $allStudents->filter(function ($s) use ($myClassIds) {
            return $myClassIds->contains($s['Class_ID'] ?? '');
        })->count();

        // === Attendance Pending (today's schedules without attendance record) ===
        $todayAttendedSchedules = $allAttendances->filter(function ($a) use ($todayDate) {
            return ($a['Attendance_Date'] ?? '') === $todayDate;
        })->pluck('Schedule_ID')->unique();
        $todayScheduleIds = $todayClassesRaw->pluck('Schedule_ID')->unique();
        $attendancePending = $todayScheduleIds->diff($todayAttendedSchedules)->count();

        // === Assessment Pending (my assessments not yet fully scored) ===
        $myAssessmentIds = $myAssessments->pluck('Assessment_ID')->unique();
        $scoredAssessmentIds = $allScores->pluck('Assessment_ID')->unique();
        $assessmentPending = $myAssessmentIds->diff($scoredAssessmentIds)->count();

        // === Score Pending (scores with Draft/Pending status for my assessments) ===
        $scorePending = $allScores->filter(function ($s) use ($myAssessmentIds) {
            return $myAssessmentIds->contains($s['Assessment_ID'] ?? '') &&
                   in_array(($s['Status'] ?? ''), ['Draft', 'Pending', '']);
        })->count();

        // Gaji Bulan Ini
        $mySalaryStatus = 'Belum Diterima';
        try {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $userId);
            if ($employee) {
                $payrolls = collect($payrollRepo->getAll())
                    ->where('Employee_ID', $employee['Employee_ID'])
                    ->where('Status', 'Paid')
                    ->sortByDesc('Created_At');
                
                $latestPayroll = $payrolls->first();
                if ($latestPayroll) {
                    $monthCreated = \Carbon\Carbon::parse($latestPayroll['Created_At'])->format('Y-m');
                    $currentMonth = \Carbon\Carbon::now()->format('Y-m');
                    if ($monthCreated === $currentMonth || \Carbon\Carbon::parse($latestPayroll['Paid_Date'] ?? now())->format('Y-m') === $currentMonth) {
                        $mySalaryStatus = 'Diterima';
                    }
                }
            }
        } catch (\Exception $e) {}

        // === KPI ===
        $kpi = [
            'today_classes'      => $todayClassesRaw->count(),
            'my_students'        => $myStudentCount,
            'attendance_pending' => $attendancePending,
            'assessment_pending' => $assessmentPending,
            'score_pending'      => $scorePending,
            'salary_status'      => $mySalaryStatus,
        ];

        // === Reminders (data riil) ===
        $reminders = [];

        foreach ($todayClasses as $cls) {
            $reminders[] = [
                'title'       => 'Class Today: ' . ($cls['subject'] ?? 'Unknown'),
                'description' => ($cls['time'] ?? '') . ' in ' . ($cls['room'] ?? ''),
                'action_url'  => route('schedules.index'),
            ];
        }

        if ($attendancePending > 0) {
            $reminders[] = [
                'title'       => 'Attendance Pending',
                'description' => $attendancePending . ' sesi absensi belum diisi hari ini.',
                'action_url'  => route('attendances.index'),
            ];
        }

        if ($assessmentPending > 0) {
            $reminders[] = [
                'title'       => 'Assessment Pending',
                'description' => $assessmentPending . ' assessment menunggu input nilai.',
                'action_url'  => route('scores.index'),
            ];
        }

        // === Recent Activity (Teacher's own, max 10) ===
        $myClassIdsArray = $myClassIds->toArray();
        $myScheduleIds = $mySchedules->pluck('Schedule_ID')->toArray();
        $myAssessmentIds = collect($mySchedules)->pluck('Subject_ID')->toArray(); // rough proxy for assessments
        $myScoreIds = $allScores->filter(function($s) use ($myAssessmentIds) {
            return in_array($s['Assessment_ID'] ?? '', $myAssessmentIds);
        })->pluck('Score_ID')->toArray();

        $relatedIds = array_merge(
            [$teacherId],
            $myClassIdsArray,
            $myScheduleIds,
            $myAssessmentIds,
            $myScoreIds
        );
        $recentActivities = $this->getRecentActivity($userId, $relatedIds);

        // === Notification Count ===
        $unreadNotifications = 0;
        try {
            $unreadNotifications = $this->notificationService->UnreadCount($userId, 'TEACHER');
        } catch (\Exception $e) {
            $unreadNotifications = 0;
        }

        return compact(
            'kpi', 'todayClasses', 'reminders', 'recentActivities', 'unreadNotifications'
        );
    }

    private function getTodayIndo()
    {
        $dayMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        ];
        return $dayMap[date('l')] ?? date('l');
    }

    private function getRecentActivity($userId, $relatedIds = [])
    {
        try {
            $allowedModules = ['SYSTEM', 'ACADEMIC', 'CLASS', 'SCHEDULE', 'SCORE', 'ASSESSMENT', 'ATTENDANCE', 'TEACHER'];
            $logs = collect($this->activityLogService->getAllLogs());
            return $logs->filter(function ($log) use ($userId, $relatedIds, $allowedModules) {
                $isAllowedModule = in_array(strtoupper($log['Module'] ?? ''), $allowedModules);
                if (!$isAllowedModule) return false;
                
                return ($log['User_ID'] ?? '') === $userId 
                    || ($log['Reference_ID'] ?? '') === $userId
                    || in_array($log['Reference_ID'] ?? '', $relatedIds);
            })->sortByDesc('Created_At')->take(5)->map(function ($log) {
                $desc = $log['Description'] ?? ($log['New_Value'] ?? '');
                if (is_string($desc) && str_starts_with($desc, '{')) {
                    $decoded = json_decode($desc, true);
                    if (is_array($decoded) && isset($decoded['description'])) {
                        $desc = $decoded['description'];
                    } elseif (is_array($decoded) && isset($decoded['title'])) {
                        $desc = $decoded['title'];
                    } else {
                        $action = str_replace('_', ' ', $log['Action'] ?? '');
                        $refId = $log['Reference_ID'] ?? '';
                        $desc = "Aktivitas " . ucwords(strtolower($action)) . ($refId ? " pada {$refId}" : '');
                    }
                }
                return [
                    'title'       => $log['Action'] ?? 'Aktivitas',
                    'description' => ($log['Module'] ?? '') . ' — ' . $desc,
                    'time'        => isset($log['Created_At']) ? Carbon::parse($log['Created_At'])->diffForHumans() : 'Baru saja',
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
