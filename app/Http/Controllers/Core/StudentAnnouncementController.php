<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementService;
use App\Services\Core\StudentService;
use Illuminate\Support\Facades\Auth;

class StudentAnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService,
        protected StudentService $studentService,
    ) {
    }

    public function index()
    {
        $student = $this->currentStudent();
        $announcements = $this->announcementService
            ->getActiveAnnouncements('STUDENT', $student['Class_ID'] ?? null)
            ->map(function ($announcement) {
                $announcement = (array) $announcement;
                $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
                $announcement['Start_At_Label'] = $this->formatDate($this->announcementService->startAt($announcement));
                $announcement['Expires_At_Label'] = $this->formatDate($this->announcementService->expiresAt($announcement));
                return $announcement;
            })->values();

        return view('student.portal.announcements.index', compact('announcements'));
    }

    public function show(string $id)
    {
        $student = $this->currentStudent();
        $announcement = $this->announcementService->getVisibleForStudent($id, $student['Class_ID'] ?? null);
        abort_if(!$announcement, 404, 'Pengumuman tidak ditemukan.');
        $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
        $announcement['Start_At_Label'] = $this->formatDate($this->announcementService->startAt($announcement));
        $announcement['Expires_At_Label'] = $this->formatDate($this->announcementService->expiresAt($announcement));
        return view('student.portal.announcements.show', compact('announcement'));
    }

    protected function currentStudent(): array
    {
        $user = Auth::user();
        abort_if(!$user, 403, 'Profil siswa tidak ditemukan.');
        $userId = $user->User_ID ?? Auth::id();
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        abort_if(!$student || empty($student['Student_ID']), 403, 'Profil siswa tidak ditemukan.');
        return (array) $student;
    }

    protected function formatDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i') . ' WIB' : '-';
    }
}
