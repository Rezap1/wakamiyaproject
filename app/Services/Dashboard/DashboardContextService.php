<?php
namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\PositionRepositoryInterface;

class DashboardContextService
{
    public function getContext()
    {
        $now = Carbon::now('Asia/Jakarta');
        $hour = $now->hour;

        if ($hour >= 5 && $hour < 11) {
            $greeting = 'Selamat pagi';
            $greeting_icon = '👋';
        } elseif ($hour >= 11 && $hour < 15) {
            $greeting = 'Selamat siang';
            $greeting_icon = '☀️';
        } elseif ($hour >= 15 && $hour < 18) {
            $greeting = 'Selamat sore';
            $greeting_icon = '🌤️';
        } else {
            $greeting = 'Selamat malam';
            $greeting_icon = '🌙';
        }

        // Format dates dynamically using Carbon with Indonesian locale
        Carbon::setLocale('id');
        $dateFormatted = $now->translatedFormat('l, d F Y');

        $user = Auth::user();
        if (!$user) {
            return [
                'time'          => $now->format('H:i:s'),
                'date'          => $dateFormatted,
                'greeting'      => $greeting,
                'greeting_icon' => $greeting_icon,
                'timezone'      => 'Asia/Jakarta',
                'user_name'     => 'Guest',
                'username'      => 'anonymous',
                'role'          => 'GUEST',
                'user_id'       => null,
                'timestamp'     => $now->timestamp,
            ];
        }

        $dynamicContext = [
            'time'          => $now->format('H:i:s'),
            'date'          => $dateFormatted,
            'greeting'      => $greeting,
            'greeting_icon' => $greeting_icon,
            'timezone'      => 'Asia/Jakarta',
            'timestamp'     => $now->timestamp,
        ];

        $userId = $user->User_ID ?? 'unknown';
        $roleId = $user->Role_ID ?? 'none';
        $updatedAt = $user->Updated_At ?? $user->updated_at ?? '';
        $cacheKey = "dashboard_context_user_{$userId}_{$roleId}_" . md5((string) $updatedAt);
        $ttl = (int) config('cache.wms.reference', 300);

        $staticContext = Cache::remember($cacheKey, $ttl, function () use ($user) {
            $roleName = 'USER';
            if (isset($user->Role_ID)) {
                $roleService = app(\App\Services\Core\RoleService::class);
                $roleData = $roleService->getRoleById($user->Role_ID);
                $roleName = $roleData['Role_Name'] ?? $user->Role ?? 'USER';
            }

            $context = [
                'user_name'     => $user->Full_Name ?? 'Unknown User',
                'username'      => $user->Username ?? 'unknown',
                'role'          => strtoupper($roleName),
                'user_id'       => $user->User_ID ?? null,
            ];

            // Enrich context based on Role
            if (strcasecmp($context['role'], 'STUDENT') === 0) {
                $studentRepo = app(StudentRepositoryInterface::class);
                $students = $studentRepo->fetchAll();
                $student = collect($students)->firstWhere('User_ID', $context['user_id']);
                if ($student) {
                    $context['student_id'] = $student['Student_ID'] ?? null;
                    $context['enrollment_status'] = $student['Enrollment_Status'] ?? 'Aktif';

                    $batchRepo = app(BatchRepositoryInterface::class);
                    $batch = collect($batchRepo->fetchAll())->firstWhere('Batch_ID', $student['Batch_ID'] ?? '');
                    $context['batch'] = $batch['Batch_Name'] ?? $student['Batch_ID'] ?? null;

                    $programRepo = app(ProgramRepositoryInterface::class);
                    $program = collect($programRepo->fetchAll())->firstWhere('Program_ID', $student['Program_ID'] ?? '');
                    $context['program'] = $program['Program_Name'] ?? $student['Program_ID'] ?? null;

                    $classRepo = app(ClassRepositoryInterface::class);
                    $class = collect($classRepo->fetchAll())->firstWhere('Class_ID', $student['Class_ID'] ?? '');
                    $context['class'] = $class['Class_Name'] ?? $student['Class_ID'] ?? null;
                }
            } elseif (strcasecmp($context['role'], 'TEACHER') === 0) {
                $teacherRepo = app(TeacherRepositoryInterface::class);
                $teachers = $teacherRepo->fetchAll();
                $teacher = collect($teachers)->firstWhere('User_ID', $context['user_id']);
                if ($teacher) {
                    $context['teacher_id'] = $teacher['Teacher_ID'] ?? null;
                    $context['teacher_name'] = $teacher['Full_Name'] ?? $context['user_name'];
                }
            } elseif (in_array(strtoupper($context['role']), ['HR', 'FINANCE', 'MARKETING', 'ACADEMIC', 'DIRECTOR', 'ADMINISTRATOR'])) {
                $employeeRepo = app(EmployeeRepositoryInterface::class);
                $employees = $employeeRepo->fetchAll();
                $employee = collect($employees)->firstWhere('User_ID', $context['user_id']);
                if ($employee) {
                    $context['employee_id'] = $employee['Employee_ID'] ?? null;

                    $deptRepo = app(DepartmentRepositoryInterface::class);
                    $dept = collect($deptRepo->fetchAll())->firstWhere('Department_ID', $employee['Department_ID'] ?? '');
                    $context['department'] = $dept['Department_Name'] ?? $employee['Department_ID'] ?? null;

                    $posRepo = app(PositionRepositoryInterface::class);
                    $pos = collect($posRepo->fetchAll())->firstWhere('Position_ID', $employee['Position_ID'] ?? '');
                    $context['position'] = $pos['Position_Name'] ?? $employee['Position_ID'] ?? null;
                }
            }

            return $context;
        });

        return array_merge($staticContext, $dynamicContext);
    }
}
