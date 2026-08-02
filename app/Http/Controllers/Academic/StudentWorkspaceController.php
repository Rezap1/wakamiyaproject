<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\SubjectService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\AttendanceService;

class StudentWorkspaceController extends Controller
{
    protected $studentRepo;
    protected $scheduleService;
    protected $subjectService;
    protected $scoreService;
    protected $attendanceService;

    public function __construct(
        StudentRepositoryInterface $studentRepo,
        ScheduleService $scheduleService,
        SubjectService $subjectService,
        ScoreService $scoreService,
        AttendanceService $attendanceService
    ) {
        $this->studentRepo = $studentRepo;
        $this->scheduleService = $scheduleService;
        $this->subjectService = $subjectService;
        $this->scoreService = $scoreService;
        $this->attendanceService = $attendanceService;
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
        return 'STU-001';
    }

    private function getStudentClassId($studentId)
    {
        $student = $this->studentRepo->findById($studentId);
        return $student['Class_ID'] ?? null;
    }

    public function mySchedule()
    {
        $studentId = $this->getStudentId();
        $classId = $this->getStudentClassId($studentId);
        
        $schedules = [];
        if ($classId) {
            $allSchedules = collect($this->scheduleService->getAll());
            $schedules = $allSchedules->where('Class_ID', $classId)->map(function($s) {
                return [
                    'day' => $s['Day'] ?? $s['Day_Of_Week'] ?? '',
                    'time' => ($s['Start_Time'] ?? '') . ' - ' . ($s['End_Time'] ?? ''),
                    'subject' => $s['Subject_ID'] ?? '',
                    'room' => $s['Room'] ?? ''
                ];
            })->values()->toArray();
        }

        return view('academic.student.schedule', compact('schedules', 'studentId'));
    }

    public function mySubjects()
    {
        $studentId = $this->getStudentId();
        // Since there is no ClassEnrollment mapping subjects explicitly in this simplified version,
        // we fetch all subjects (or ideally filter by program/batch). For now, we list all available subjects.
        $subjects = collect($this->subjectService->getAll())->map(function($sub) {
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
        $avgScore = $myScores->count() > 0 ? round($myScores->avg('Score_Value'), 1) : 0;
        
        $myAttendances = collect($this->attendanceService->getAll())->where('Student_ID', $studentId);
        $totalMyAttendance = $myAttendances->count();
        $presentCount = $myAttendances->whereIn('Status', ['Present', 'Late'])->count();
        $attendancePercentage = $totalMyAttendance > 0 ? round(($presentCount / $totalMyAttendance) * 100) : 0;

        $progress = [
            'gpa' => $avgScore,
            'attendance' => $attendancePercentage,
            'completed_credits' => $myScores->where('Status', 'PASS')->count() * 3, // Assuming 3 credits per subject
            'status' => $avgScore >= 80 ? 'Excellent' : ($avgScore >= 60 ? 'Good' : 'Needs Improvement')
        ];
        return view('academic.student.progress', compact('progress', 'studentId'));
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
