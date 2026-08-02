<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboardService;

class DashboardController extends Controller
{
    protected $adminDashboardService;

    public function __construct(AdminDashboardService $adminDashboardService)
    {
        $this->adminDashboardService = $adminDashboardService;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user) {
            $roleService = app(\App\Services\Core\RoleService::class);
            $role = $roleService->getRoleById($user->Role_ID);
            $roleName = strtolower(trim($role['Role_Name'] ?? ''));
            
            $alias = 'administrator';
            if (str_contains($roleName, 'hr')) $alias = 'hr';
            elseif (str_contains($roleName, 'academic')) $alias = 'academic';
            elseif (str_contains($roleName, 'marketing')) $alias = 'marketing';
            elseif (str_contains($roleName, 'finance')) $alias = 'finance';
            elseif (str_contains($roleName, 'director')) $alias = 'director';
            elseif (str_contains($roleName, 'teacher')) $alias = 'teacher';
            elseif (str_contains($roleName, 'student')) $alias = 'student';
            elseif (str_contains($roleName, 'admin')) $alias = 'administrator';

            if ($alias !== 'administrator' && \Illuminate\Support\Facades\Route::has('dashboard.' . $alias)) {
                return redirect()->route('dashboard.' . $alias);
            }
        }

        // Cache handled inside AdminDashboardService (key: dashboard_admin, TTL: 300s)
        $dashboardData = $this->adminDashboardService->getDashboardData();
        return view('dashboard.index', $dashboardData);
    }
}
