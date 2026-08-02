<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\RoleService;
use App\Services\Core\DepartmentService;
use App\Services\Core\PositionService;
use App\Services\Core\ActivityLogService;
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
            if (!$employee) {
                $employee = collect($employees)->firstWhere('Full_Name', $user->Full_Name ?? $user->Username);
            }
            if ($employee && !empty($employee['Phone_Number'])) {
                $user->Phone = $employee['Phone_Number'];
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch role/employee in ProfileController: " . $e->getMessage());
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
}
