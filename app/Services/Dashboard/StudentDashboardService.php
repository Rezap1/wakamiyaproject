<?php
namespace App\Services\Dashboard;

use App\Services\Academic\ScoreService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Core\StudentService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StudentDashboardService
{
    protected $scoreService;
    protected $scheduleService;
    protected $attendanceService;
    protected $invoiceService;
    protected $paymentService;
    protected $studentService;
    protected $activityLogService;
    protected $notificationService;

    public function __construct(
        ScoreService $scoreService,
        ScheduleService $scheduleService,
        AcademicAttendanceService $attendanceService,
        InvoiceService $invoiceService,
        PaymentService $paymentService,
        StudentService $studentService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService
    ) {
        $this->scoreService = $scoreService;
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->studentService = $studentService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    public function getDashboardData()
    {
        $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : 'U-001';

        // === Resolve Student ID (fetch once) ===
        $allStudents = collect($this->studentService->getAllStudents());
        $student = $allStudents->firstWhere('User_ID', $userId);
        $studentId = $student['Student_ID'] ?? null;
        $studentClassId = $student['Class_ID'] ?? null;

        // === Fetch data ONCE ===
        $allScores = collect($this->scoreService->getAll());
        $allSchedules = collect($this->scheduleService->getAll());
        $allAttendances = collect($this->attendanceService->getAll());
        $allInvoices = collect($this->invoiceService->getAll());
        $allPayments = collect($this->paymentService->getAll());

        // === Filter by Student ===
        $myScores = $studentId ? $allScores->where('Student_ID', $studentId) : collect([]);
        $myAttendances = $studentId ? $allAttendances->where('Student_ID', $studentId) : collect([]);
        $myInvoices = $studentId ? $allInvoices->where('Student_ID', $studentId) : collect([]);
        $myPayments = $studentId ? $allPayments->where('Student_ID', $studentId) : collect([]);

        // === Today's Class from MASTER_SCHEDULE ===
        $todayIndo = $this->getTodayIndo();
        $mySchedules = $studentClassId
            ? $allSchedules->where('Class_ID', $studentClassId)
            : collect([]);
        $todayClasses = $mySchedules->filter(function ($s) use ($todayIndo) {
            return strtolower($s['Day'] ?? $s['Day_Of_Week'] ?? '') === strtolower($todayIndo);
        })->map(function ($s) {
            return [
                'time'    => ($s['Start_Time'] ?? '') . ' - ' . ($s['End_Time'] ?? ''),
                'subject' => $s['Subject_ID'] ?? 'Subject',
                'room'    => $s['Room'] ?? 'Room',
            ];
        })->values()->toArray();
        $todayClassCount = count($todayClasses);

        // === Outstanding Bills ===
        $outstandingBills = $myInvoices->whereIn('Status', ['Waiting Payment', 'Partial Paid']);
        $totalOutstanding = $outstandingBills->sum('Amount');
        $latestInvoice = $outstandingBills->sortByDesc('Created_At')->first();

        // === Payment Progress ===
        $totalPaid = $myPayments->where('Status', 'Verified')->sum('Amount_Paid');
        $totalBilled = $myInvoices->sum('Amount');
        $paymentProgress = $totalBilled > 0 ? min(100, round(($totalPaid / $totalBilled) * 100)) : ($totalPaid > 0 ? 100 : 0);

        $lastPayment = $myPayments->where('Status', 'Verified')->sortByDesc('Payment_Date')->first();
        $nextDueDate = $outstandingBills->whereNotNull('Due_Date')->sortBy('Due_Date')->first();
        $paymentHistory = $myPayments->sortByDesc('Created_At')->take(5)->values();

        // === Latest Score ===
        $latestScore = $myScores->sortByDesc('Created_At')->first();
        $avgScore = $myScores->count() > 0 ? round($myScores->avg(function ($s) {
            $val = $s['Score'] ?? $s['Score_Value'] ?? 0;
            return is_numeric($val) ? (float) $val : 0;
        }), 1) : 0;

        // === Attendance Percentage ===
        $totalMyAttendance = $myAttendances->count();
        $presentCount = $myAttendances->whereIn('Status', ['Present', 'Late'])->count();
        $attendancePercentage = $totalMyAttendance > 0 ? round(($presentCount / $totalMyAttendance) * 100) : 0;

        // === Certificate Status ===
        $allPassed = $myScores->count() > 0 && $myScores->where('Status', '!=', 'PASS')->count() === 0;
        $certificateStatus = $myScores->count() === 0 ? 'Belum Ada Data' : ($allPassed ? 'Memenuhi Syarat' : 'Dalam Proses');

        // === Language Progress ===
        $langProgress = 0;
        $jftScore = $myScores->firstWhere('Assessment_ID', 'JFT-001') ?? $myScores->firstWhere('Assignment_ID', 'JFT-001');
        if ($jftScore) {
            $scoreVal = $jftScore['Score'] ?? $jftScore['Score_Value'] ?? 0;
            $langProgress = $scoreVal >= config('assessment.passing_score', 65) ? 100 : 50;
        }

        $internals = $myScores->map(function ($s) {
            return [
                'name'   => $s['Assessment_ID'] ?? $s['Assignment_ID'] ?? 'Task',
                'status' => $s['Status'] ?? 'Completed',
                'score'  => $s['Score'] ?? $s['Score_Value'] ?? 0,
                'color'  => ($s['Status'] ?? '') == 'PASS' ? 'emerald' : 'red',
            ];
        })->take(4)->toArray();

        // === KPI ===
        $kpi = [
            'today_class'           => $todayClassCount,
            'outstanding_bills'     => $totalOutstanding,
            'latest_score'          => $latestScore['Score'] ?? $latestScore['Score_Value'] ?? 0,
            'attendance_percentage' => $attendancePercentage . '%',
            'certificate_status'    => $certificateStatus,
        ];

        // === Reminders (data riil) ===
        $reminders = [];

        if ($totalOutstanding > 0) {
            $reminders[] = [
                'title'       => 'Tagihan Belum Lunas',
                'description' => 'Terdapat tagihan sebesar Rp ' . number_format($totalOutstanding, 0, ',', '.') . ' yang belum dilunasi.',
                'action_url'  => route('student.billing.index'),
            ];
        }

        foreach ($todayClasses as $cls) {
            $reminders[] = [
                'title'       => 'Jadwal Hari Ini: ' . ($cls['subject'] ?? 'Tidak Diketahui'),
                'description' => ($cls['time'] ?? '') . ' di ' . ($cls['room'] ?? ''),
                'action_url'  => route('student.schedule'),
            ];
        }

        if ($nextDueDate) {
            $reminders[] = [
                'title'       => 'Tagihan Segera Jatuh Tempo',
                'description' => 'Tagihan jatuh tempo: ' . ($nextDueDate['Due_Date'] ?? '—'),
                'action_url'  => route('student.billing.index'),
            ];
        }

        // === Assignments ===
        try {
            $assignmentService = app(\App\Services\Core\AssignmentService::class);
            $allAssignments = collect($assignmentService->getAll());
            $myAssignments = $allAssignments->filter(function($item) use ($studentClassId) {
                return ($item['Class_ID'] ?? '') == $studentClassId;
            })->sortBy('Deadline')->take(5);

            foreach($myAssignments as $assignment) {
                $reminders[] = [
                    'title'       => 'Tugas: ' . ($assignment['Title'] ?? 'Untitled'),
                    'description' => 'Tenggat Waktu: ' . ($assignment['Deadline'] ?? '-'),
                    'action_url'  => route('student.portal.assignments'),
                ];
            }
        } catch (\Exception $e) {
            // Ignore if AssignmentService is not available
        }

        // === Recent Activity (Student's own, max 10) ===
        $myInvoiceIds = $myInvoices->pluck('Invoice_ID')->toArray();
        $myPaymentIds = $myPayments->pluck('Payment_ID')->toArray();
        $myScoreIds = $myScores->pluck('Score_ID')->toArray();
        $myAttendanceIds = $myAttendances->pluck('Attendance_ID')->toArray();

        $relatedIds = array_merge(
            [$studentId], // if Reference_ID is the Student_ID itself
            $myInvoiceIds,
            $myPaymentIds,
            $myScoreIds,
            $myAttendanceIds
        );
        $recentActivities = $this->getRecentActivity($userId, $relatedIds);

        // === Notification Count ===
        $unreadNotifications = 0;
        if ($userId) {
            try {
                $unreadNotifications = $this->notificationService->UnreadCount($userId, 'STUDENT');
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
        }

        return compact(
            'kpi', 'todayClasses', 'myScores', 'langProgress', 'internals',
            'totalOutstanding', 'latestInvoice', 'outstandingBills',
            'paymentProgress', 'lastPayment', 'nextDueDate', 'paymentHistory',
            'attendancePercentage', 'certificateStatus',
            'reminders', 'recentActivities', 'unreadNotifications'
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
            $allowedModules = ['SYSTEM', 'STUDENT', 'FINANCE', 'INVOICE', 'PAYMENT', 'ACADEMIC', 'SCHEDULE', 'SCORE', 'ATTENDANCE', 'PROGRAM', 'CLASS'];
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
