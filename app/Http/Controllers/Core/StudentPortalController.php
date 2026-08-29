<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AssignmentService;
use App\Services\Core\StudentService;
use Illuminate\Support\Facades\Auth;

class StudentPortalController extends Controller
{
    protected $assignmentService;
    protected $studentService;

    public function __construct(
        AssignmentService $assignmentService,
        StudentService $studentService
    ) {
        $this->assignmentService = $assignmentService;
        $this->studentService = $studentService;
    }

    public function assignments()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $userId = $user->User_ID ?? Auth::id();
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        if (!$student) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $assignments = [];
        if ($student && !empty($student['Class_ID'])) {
            $allAssignments = collect($this->assignmentService->getAll());
            $assignments = $allAssignments->filter(function($item) use ($student) {
                $status = strtoupper(trim(!empty($item['Status']) ? $item['Status'] : 'PUBLISHED'));
                return ($item['Class_ID'] ?? '') == $student['Class_ID']
                    && $status === 'PUBLISHED';
            })->values();
        }

        return view('student.portal.assignments', compact('assignments', 'student'));
    }

    public function showAssignment($id)
    {
        $assignment = $this->assignmentService->getById($id);
        if (!$assignment) {
            return redirect()->route('student.portal.assignments')->withErrors(['error' => 'Tugas tidak ditemukan.']);
        }

        $user = Auth::user();
        $userId = $user ? ($user->User_ID ?? Auth::id()) : '';
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);

        if (!$student || ($student['Class_ID'] ?? '') !== ($assignment['Class_ID'] ?? '')) {
            abort(403, 'Tugas ini bukan untuk kelas Anda.');
        }

        $status = strtoupper(trim(!empty($assignment['Status']) ? $assignment['Status'] : 'PUBLISHED'));
        if ($status !== 'PUBLISHED') {
            abort(404, 'Tugas tidak ditemukan.');
        }

        return view('student.portal.assignment_show', compact('assignment'));
    }

    public function materials()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $userId = $user->User_ID ?? Auth::id();
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        if (!$student) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $classId = $student['Class_ID'] ?? null;

        // Fetch announcements as materials
        $announcementService = app(\App\Services\Academic\AnnouncementService::class);
        $announcements = $announcementService->getActiveAnnouncements('STUDENT', $classId);

        $subjects = collect([]);
        if ($classId) {
            $scheduleService = app(\App\Services\Academic\ScheduleService::class);
            $subjectService = app(\App\Services\Academic\SubjectService::class);
            $subjectIds = collect($scheduleService->getAll())
                ->where('Class_ID', $classId)
                ->pluck('Subject_ID')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $subjects = collect($subjectService->getAll())
                ->whereIn('Subject_ID', $subjectIds)
                ->values();
        }

        return view('student.portal.materials', compact('announcements', 'subjects'));
    }
}
