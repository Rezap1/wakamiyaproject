<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Attendance\AttendanceRequestService;
use Carbon\Carbon;
use App\Helpers\CollectionHelper;
use App\Helpers\AttendanceStatusHelper;

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
        $allStudents = collect($this->studentRepository->fetchAll());
        $student = $allStudents->firstWhere('User_ID', $user->User_ID);

        if (!$student) {
            return back()->with('error', 'Profil siswa Anda tidak ditemukan.');
        }

        $studentId = $student['Student_ID'];

        // 3. Fetch all attendances and filter securely
        $allAttendances = collect($this->attendanceRepository->fetchAll());
        
        $myAttendances = $allAttendances->filter(function($att) use ($studentId) {
            $attStudentId = trim($att['Student_ID'] ?? '');
            return $attStudentId === $studentId;
        });

        // 4. Sort by Date DESC
        $myAttendances = $myAttendances->sortByDesc(function($a) {
            $date = $a['Attendance_Date'] ?? '1970-01-01';
            $time = $a['Created_At'] ?? '00:00:00';
            return $date . ' ' . $time;
        })->values();

        // 5. KPIs Calculation
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

        // 6. Enrich with Requests Data
        $myRequests = $this->requestService->getStudentRequests($studentId);
        $myAttendances = $myAttendances->map(function($att) use ($myRequests) {
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

        // 7. Pagination
        $paginated = CollectionHelper::paginate($myAttendances, 25)->withQueryString();

        return view('attendance.my_history', compact(
            'paginated', 'student', 'hadirBulanIni', 'terlambatBulanIni', 'totalPresensiSaya'
        ));
    }
}
