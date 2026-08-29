<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class UserResolverHelper
{
    /**
     * Resolve any User ID, Employee ID, Student ID, Teacher ID, or Email to Full Name.
     */
    public static function getName(?string $identifier): string
    {
        if (empty($identifier)) {
            return '-';
        }

        $trimmed = trim($identifier);

        // 1. Check Employees
        $employees = Cache::remember('all_employees_lookup_map', 300, function () {
            try {
                $repo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
                return collect($repo->fetchAll());
            } catch (\Exception $e) {
                return collect();
            }
        });

        $emp = $employees->first(function ($e) use ($trimmed) {
            return ($e['Employee_ID'] ?? '') === $trimmed ||
                   ($e['User_ID'] ?? '') === $trimmed ||
                   strcasecmp($e['Email'] ?? '', $trimmed) === 0;
        });

        if ($emp && !empty($emp['Full_Name'])) {
            return $emp['Full_Name'];
        }

        // 2. Check Students
        $students = Cache::remember('all_students_lookup_map', 300, function () {
            try {
                $repo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                return collect($repo->fetchAll());
            } catch (\Exception $e) {
                return collect();
            }
        });

        $std = $students->first(function ($s) use ($trimmed) {
            return ($s['Student_ID'] ?? '') === $trimmed ||
                   ($s['User_ID'] ?? '') === $trimmed ||
                   strcasecmp($s['Email'] ?? '', $trimmed) === 0;
        });

        if ($std && !empty($std['Full_Name'])) {
            return $std['Full_Name'];
        }

        // 3. Check Teachers
        $teachers = Cache::remember('all_teachers_lookup_map', 300, function () {
            try {
                $repo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
                return collect($repo->fetchAll());
            } catch (\Exception $e) {
                return collect();
            }
        });

        $tch = $teachers->first(function ($t) use ($trimmed) {
            return ($t['Teacher_ID'] ?? '') === $trimmed ||
                   ($t['User_ID'] ?? '') === $trimmed ||
                   ($t['Employee_ID'] ?? '') === $trimmed ||
                   strcasecmp($t['Email'] ?? '', $trimmed) === 0;
        });

        if ($tch && !empty($tch['Full_Name'])) {
            return $tch['Full_Name'];
        }

        // 4. Check Users
        $users = Cache::remember('all_users_lookup_map', 300, function () {
            try {
                $repo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
                return collect($repo->fetchAll());
            } catch (\Exception $e) {
                return collect();
            }
        });

        $usr = $users->first(function ($u) use ($trimmed) {
            return ($u['User_ID'] ?? '') === $trimmed ||
                   strcasecmp($u['Email'] ?? '', $trimmed) === 0 ||
                   strcasecmp($u['Username'] ?? '', $trimmed) === 0;
        });

        if ($usr) {
            if (!empty($usr['Full_Name'])) return $usr['Full_Name'];
            if (!empty($usr['Username'])) return ucwords(str_replace(['.', '_', '-'], ' ', $usr['Username']));
        }

        // 5. If Email format, format prettily
        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $trimmed);
            return ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
        }

        return $trimmed;
    }

    /**
     * Resolve Role ID or Role string to human readable Role Name.
     */
    public static function getRoleName(?string $roleId): string
    {
        if (empty($roleId)) {
            return '-';
        }

        $trimmed = trim($roleId);

        try {
            $rolesMap = Cache::remember('all_roles_lookup_map', 300, function () {
                $repo = app(\App\Interfaces\GoogleSheets\RoleRepositoryInterface::class);
                return collect($repo->fetchAll())->keyBy('Role_ID');
            });

            if (isset($rolesMap[$trimmed])) {
                $role = $rolesMap[$trimmed];
                if (strtoupper(trim((string) ($role['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                    return '';
                }

                return trim((string) ($role['Role_Name'] ?? ''));
            }
        } catch (\Exception $e) {}

        return '';
    }

    /**
     * Resolve Student ID/User ID to array containing student details:
     * ['name' => ..., 'class_name' => ..., 'batch_name' => ..., 'formatted' => ...]
     */
    public static function getStudentDetail(?string $identifier): array
    {
        $default = [
            'name' => '-',
            'class_name' => '-',
            'batch_name' => '-',
            'formatted' => '-'
        ];

        if (empty($identifier)) {
            return $default;
        }

        $trimmed = trim($identifier);

        $students = Cache::remember('all_students_lookup_map', 300, function () {
            try {
                $repo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                return collect($repo->fetchAll());
            } catch (\Exception $e) {
                return collect();
            }
        });

        $std = $students->first(function ($s) use ($trimmed) {
            return ($s['Student_ID'] ?? '') === $trimmed ||
                   ($s['User_ID'] ?? '') === $trimmed ||
                   strcasecmp($s['Email'] ?? '', $trimmed) === 0;
        });

        if (!$std) {
            $name = self::getName($identifier);
            return [
                'name' => $name,
                'class_name' => '-',
                'batch_name' => '-',
                'formatted' => $name
            ];
        }

        $studentName = $std['Full_Name'] ?? '-';
        $classId = $std['Class_ID'] ?? '';
        $batchId = $std['Batch_ID'] ?? '';

        $className = '-';
        if (!empty($classId)) {
            $classes = Cache::remember('all_classes_lookup_map', 300, function () {
                try {
                    $repo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
                    return collect($repo->fetchAll());
                } catch (\Exception $e) {
                    return collect();
                }
            });
            $cls = $classes->firstWhere('Class_ID', $classId);
            if ($cls) {
                $className = $cls['Class_Name'] ?? $cls['Class_Code'] ?? '-';
            }
        }

        $batchName = '-';
        if (!empty($batchId)) {
            $batches = Cache::remember('all_batches_lookup_map', 300, function () {
                try {
                    $repo = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class);
                    return collect($repo->fetchAll());
                } catch (\Exception $e) {
                    return collect();
                }
            });
            $btc = $batches->firstWhere('Batch_ID', $batchId);
            if ($btc) {
                $batchName = $btc['Batch_Name'] ?? $btc['Batch_Code'] ?? '-';
            }
        }

        $formattedInfo = $studentName;
        $details = [];
        if ($className !== '-') $details[] = "Kelas: {$className}";
        if ($batchName !== '-') $details[] = "Batch: {$batchName}";

        if (!empty($details)) {
            $formattedInfo .= ' (' . implode(' | ', $details) . ')';
        }

        return [
            'name' => $studentName,
            'class_name' => $className,
            'batch_name' => $batchName,
            'formatted' => $formattedInfo
        ];
    }

    /**
     * Clear resolution lookup caches.
     */
    public static function clearCache(): void
    {
        Cache::forget('all_employees_lookup_map');
        Cache::forget('all_students_lookup_map');
        Cache::forget('all_teachers_lookup_map');
        Cache::forget('all_users_lookup_map');
        Cache::forget('all_roles_lookup_map');
        Cache::forget('all_classes_lookup_map');
        Cache::forget('all_batches_lookup_map');
    }
}
