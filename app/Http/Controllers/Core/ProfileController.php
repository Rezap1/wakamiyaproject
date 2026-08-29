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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roleName = 'Unknown';
        $recentActivities = [];
        
        try {
            $roleService = app(RoleService::class);
            if (isset($user->Role_ID)) {
                $roleData = $roleService->getRoleById($user->Role_ID);
                $roleName = $roleData['Role_Name'] ?? 'Unknown';
            }
            
            // Fetch Employee Data for Phone Number
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employees = $employeeRepo->fetchAll();
            $employee = collect($employees)->firstWhere('User_ID', $user->User_ID);
            
            if (!$employee && !empty($user->Employee_ID)) {
                $employee = collect($employees)->firstWhere('Employee_ID', $user->Employee_ID);
            }
            
            if ($employee && !empty($employee['Phone_Number'])) {
                $user->Phone = $employee['Phone_Number'];
            } else {
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
            Log::error("Failed to fetch role/employee/student in ProfileController: " . $e->getMessage());
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
            Log::error("Failed to fetch activity logs for user {$user->User_ID}: " . $e->getMessage());
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

        if (!$user || !Hash::check($validated['current_password'], $user->getAuthPassword())) {
            return back()
                ->withErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $userService->updateUser($user->User_ID, [
            'Password' => $validated['password'],
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
