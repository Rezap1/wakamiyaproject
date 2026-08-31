<?php
namespace App\Services\Dashboard;

use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Core\ProgramService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\TeacherService;
use App\Services\Core\StudentService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use App\Services\Attendance\AttendanceRequestService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
    protected $attendanceRequestService;
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
        AttendanceRequestService $attendanceRequestService,
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
        $this->attendanceRequestService = $attendanceRequestService;
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
        $legacyClassifier = new AttendanceLegacyClassifier();
        $academicAttendances = $attendances->filter(function ($attendance) use ($legacyClassifier, $classes, $schedules) {
            $classified = $legacyClassifier->classify($attendance, $classes, $schedules);
            return !in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)
                && !empty($attendance['Student_ID']);
        })->values();
        $attendanceRequests = collect($this->attendanceRequestService->getAll());

        // === Attendance Rate (riil) ===
        $totalAttendance = $academicAttendances->count();
        $presentCount = $academicAttendances->filter(function ($attendance) {
            return in_array(strtoupper(trim($attendance['Status'] ?? '')), ['PRESENT', 'HADIR', 'LATE', 'TERLAMBAT'], true);
        })->count();
        $attendanceRate = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100) : 0;

        // === Score Stats ===
        $numericScores = $scores->filter(fn ($score) => is_numeric($score['Score_Value'] ?? $score['Score'] ?? null));
        $avgScore = $numericScores->count() > 0 ? round($numericScores->avg(function ($score) {
            return (float) ($score['Score_Value'] ?? $score['Score']);
        }), 1) : 0;
        $passed = $scores->filter(fn ($score) => strtoupper(trim($score['Status'] ?? '')) === 'PASS')->count();
        $passingRate = $scores->count() > 0 ? round(($passed / $scores->count()) * 100) : 0;

        // === Score Pending (assessments tanpa score) ===
        $pendingScoreCount = $this->countAssessmentsWithoutFullScores($assessments, $scores, $students);

        $todayDate = date('Y-m-d');

        $unplacedStudents = $students->filter(function($s) {
            return empty(trim($s['Class_ID'] ?? '')) || trim($s['Class_ID'] ?? '') === '-';
        })->count();

        $attendanceToday = $academicAttendances->filter(function($a) use ($todayDate) {
            return ($a['Attendance_Date'] ?? '') === $todayDate
                && in_array(strtoupper(trim($a['Status'] ?? '')), ['PRESENT', 'HADIR', 'LATE', 'TERLAMBAT', 'SICK', 'SAKIT', 'PERMITTED', 'IZIN', 'ABSENT', 'ALPHA', 'ALPA'], true);
        })->count();

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
            'pending_requests' => $attendanceRequests->where('Status', 'PENDING')->count(),
            'student_unplaced' => $unplacedStudents,
            'attendance_today' => $attendanceToday,
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
        $todayScheduleCount = count($todayClasses);
        $todayClassIds = $todayClasses ? collect($todayClasses)->pluck('class')->filter()->unique() : collect();
        $todayAttendanceClassIds = $academicAttendances->filter(function ($a) use ($todayDate) {
            return ($a['Attendance_Date'] ?? '') === $todayDate;
        })->map(function ($a) use ($legacyClassifier, $classes, $schedules) {
            return $legacyClassifier->classify($a, $classes, $schedules)['class_id'] ?? '';
        })->filter()->unique();
        $attendancePending = max(0, $todayClassIds->diff($todayAttendanceClassIds)->count());
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

    private function countAssessmentsWithoutFullScores($assessments, $scores, $students): int
    {
        return collect($assessments)->filter(function ($assessment) use ($scores, $students) {
            $assessmentId = $assessment['Assessment_ID'] ?? '';
            if ($assessmentId === '') {
                return false;
            }

            $assessmentScores = collect($scores)->where('Assessment_ID', $assessmentId);
            $classId = trim((string) ($assessment['Class_ID'] ?? ''));
            if ($classId === '') {
                return $assessmentScores->isEmpty();
            }

            $classStudentIds = collect($students)
                ->filter(fn ($student) => trim((string) ($student['Class_ID'] ?? '')) === $classId)
                ->pluck('Student_ID')
                ->filter()
                ->unique()
                ->values();

            if ($classStudentIds->isEmpty()) {
                return $assessmentScores->isEmpty();
            }

            $scoredStudentIds = $assessmentScores
                ->whereIn('Student_ID', $classStudentIds->all())
                ->pluck('Student_ID')
                ->filter()
                ->unique();

            return $scoredStudentIds->count() < $classStudentIds->count();
        })->count();
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
