<?php
namespace App\Services\Dashboard;

use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Core\ProgramService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\TeacherService;
use App\Services\Core\StudentService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;

class AcademicDashboardService
{
    protected $assessmentService;
    protected $scoreService;
    protected $programService;
    protected $batchService;
    protected $classService;
    protected $teacherService;
    protected $studentService;
    protected $scheduleService;
    protected $attendanceService;
    protected $activityLogService;
    protected $notificationService;

    public function __construct(
        AssessmentService $assessmentService,
        ScoreService $scoreService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService,
        TeacherService $teacherService,
        StudentService $studentService,
        ScheduleService $scheduleService,
        AcademicAttendanceService $attendanceService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService
    ) {
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
        $this->teacherService = $teacherService;
        $this->studentService = $studentService;
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    public function getDashboardData()
    {
        // === Fetch data ONCE, reuse collections (N+1 prevention) ===
        $programs = collect($this->programService->getAllPrograms())->where('Is_Active', '!=', 'FALSE');
        $batches  = collect($this->batchService->getAllBatches())->where('Is_Active', '!=', 'FALSE');
        $classes  = collect($this->classService->getAllClasses())->where('Is_Active', '!=', 'FALSE');
        $teachers = collect($this->teacherService->getAllTeachers())->where('Is_Active', '!=', 'FALSE');
        $students = collect($this->studentService->getAllStudents())->where('Is_Active', '!=', 'FALSE');
        $schedules = collect($this->scheduleService->getAll())->where('Is_Active', '!=', 'FALSE');
        $assessments = collect($this->assessmentService->getAll());
        $scores = collect($this->scoreService->getAll());
        $attendances = collect($this->attendanceService->getAll());

        // === Attendance Rate (riil) ===
        $totalAttendance = $attendances->count();
        $presentCount = $attendances->whereIn('Status', ['Present', 'Late'])->count();
        $attendanceRate = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100) : 0;

        // === Score Stats ===
        $totalScore = $scores->sum('Score_Value');
        $avgScore = $scores->count() > 0 ? round($totalScore / $scores->count(), 1) : 0;
        $passed = $scores->where('Status', 'PASS')->count();
        $passingRate = $scores->count() > 0 ? round(($passed / $scores->count()) * 100) : 0;

        // === Score Pending (assessments tanpa score) ===
        $assessmentIds = $assessments->pluck('Assessment_ID')->unique();
        $scoredAssessmentIds = $scores->pluck('Assessment_ID')->unique();
        $pendingScoreCount = $assessmentIds->diff($scoredAssessmentIds)->count();

        // === KPI ===
        $kpi = [
            'programs'         => $programs->count(),
            'batches'          => $batches->count(),
            'classes'          => $classes->count(),
            'teachers'         => $teachers->count(),
            'students'         => $students->count(),
            'attendance_rate'  => $attendanceRate . '%',
            'average_score'    => $avgScore,
            'passing_rate'     => $passingRate . '%',
            'total_assessments'=> $assessments->count(),
            'score_pending'    => $pendingScoreCount,
        ];

        // === Today's Schedule ===
        $todayIndo = $this->getTodayIndo();
        $todayClasses = $schedules->filter(function ($s) use ($todayIndo) {
            return strtolower($s['Day'] ?? $s['Day_Of_Week'] ?? '') === strtolower($todayIndo);
        })->map(function ($s) {
            return [
                'time'    => ($s['Start_Time'] ?? '') . ' - ' . ($s['End_Time'] ?? ''),
                'subject' => $s['Subject_ID'] ?? 'Subject',
                'room'    => $s['Room'] ?? 'Room',
                'class'   => $s['Class_ID'] ?? '',
                'teacher' => $s['Teacher_ID'] ?? '',
            ];
        })->values()->toArray();

        // === Reminders (data riil) ===
        $reminders = [];

        // Schedule Today
        if (count($todayClasses) > 0) {
            $reminders[] = [
                'title'       => 'Schedule Today',
                'description' => count($todayClasses) . ' kelas terjadwal hari ini.',
                'action_url'  => route('schedules.index'),
            ];
        }

        // Attendance Pending
        $todayDate = date('Y-m-d');
        $todayScheduleCount = count($todayClasses);
        $todayAttendanceCount = $attendances->filter(function ($a) use ($todayDate) {
            return ($a['Attendance_Date'] ?? '') === $todayDate;
        })->pluck('Schedule_ID')->unique()->count();
        $attendancePending = max(0, $todayScheduleCount - $todayAttendanceCount);
        if ($attendancePending > 0) {
            $reminders[] = [
                'title'       => 'Attendance Pending',
                'description' => $attendancePending . ' sesi absensi belum diisi hari ini.',
                'action_url'  => route('attendances.index'),
            ];
        }

        // Score Pending
        if ($pendingScoreCount > 0) {
            $reminders[] = [
                'title'       => 'Score Pending',
                'description' => $pendingScoreCount . ' penilaian menunggu input nilai.',
                'action_url'  => route('scores.index'),
            ];
        }

        // === Recent Activity (Academic modules, max 10, DESC) ===
        $recentActivities = $this->getRecentActivity(['ACADEMIC', 'CLASS', 'SCHEDULE', 'SCORE', 'ASSESSMENT', 'ATTENDANCE', 'PROGRAM', 'BATCH']);

        // === Notification Count ===
        $userId = Auth::id();
        $unreadNotifications = 0;
        if ($userId) {
            try {
                $unreadNotifications = $this->notificationService->UnreadCount($userId, 'ACADEMIC');
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
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

    private function getRecentActivity(array $modules)
    {
        try {
            $logs = collect($this->activityLogService->getAllLogs());
            return $logs->filter(function ($log) use ($modules) {
                return in_array(strtoupper($log['Module'] ?? ''), $modules);
            })->sortByDesc('Created_At')->take(10)->map(function ($log) {
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
