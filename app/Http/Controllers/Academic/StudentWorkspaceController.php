<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\SubjectService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\AttendanceService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Academic\AssessmentConfigService;
use App\Helpers\ReportHelper;

class StudentWorkspaceController extends Controller
{
    protected $studentRepo;
    protected $scheduleService;
    protected $subjectService;
    protected $scoreService;
    protected $attendanceService;
    protected $attendanceRequestService;
    protected $assessmentConfigService;

    public function __construct(
        StudentRepositoryInterface $studentRepo,
        ScheduleService $scheduleService,
        SubjectService $subjectService,
        ScoreService $scoreService,
        AttendanceService $attendanceService,
        AttendanceRequestService $attendanceRequestService,
        AssessmentConfigService $assessmentConfigService
    ) {
        $this->studentRepo = $studentRepo;
        $this->scheduleService = $scheduleService;
        $this->subjectService = $subjectService;
        $this->scoreService = $scoreService;
        $this->attendanceService = $attendanceService;
        $this->attendanceRequestService = $attendanceRequestService;
        $this->assessmentConfigService = $assessmentConfigService;
    }

    private function getStudentId()
    {
        $user = auth()->user();
        if ($user && isset($user->User_ID)) {
            $student = collect($this->studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return $student['Student_ID'];
            }
        }

        abort(403, 'Profil siswa tidak ditemukan.');
    }

    private function getStudentClassId($studentId)
    {
        $student = $this->studentRepo->findById($studentId);
        return $student['Class_ID'] ?? null;
    }

    private function resolveAttendances($studentId)
    {
        $rawAttendances = collect($this->attendanceService->getAll())->where('Student_ID', $studentId);
        $attendanceRequests = collect($this->attendanceRequestService->getStudentRequests($studentId))->where('Status', 'APPROVED');

        return $rawAttendances->map(function ($att) use ($attendanceRequests) {
            $date = $att['Attendance_Date'] ?? $att['Date'] ?? null;
            $scheduleId = trim((string) ($att['Schedule_ID'] ?? ''));
            $classId = trim((string) ($att['Class_ID'] ?? ''));
            $attendanceType = strtoupper(trim((string) ($att['Attendance_Type'] ?? '')));

            // Match approved requests by their explicit target identity. A
            // class-based request must never be matched through Schedule_ID.
            $matchingRequest = $attendanceRequests->first(function ($req) use ($date, $scheduleId, $classId, $attendanceType) {
                $reqDate = $req['Attendance_Date'] ?? null;
                if ($reqDate !== $date) return false;

                $requestType = strtoupper(trim((string) ($req['Attendance_Type'] ?? '')));
                if ($attendanceType === 'CLASS_QR') {
                    $requestClassId = trim((string) ($req['Class_ID'] ?? ''));
                    return ($requestType === 'CLASS_QR' || ($requestType === '' && empty($req['Schedule_ID'])))
                        && ($requestClassId === '' || $requestClassId === $classId);
                }

                if ($attendanceType === 'SCHEDULE') {
                    return ($requestType === 'SCHEDULE' || ($requestType === '' && !empty($req['Schedule_ID'])))
                        && trim((string) ($req['Schedule_ID'] ?? '')) === $scheduleId;
                }

                return $requestType === ''
                    && ($scheduleId === '' || trim((string) ($req['Schedule_ID'] ?? '')) === $scheduleId);
            });

            if ($matchingRequest) {
                $att['Resolved_Status'] = strtoupper(trim($matchingRequest['Request_Type'] ?? $matchingRequest['Status'] ?? ''));
            } else {
                $att['Resolved_Status'] = strtoupper(trim($att['Status'] ?? ''));
            }

            return $att;
        });
    }

    public function mySchedule()
    {
        // Use IDOR protected User_ID from authenticated session
        $userId = auth()->user()->User_ID ?? auth()->id();

        $contextService = app(\App\Services\Dashboard\DashboardContextService::class);
        $context = $contextService->getContext();

        $studentId = $context['student_id'] ?? $this->getStudentId();
        $batchName = $context['batch'] ?? 'Belum Ada';
        $className = $context['class'] ?? 'Belum Ada';

        // Find Class ID for the authenticated student
        $student = collect($this->studentRepo->fetchAll())->firstWhere('Student_ID', $studentId);
        $classId = $student['Class_ID'] ?? null;

        $schedules = [];
        if ($classId) {
            $allSchedules = collect($this->scheduleService->getAll());
            $allSubjects = collect($this->subjectService->getAll());
            $allTeachers = collect(app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll());

            $dayMap = [
                'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
            ];

            $statusMap = [
                'Scheduled' => 'Terjadwal',
                'Completed' => 'Selesai',
                'Cancelled' => 'Dibatalkan'
            ];

            // Filter strictly by student's authenticated Class_ID
            $schedules = $allSchedules->where('Class_ID', $classId)->map(function($s) use ($allSubjects, $allTeachers, $dayMap, $statusMap) {
                $subject = $allSubjects->firstWhere('Subject_ID', $s['Subject_ID'] ?? '');
                $teacher = $allTeachers->firstWhere('Teacher_ID', $s['Teacher_ID'] ?? '');

                $dayStr = $s['Day'] ?? $s['Day_Of_Week'] ?? '';
                $statusStr = $s['Session_Status'] ?? 'Scheduled';

                return [
                    'date' => $s['Date'] ?? null, // if specific date available
                    'day' => $dayMap[$dayStr] ?? $dayStr,
                    'time' => ($s['Start_Time'] ?? '') . ' - ' . ($s['End_Time'] ?? ''),
                    'subject' => $subject['Subject_Name'] ?? $s['Subject_Name'] ?? $s['Subject_ID'] ?? '',
                    'teacher' => $teacher['Name'] ?? $teacher['Full_Name'] ?? $s['Teacher_Name'] ?? $s['Teacher_ID'] ?? 'Menunggu Pengajar',
                    'room' => $s['Room'] ?? 'Belum Ditentukan',
                    'status' => $statusMap[$statusStr] ?? $statusStr
                ];
            })->values()->toArray();
        }

        return view('academic.student.schedule', compact('schedules', 'studentId', 'batchName', 'className', 'context'));
    }

    public function mySubjects()
    {
        $studentId = $this->getStudentId();
        $classId = $this->getStudentClassId($studentId);
        $subjectIds = collect([]);

        if ($classId) {
            $subjectIds = collect($this->scheduleService->getAll())
                ->where('Class_ID', $classId)
                ->pluck('Subject_ID')
                ->filter()
                ->unique()
                ->values();
        }

        $subjects = collect($this->subjectService->getAll())
            ->whereIn('Subject_ID', $subjectIds->all())
            ->map(function($sub) {
            return [
                'code' => $sub['Subject_ID'] ?? '',
                'name' => $sub['Subject_Name'] ?? '',
                'credits' => $sub['Credit'] ?? 3
            ];
        })->values()->toArray();

        return view('academic.student.subjects', compact('subjects', 'studentId'));
    }

    public function progress()
    {
        $studentId = $this->getStudentId();

        $myScores = collect($this->scoreService->getAll())->where('Student_ID', $studentId);

        $numericScores = $myScores->filter(function($s) {
            return empty($s['Evaluation_Details']) && is_numeric($s['Score'] ?? $s['Score_Value'] ?? null);
        });

        if ($numericScores->count() > 0) {
            $avgScore = round($numericScores->avg(function ($s) {
                return (float) ($s['Score'] ?? $s['Score_Value'] ?? 0);
            }), 1);
        } else {
            $avgScore = 'Belum tersedia';
        }

        $myAttendances = $this->resolveAttendances($studentId);
        $totalMyAttendance = $myAttendances->count();
        $presentCount = $myAttendances->filter(function($a) {
            return in_array($a['Resolved_Status'], ['PRESENT', 'LATE', 'HADIR', 'TERLAMBAT']);
        })->count();
        $attendancePercentage = $totalMyAttendance > 0 ? round(($presentCount / $totalMyAttendance) * 100) : 0;

        $progress = [
            'gpa' => $avgScore,
            'attendance' => $attendancePercentage,
            'total_assessments' => $myScores->count()
        ];

        $assessmentConfigs = collect($this->assessmentConfigService->getActiveCategories())->keyBy('Category_ID')->toArray();

        return view('academic.student.progress', compact('progress', 'studentId', 'myScores', 'myAttendances', 'assessmentConfigs'));
    }

    public function exportScoresCsv()
    {
        return $this->csvResponse('riwayat_nilai.csv', $this->studentScoreHeaders(), $this->studentScoreRows());
    }

    public function exportScoresPdf()
    {
        return $this->studentReportResponse('pdf', 'Riwayat Nilai Siswa', $this->studentScoreHeaders(), $this->studentScoreRows());
    }

    public function printScores()
    {
        return $this->studentReportResponse('print', 'Riwayat Nilai Siswa', $this->studentScoreHeaders(), $this->studentScoreRows());
    }

    private function studentScoreHeaders(): array
    {
        return ['Tanggal', 'Kategori', 'Penilaian', 'Hasil'];
    }

    private function studentScoreRows(): array
    {
        $studentId = $this->getStudentId();
        $scores = collect($this->scoreService->getAll())->where('Student_ID', $studentId);

        $rows = [];

        foreach ($scores as $s) {
            $date = $this->formatCsvDate($s['Created_At'] ?? null);
            $category = strtoupper($s['Assessment_Category'] ?? 'Tidak dikategorikan');
            $assessment = $s['Assessment_ID'] ?? $s['Assignment_ID'] ?? '-';

            $detailsRaw = $s['Evaluation_Details'] ?? null;
            if (!empty($detailsRaw)) {
                $details = json_decode($detailsRaw, true);
                $config = $this->assessmentConfigService->getCategoryConfig($s['Assessment_Category'] ?? '');
                $aspects = !empty($config['Aspects_JSON']) ? json_decode($config['Aspects_JSON'], true) : [];
                $aspectMap = collect($aspects)->pluck('label', 'id')->toArray();

                $summaryArr = [];
                if (is_array($details)) {
                    foreach ($details as $key => $val) {
                        if (strtolower($key) === 'notes') continue;
                        $label = $aspectMap[$key] ?? ucwords(str_replace('_', ' ', $key));
                        $summaryArr[] = "$label: $val";
                    }
                }
                $hasil = implode("; ", $summaryArr);
            } else {
                $hasil = $s['Score'] ?? $s['Score_Value'] ?? '-';
            }

            $rows[] = [$date, $category, $assessment, $hasil];
        }

        return $rows;
    }

    public function exportAttendancesCsv()
    {
        return $this->csvResponse('riwayat_kehadiran.csv', $this->studentAttendanceHeaders(), $this->studentAttendanceRows());
    }

    public function exportAttendancesPdf()
    {
        return $this->studentReportResponse('pdf', 'Riwayat Kehadiran Siswa', $this->studentAttendanceHeaders(), $this->studentAttendanceRows());
    }

    public function printAttendances()
    {
        return $this->studentReportResponse('print', 'Riwayat Kehadiran Siswa', $this->studentAttendanceHeaders(), $this->studentAttendanceRows());
    }

    private function studentAttendanceHeaders(): array
    {
        return ['Tanggal', 'Status'];
    }

    private function studentAttendanceRows(): array
    {
        $studentId = $this->getStudentId();
        $attendances = $this->resolveAttendances($studentId);

        $rows = [];

        foreach ($attendances as $att) {
            $date = $att['Attendance_Date'] ?? $att['Date'] ?? '-';
            $status = $att['Resolved_Status'];

            $translatedStatus = match($status) {
                'PRESENT', 'HADIR' => 'Hadir',
                'LATE', 'TERLAMBAT' => 'Terlambat',
                'SICK', 'SAKIT' => 'Sakit',
                'PERMITTED', 'IZIN' => 'Izin',
                'ABSENT', 'ALPHA', 'ALPA' => 'Alpa',
                default => 'Status tidak diketahui'
            };

            $rows[] = [$date, $translatedStatus];
        }

        return $rows;
    }

    private function studentReportResponse(string $format, string $title, array $headers, array $rows)
    {
        return ReportHelper::export(
            $format,
            $title,
            collect($rows),
            ['summary' => '<tr><td>Total Data</td><td>: ' . count($rows) . '</td></tr>'],
            'pdf.generic_table',
            $headers,
            fn ($row) => $row,
            true
        );
    }

    private function formatCsvDate($value)
    {
        if (empty($value)) {
            return now()->toDateString();
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function csvResponse(string $filename, array $headers, array $rows)
    {
        $file = fopen('php://temp', 'r+');
        $sanitize = [ReportHelper::class, 'sanitizeCsvCell'];

        fputcsv($file, array_map($sanitize, $headers));
        foreach ($rows as $row) {
            fputcsv($file, array_map($sanitize, $row));
        }

        rewind($file);
        $csv = stream_get_contents($file);
        fclose($file);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function calendar()
    {
        $studentId = $this->getStudentId();
        $classId = $this->getStudentClassId($studentId);

        $events = [];
        if ($classId) {
            $allSchedules = collect($this->scheduleService->getAll());
            // Map schedules into events for the calendar
            $events = $allSchedules->where('Class_ID', $classId)->map(function($s) {
                return [
                    'date' => $s['Date'] ?? date('Y-m-d'),
                    'title' => ($s['Subject_ID'] ?? 'Class') . ' - ' . ($s['Topic'] ?? ''),
                    'type' => $s['Type'] ?? 'Class'
                ];
            })->values()->toArray();
        }

        return view('academic.student.calendar', compact('events', 'studentId'));
    }
}
