<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Attendance\AttendanceRequestService;
use Carbon\Carbon;
use App\Helpers\AttendanceStatusHelper;
use App\Helpers\DateHelper;

class AttendanceHistoryController extends Controller
{
    protected $attendanceRepository;
    protected $studentRepository;
    protected $requestService;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        StudentRepositoryInterface $studentRepository,
        AttendanceRequestService $requestService
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->studentRepository = $studentRepository;
        $this->requestService = $requestService;
    }

    public function index(Request $request)
    {
        // 1. Strict Identity Resolution (IDOR Protection)
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        // 2. Fetch the single student identity
        $userId = trim((string) ($user->User_ID ?? ''));
        $student = collect($this->studentRepository->fetchAll())->first(function ($candidate) use ($userId) {
            return $userId !== '' && trim((string) ($candidate['User_ID'] ?? '')) === $userId;
        });

        if (!$student) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $studentId = trim((string) ($student['Student_ID'] ?? ''));
        if ($studentId === '') {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        // 3. Fetch all attendances and filter securely
        $allAttendances = collect($this->attendanceRepository->fetchAll());
        
        $myAttendances = $allAttendances->filter(function ($att) use ($studentId) {
            $attStudentId = trim((string) ($att['Student_ID'] ?? ''));
            return $attStudentId === $studentId;
        })->sort(fn ($left, $right) => $this->compareAttendanceNewestFirst($left, $right))->values();

        // 4. KPIs are calculated from this student's complete history. The
        // visible list is limited separately and never deletes persisted rows.
        $currentMonth = date('Y-m');
        
        $attendancesThisMonth = $myAttendances->filter(function($a) use ($currentMonth) {
            try {
                if (empty($a['Attendance_Date'])) return false;
                $aMonth = Carbon::parse(str_replace('/', '-', $a['Attendance_Date']))->format('Y-m');
                return $aMonth === $currentMonth;
            } catch (\Exception $e) { return false; }
        });

        $hadirBulanIni = $attendancesThisMonth->filter(fn($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'PRESENT')->count();
        $terlambatBulanIni = $attendancesThisMonth->filter(fn($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'LATE')->count();
        $totalPresensiSaya = $myAttendances->count();

        // 5. Student presentation rule: filter first, then sort, then limit.
        $latestAttendances = $myAttendances->take(5)->values();

        // 6. Enrich only the five visible rows with request status data.
        $myRequests = $this->requestService->getStudentRequests($studentId);
        $latestAttendances = $latestAttendances->map(function ($att) use ($myRequests) {
            $attId = $att['Attendance_ID'] ?? '';
            $attendanceType = strtoupper(trim((string) ($att['Attendance_Type'] ?? '')));
            $attendanceClassId = trim((string) ($att['Class_ID'] ?? ''));
            $attendanceScheduleId = trim((string) ($att['Schedule_ID'] ?? ''));
            $attendanceDate = $att['Attendance_Date'] ?? $att['Date'] ?? '';
            $request = $myRequests->first(function ($item) use ($attId, $attendanceType, $attendanceClassId, $attendanceScheduleId, $attendanceDate) {
                if (($item['Attendance_ID'] ?? '') === $attId) {
                    return true;
                }
                if (($item['Attendance_Date'] ?? '') !== $attendanceDate) {
                    return false;
                }

                $requestType = strtoupper(trim((string) ($item['Attendance_Type'] ?? '')));
                if ($attendanceType === 'CLASS_QR') {
                    $requestClassId = trim((string) ($item['Class_ID'] ?? ''));
                    return ($requestType === 'CLASS_QR' || ($requestType === '' && empty($item['Schedule_ID'])))
                        && ($requestClassId === '' || $requestClassId === $attendanceClassId);
                }
                if ($attendanceType === 'SCHEDULE') {
                    return ($requestType === 'SCHEDULE' || ($requestType === '' && !empty($item['Schedule_ID'])))
                        && trim((string) ($item['Schedule_ID'] ?? '')) === $attendanceScheduleId;
                }
                return false;
            });
            if ($request) {
                $att['Request_Status'] = $request['Status'];
                $att['Request_Type'] = $request['Request_Type'];
            }
            return $att;
        });

        return view('attendance.my_history', compact(
            'latestAttendances', 'student', 'hadirBulanIni', 'terlambatBulanIni', 'totalPresensiSaya'
        ));
    }

    private function compareAttendanceNewestFirst(array $left, array $right): int
    {
        $leftKey = $this->attendanceSortKey($left);
        $rightKey = $this->attendanceSortKey($right);

        foreach ([0, 1, 2] as $index) {
            if ($leftKey[$index] !== $rightKey[$index]) {
                return $rightKey[$index] <=> $leftKey[$index];
            }
        }

        return strcmp($rightKey[3], $leftKey[3]);
    }

    private function attendanceSortKey(array $attendance): array
    {
        $createdAt = DateHelper::parse($attendance['Created_At'] ?? null);
        $canonicalDate = trim((string) ($attendance['Attendance_Date'] ?? ''));
        $attendanceDate = DateHelper::parse($canonicalDate !== '' ? $canonicalDate : ($attendance['Date'] ?? null));
        $dateTimestamp = $attendanceDate?->copy()->startOfDay()->timestamp
            ?? $createdAt?->copy()->startOfDay()->timestamp
            ?? 0;

        $checkInSeconds = $this->timeInSeconds($attendance['Check_In_Time'] ?? null);
        if ($checkInSeconds === null) {
            $checkInSeconds = $createdAt
                ? ($createdAt->hour * 3600) + ($createdAt->minute * 60) + $createdAt->second
                : 0;
        }

        return [
            $dateTimestamp,
            $checkInSeconds,
            $createdAt?->timestamp ?? 0,
            trim((string) ($attendance['Attendance_ID'] ?? '')),
        ];
    }

    private function timeInSeconds(mixed $value): ?int
    {
        $time = trim((string) $value);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $time, $parts)) {
            return null;
        }

        $hour = (int) $parts[1];
        $minute = (int) $parts[2];
        $second = (int) ($parts[3] ?? 0);
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
