<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\RoleService;
use App\Services\Core\DepartmentService;
use App\Services\Core\PositionService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\UserService;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->Phone = trim((string) ($user->Phone ?? $user->Phone_Number ?? ''));
        $roleName = 'Unknown';
        $recentActivities = [];
        
        try {
            $roleService = app(RoleService::class);
            if (isset($user->Role_ID)) {
                $roleData = $roleService->getRoleById($user->Role_ID);
                $roleName = $roleData['Role_Name'] ?? 'Unknown';
            }
            
            // MASTER_USER owns contact data. Refresh the principal from the
            // sheet first, then use employee/student rows only as legacy
            // fallbacks for accounts whose contact cell is still empty.
            try {
                $authoritativeUser = app(UserService::class)->getUserById((string) ($user->User_ID ?? ''));
                if (is_array($authoritativeUser)) {
                    foreach (['Full_Name', 'Email', 'Phone_Number', 'Employee_ID', 'Student_ID'] as $field) {
                        if (array_key_exists($field, $authoritativeUser) && trim((string) ($authoritativeUser[$field] ?? '')) !== '') {
                            $user->{$field} = $authoritativeUser[$field];
                        }
                    }
                    $user->Phone = $authoritativeUser['Phone_Number'] ?? '';
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to refresh authoritative profile user', ['exception' => get_class($e)]);
            }

            // Fetch legacy Employee/Student fallbacks for phone display.
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employees = $employeeRepo->fetchAll();
            $employee = collect($employees)->firstWhere('User_ID', $user->User_ID);
            
            if (!$employee && !empty($user->Employee_ID)) {
                $employee = collect($employees)->firstWhere('Employee_ID', $user->Employee_ID);
            }
            
            if (empty($user->Phone) && $employee && !empty($employee['Phone_Number'])) {
                $user->Phone = $employee['Phone_Number'];
            } elseif (empty($user->Phone)) {
                // If not found in Employee, try fetching from Student Data
                $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                $students = $studentRepo->fetchAll();
                $student = collect($students)->firstWhere('User_ID', $user->User_ID);
                if (!$student && !empty($user->Student_ID)) {
                    $student = collect($students)->firstWhere('Student_ID', $user->Student_ID);
                }
                if ($student && !empty($student['Phone_Number'])) {
                    $user->Phone = $student['Phone_Number'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Profile contact lookup failed', ['exception' => get_class($e)]);
        }
        
        try {
            if (isset($user->User_ID)) {
                $activityLogService = app(ActivityLogService::class);
                $allActivities = $activityLogService->getAllLogs();
                $recentActivities = collect($allActivities)
                    ->filter(function($log) use ($user) {
                        return isset($log['User_ID']) && $log['User_ID'] == $user->User_ID;
                    })
                    ->sortByDesc(function($log) {
                        return strtotime($log['Created_At'] ?? '1970-01-01');
                    })
                    ->take(10)
                    ->values()
                    ->toArray();
            }
        } catch (\Exception $e) {
            Log::error('Profile activity lookup failed', ['exception' => get_class($e)]);
        }

        return view('profile.index', compact('user', 'roleName', 'recentActivities'));
    }

    public function updatePassword(Request $request, UserService $userService)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = auth()->user();

        if (!$user || !$userService->changePassword(
            (string) ($user->User_ID ?? ''),
            $validated['current_password'],
            $validated['password']
        )) {
            return back()
                ->withErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        // Keep the in-memory authenticated principal consistent for subsequent
        // password changes during the same session; the provider still reads
        // the authoritative hash from MASTER_USER on the next login.
        $freshUser = $userService->getUserById((string) $user->User_ID);
        $freshHash = is_array($freshUser) ? ($freshUser['Password'] ?? null) : null;
        if ($freshHash) {
            $user->setAttribute('password', $freshHash);
            $user->setAttribute('Password', $freshHash);
        }

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
