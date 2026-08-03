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
        $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : 'U-002'; // fallback for testing
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        
        $assignments = [];
        if ($student && !empty($student['Class_ID'])) {
            $allAssignments = collect($this->assignmentService->getAll());
            $assignments = $allAssignments->filter(function($item) use ($student) {
                return ($item['Class_ID'] ?? '') == $student['Class_ID'];
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
        
        $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : '';
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        
        $submissionRepo = app(\App\Interfaces\GoogleSheets\SubmissionRepositoryInterface::class);
        $submissions = collect($submissionRepo->fetchAll());
        $mySubmission = $submissions->filter(function($s) use ($id, $student) {
            return ($s['Assignment_ID'] ?? '') === $id && ($s['Student_ID'] ?? '') === ($student['Student_ID'] ?? '');
        })->first();

        return view('student.portal.assignment_show', compact('assignment', 'mySubmission'));
    }

    public function uploadSubmission(Request $request, $assignmentId)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'comments' => 'nullable|string'
        ]);

        try {
            $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : '';
            $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
            if (!$student) throw new \Exception('Data siswa tidak valid.');

            $file = $request->file('file');
            $filename = 'sub_' . $assignmentId . '_' . $student['Student_ID'] . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('submissions', $filename, 'public');
            $filePath = 'storage/submissions/' . $filename;

            $submissionRepo = app(\App\Interfaces\GoogleSheets\SubmissionRepositoryInterface::class);
            
            $data = [
                'Submission_ID' => $submissionRepo->generateNewId('SUB', 6),
                'Assignment_ID' => $assignmentId,
                'Student_ID' => $student['Student_ID'],
                'Submission_Date' => now()->toDateTimeString(),
                'File_URL' => '/' . $filePath,
                'Comment' => $request->comments ?? '',
                'Grade_Received' => '',
                'Feedback' => '',
                'Status' => 'Submitted',
                'Created_At' => now()->toDateTimeString(),
            ];

            $submissionRepo->create($data);
            $submissionRepo->clearCache();

            return back()->with('success', 'Tugas berhasil diunggah.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function materials()
    {
        $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : '';
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        $classId = $student['Class_ID'] ?? null;

        // Fetch announcements as materials
        $announcementService = app(\App\Services\Academic\AnnouncementService::class);
        $announcements = $announcementService->getActiveAnnouncements('STUDENT', $classId);

        // Fetch subjects for this student's class
        $subjects = collect([]);
        if ($classId) {
            $subjectService = app(\App\Services\Academic\SubjectService::class);
            $subjects = $subjectService->getAll();
        }

        return view('student.portal.materials', compact('announcements', 'subjects'));
    }
}
