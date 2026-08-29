<?php
namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class GlobalSearchService
{
    public function search($keyword, $role, $userId)
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return [];
        }

        $role = strtoupper(trim((string) $role));
        $context = $this->resolveUserContext($userId, $role);
        $cacheKey = 'wms_search_' . md5($role . '|' . $userId . '|' . mb_strtolower($keyword));

        return Cache::remember($cacheKey, 60, function () use ($keyword, $role, $context) {
            $results = [];

            if (in_array($role, ['ADMINISTRATOR', 'ACADEMIC'], true)) {
                $this->searchStudents($results, $keyword, route('students.index'));
                $this->searchTeachers($results, $keyword, route('teachers.index'));
                $this->searchAcademicMaster($results, $keyword);
                $this->searchAcademicWork($results, $keyword);
            }

            if ($role === 'TEACHER') {
                $this->searchTeacherScope($results, $keyword, $context);
            }

            if ($role === 'STUDENT') {
                $this->searchStudentScope($results, $keyword, $context);
            }

            if (in_array($role, ['ADMINISTRATOR', 'FINANCE', 'DIRECTOR'], true)) {
                $this->searchFinance($results, $keyword);
            }

            if (in_array($role, ['ADMINISTRATOR', 'HR'], true)) {
                $this->searchHr($results, $keyword);
            }

            $this->searchAnnouncements($results, $keyword, $role);

            return array_filter($results, fn ($group) => count($group) > 0);
        });
    }

    public function getHistory($userId)
    {
        return Cache::get("wms_search_history_{$userId}", []);
    }

    public function saveHistory($userId, $keyword)
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') return;

        $history = $this->getHistory($userId);
        $history = array_filter($history, fn ($item) => mb_strtolower($item) !== mb_strtolower($keyword));

        array_unshift($history, $keyword);
        Cache::put("wms_search_history_{$userId}", array_slice($history, 0, 10), 86400 * 30);
    }

    public function clearHistory($userId)
    {
        Cache::forget("wms_search_history_{$userId}");
    }

    private function resolveUserContext($userId, string $role): array
    {
        $context = ['user_id' => $userId, 'role' => $role, 'teacher_id' => null, 'student' => null];

        try {
            if ($role === 'TEACHER') {
                $teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
                $teacher = collect($teacherRepo->fetchAll())->firstWhere('User_ID', $userId);
                $context['teacher_id'] = $teacher['Teacher_ID'] ?? null;
            }

            if ($role === 'STUDENT') {
                $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                $context['student'] = collect($studentRepo->fetchAll())->firstWhere('User_ID', $userId);
            }
        } catch (\Exception $e) {
            return $context;
        }

        return $context;
    }

    private function searchStudents(array &$results, string $keyword, string $url, $students = null): void
    {
        try {
            $students ??= app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll();
            $matches = collect($students)->filter(fn ($student) => $this->matches($student, $keyword, [
                'Student_ID', 'Student_Number', 'Full_Name', 'Name', 'Email', 'Phone_Number', 'Class_ID', 'Batch_ID',
            ]))->take(8);

            foreach ($matches as $student) {
                $this->add($results, 'Siswa', [
                    'title' => $student['Full_Name'] ?? $student['Name'] ?? $student['Student_ID'] ?? 'Siswa',
                    'desc' => trim(($student['Student_Number'] ?? $student['Student_ID'] ?? '') . ' ' . ($student['Class_ID'] ?? '')),
                    'url' => $url,
                ]);
            }
        } catch (\Exception $e) {
        }
    }

    private function searchTeachers(array &$results, string $keyword, string $url): void
    {
        try {
            $teachers = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class)->fetchAll();
            $matches = collect($teachers)->filter(fn ($teacher) => $this->matches($teacher, $keyword, [
                'Teacher_ID', 'Teacher_Code', 'Full_Name', 'Name', 'Email', 'Specialization',
            ]))->take(8);

            foreach ($matches as $teacher) {
                $this->add($results, 'Pengajar', [
                    'title' => $teacher['Full_Name'] ?? $teacher['Name'] ?? $teacher['Teacher_ID'] ?? 'Pengajar',
                    'desc' => trim(($teacher['Teacher_ID'] ?? '') . ' ' . ($teacher['Specialization'] ?? '')),
                    'url' => $url,
                ]);
            }
        } catch (\Exception $e) {
        }
    }

    private function searchAcademicMaster(array &$results, string $keyword): void
    {
        $configs = [
            'Program' => [\App\Interfaces\GoogleSheets\ProgramRepositoryInterface::class, 'fetchAll', ['Program_ID', 'Program_Code', 'Program_Name', 'Description'], 'programs.index'],
            'Batch' => [\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class, 'fetchAll', ['Batch_ID', 'Batch_Code', 'Batch_Name', 'Program_Name'], 'batches.index'],
            'Kelas' => [\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class, 'fetchAll', ['Class_ID', 'Class_Code', 'Class_Name', 'Program_Name', 'Batch_Name', 'Room'], 'classes.index'],
            'Materi' => [\App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class, 'fetchAll', ['Subject_ID', 'Subject_Code', 'Subject_Name', 'Description'], 'subjects.index'],
        ];

        foreach ($configs as $group => [$repoClass, $method, $fields, $route]) {
            if (!Route::has($route)) {
                continue;
            }

            try {
                $items = app($repoClass)->{$method}();
                collect($items)->filter(fn ($item) => $this->matches($item, $keyword, $fields))->take(6)->each(function ($item) use (&$results, $group, $fields, $route) {
                    $this->add($results, $group, [
                        'title' => $item[$fields[2]] ?? $item[$fields[0]] ?? $group,
                        'desc' => $item[$fields[0]] ?? '',
                        'url' => route($route),
                    ]);
                });
            } catch (\Exception $e) {
            }
        }
    }

    private function searchAcademicWork(array &$results, string $keyword): void
    {
        $configs = [
            'Jadwal' => [\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class, ['Schedule_ID', 'Class_ID', 'Subject_ID', 'Teacher_ID', 'Room', 'Day', 'Day_Of_Week'], 'schedules.index'],
            'Nilai' => [\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class, ['Score_ID', 'Student_ID', 'Assessment_ID', 'Assessment_Category', 'Grade', 'Status'], 'scores.index'],
            'Tugas' => [\App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class, ['Assignment_ID', 'Title', 'Class_ID', 'Teacher_ID', 'Status'], 'assignments.index'],
        ];

        foreach ($configs as $group => [$repoClass, $fields, $route]) {
            if (!Route::has($route)) {
                continue;
            }

            try {
                $items = app($repoClass)->fetchAll();
                collect($items)->filter(fn ($item) => $this->matches($item, $keyword, $fields))->take(6)->each(function ($item) use (&$results, $group, $route) {
                    $this->add($results, $group, [
                        'title' => $item['Title'] ?? $item['Schedule_ID'] ?? $item['Score_ID'] ?? $item['Assignment_ID'] ?? $group,
                        'desc' => trim(($item['Class_ID'] ?? '') . ' ' . ($item['Student_ID'] ?? '') . ' ' . ($item['Status'] ?? '')),
                        'url' => route($route),
                    ]);
                });
            } catch (\Exception $e) {
            }
        }
    }

    private function searchTeacherScope(array &$results, string $keyword, array $context): void
    {
        $teacherId = $context['teacher_id'];
        if (!$teacherId) {
            return;
        }

        try {
            $schedules = collect(app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll())
                ->where('Teacher_ID', $teacherId);
            $classIds = $schedules->pluck('Class_ID')->filter()->unique()->values()->all();
            $students = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())
                ->whereIn('Class_ID', $classIds)
                ->values();

            $this->searchStudents($results, $keyword, route('teacher.workspace.students'), $students);

            $schedules->filter(fn ($item) => $this->matches($item, $keyword, ['Schedule_ID', 'Class_ID', 'Subject_ID', 'Room', 'Day', 'Day_Of_Week']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Jadwal Saya', [
                        'title' => $item['Subject_ID'] ?? $item['Schedule_ID'] ?? 'Jadwal',
                        'desc' => trim(($item['Class_ID'] ?? '') . ' ' . ($item['Day'] ?? $item['Day_Of_Week'] ?? '') . ' ' . ($item['Start_Time'] ?? '')),
                        'url' => route('teacher.workspace.schedule'),
                    ]);
                });

            $studentIds = $students->pluck('Student_ID')->all();
            $teacherAssessmentIds = $this->teacherAssessmentIds($teacherId);
            $scores = collect(app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class)->fetchAll())
                ->whereIn('Student_ID', $studentIds)
                ->filter(function ($score) use ($teacherId, $teacherAssessmentIds) {
                    $scoreTeacherId = trim((string) ($score['Teacher_ID'] ?? ''));
                    if ($scoreTeacherId !== '') {
                        return $scoreTeacherId === $teacherId;
                    }

                    $assessmentId = trim((string) ($score['Assessment_ID'] ?? ''));
                    return $assessmentId !== '' && in_array($assessmentId, $teacherAssessmentIds, true);
                });
            $scores->filter(fn ($item) => $this->matches($item, $keyword, ['Score_ID', 'Student_ID', 'Assessment_ID', 'Assessment_Category', 'Grade', 'Status']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Nilai Kelas Saya', [
                        'title' => $item['Score_ID'] ?? 'Nilai',
                        'desc' => trim(($item['Student_ID'] ?? '') . ' ' . ($item['Assessment_Category'] ?? '') . ' ' . ($item['Grade'] ?? '')),
                        'url' => route('teacher.workspace.scores'),
                    ]);
                });

            $assignments = collect(app(\App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class)->fetchAll())
                ->where('Teacher_ID', $teacherId)
                ->whereIn('Class_ID', $classIds);
            $assignments->filter(fn ($item) => $this->matches($item, $keyword, ['Assignment_ID', 'Title', 'Class_ID', 'Status']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Tugas Kelas Saya', [
                        'title' => $item['Title'] ?? $item['Assignment_ID'] ?? 'Tugas',
                        'desc' => trim(($item['Class_ID'] ?? '') . ' ' . ($item['Status'] ?? '')),
                        'url' => route('teacher.workspace.assignments'),
                    ]);
                });
        } catch (\Exception $e) {
        }
    }

    private function teacherAssessmentIds(string $teacherId): array
    {
        try {
            $assessmentRepo = app(\App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class);
            try {
                $assessments = $assessmentRepo->getAll();
            } catch (\Throwable $e) {
                $assessments = is_callable([$assessmentRepo, 'fetchAll']) ? $assessmentRepo->fetchAll() : collect([]);
            }

            return collect($assessments)
                ->where('Teacher_ID', $teacherId)
                ->pluck('Assessment_ID')
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function searchStudentScope(array &$results, string $keyword, array $context): void
    {
        $student = $context['student'];
        if (!$student) {
            return;
        }

        $studentId = $student['Student_ID'] ?? '';
        $classId = $student['Class_ID'] ?? '';

        if ($this->matches($student, $keyword, ['Student_ID', 'Student_Number', 'Full_Name', 'Class_ID', 'Batch_ID'])) {
            $this->add($results, 'Profil Saya', [
                'title' => $student['Full_Name'] ?? 'Profil Siswa',
                'desc' => trim(($student['Student_Number'] ?? $studentId) . ' ' . $classId),
                'url' => route('profile.index'),
            ]);
        }

        try {
            collect(app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll())
                ->where('Class_ID', $classId)
                ->filter(fn ($item) => $this->matches($item, $keyword, ['Schedule_ID', 'Subject_ID', 'Room', 'Day', 'Day_Of_Week']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Jadwal Saya', [
                        'title' => $item['Subject_ID'] ?? $item['Schedule_ID'] ?? 'Jadwal',
                        'desc' => trim(($item['Day'] ?? $item['Day_Of_Week'] ?? '') . ' ' . ($item['Start_Time'] ?? '')),
                        'url' => route('student.schedule'),
                    ]);
                });

            collect(app(\App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class)->fetchAll())
                ->where('Class_ID', $classId)
                ->filter(fn ($item) => strtoupper(trim(!empty($item['Status']) ? $item['Status'] : 'PUBLISHED')) === 'PUBLISHED')
                ->filter(fn ($item) => $this->matches($item, $keyword, ['Assignment_ID', 'Title', 'Description', 'Deadline']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Tugas Saya', [
                        'title' => $item['Title'] ?? $item['Assignment_ID'] ?? 'Tugas',
                        'desc' => $item['Deadline'] ?? '',
                        'url' => route('student.portal.assignments'),
                    ]);
                });

            collect(app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class)->fetchAll())
                ->where('Student_ID', $studentId)
                ->filter(fn ($item) => $this->matches($item, $keyword, ['Score_ID', 'Assessment_ID', 'Assessment_Category', 'Grade', 'Status']))
                ->take(6)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Nilai Saya', [
                        'title' => $item['Assessment_Category'] ?? $item['Score_ID'] ?? 'Nilai',
                        'desc' => trim(($item['Score_Value'] ?? $item['Score'] ?? '') . ' ' . ($item['Grade'] ?? '')),
                        'url' => route('student.progress'),
                    ]);
                });
        } catch (\Exception $e) {
        }
    }

    private function searchFinance(array &$results, string $keyword): void
    {
        $configs = [
            'Invoice' => [\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class, 'getAll', ['Invoice_ID', 'Student_ID', 'Company_ID', 'Category', 'Status', 'Amount'], 'invoices.index'],
            'Pembayaran' => [\App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class, 'getAll', ['Payment_ID', 'Invoice_ID', 'Student_ID', 'Status', 'Amount_Paid'], 'payments.index'],
        ];

        foreach ($configs as $group => [$repoClass, $method, $fields, $route]) {
            if (!Route::has($route)) {
                continue;
            }

            try {
                collect(app($repoClass)->{$method}())
                    ->filter(fn ($item) => $this->matches($item, $keyword, $fields))
                    ->take(6)
                    ->each(function ($item) use (&$results, $group, $fields, $route) {
                        $this->add($results, $group, [
                            'title' => $item[$fields[0]] ?? $group,
                            'desc' => trim(($item['Student_ID'] ?? $item['Company_ID'] ?? '') . ' ' . ($item['Status'] ?? '')),
                            'url' => route($route),
                        ]);
                    });
            } catch (\Exception $e) {
            }
        }
    }

    private function searchHr(array &$results, string $keyword): void
    {
        if (!Route::has('employees.index')) {
            return;
        }

        try {
            collect(app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll())
                ->filter(fn ($item) => $this->matches($item, $keyword, ['Employee_ID', 'Employee_Number', 'Full_Name', 'Name', 'Email', 'Department_ID', 'Position_ID']))
                ->take(8)
                ->each(function ($item) use (&$results) {
                    $this->add($results, 'Pegawai', [
                        'title' => $item['Full_Name'] ?? $item['Name'] ?? $item['Employee_ID'] ?? 'Pegawai',
                        'desc' => trim(($item['Employee_ID'] ?? '') . ' ' . ($item['Department_ID'] ?? '')),
                        'url' => route('employees.index'),
                    ]);
                });
        } catch (\Exception $e) {
        }
    }

    private function searchAnnouncements(array &$results, string $keyword, string $role): void
    {
        $route = match ($role) {
            'ADMINISTRATOR', 'ACADEMIC' => Route::has('announcements.index') ? 'announcements.index' : null,
            'STUDENT' => Route::has('student.portal.materials') ? 'student.portal.materials' : null,
            default => null,
        };

        if (!$route) {
            return;
        }

        try {
            collect(app(\App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface::class)->fetchAll())
                ->filter(fn ($item) => $this->matches($item, $keyword, ['Announcement_ID', 'Title', 'Content', 'Target_Audience']))
                ->take(5)
                ->each(function ($item) use (&$results, $route) {
                    $this->add($results, 'Pengumuman', [
                        'title' => $item['Title'] ?? 'Pengumuman',
                        'desc' => mb_substr((string) ($item['Content'] ?? ''), 0, 80),
                        'url' => route($route),
                    ]);
                });
        } catch (\Exception $e) {
        }
    }

    private function matches($item, string $keyword, array $fields): bool
    {
        $needle = mb_strtolower($keyword);
        foreach ($fields as $field) {
            $value = mb_strtolower((string) data_get($item, $field, ''));
            if ($value !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function add(array &$results, string $group, array $item): void
    {
        $results[$group] ??= [];
        if (count($results[$group]) >= 8) {
            return;
        }

        $results[$group][] = [
            'title' => $item['title'] ?? '-',
            'desc' => $item['desc'] ?? '',
            'url' => $item['url'] ?? route('dashboard'),
        ];
    }
}
