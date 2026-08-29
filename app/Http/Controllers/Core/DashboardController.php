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
        if (!$user || empty($user->Role_ID)) {
            abort(403, 'Sesi tidak valid atau Role tidak ditemukan.');
        }

        $roleService = app(\App\Services\Core\RoleService::class);
        $role = $roleService->getRoleById($user->Role_ID);
        if (!$role || (isset($role['Is_Active']) && strtoupper(trim($role['Is_Active'])) === 'FALSE')) {
            abort(403, 'Role tidak valid atau sedang dinonaktifkan.');
        }

        $roleName = strtolower(trim($role['Role_Name'] ?? ''));

        $alias = null;
        if (str_contains($roleName, 'hr')) $alias = 'hr';
        elseif (str_contains($roleName, 'academic')) $alias = 'academic';
        elseif (str_contains($roleName, 'marketing')) $alias = 'marketing';
        elseif (str_contains($roleName, 'finance')) $alias = 'finance';
        elseif (str_contains($roleName, 'director')) $alias = 'director';
        elseif (str_contains($roleName, 'teacher')) $alias = 'teacher';
        elseif (str_contains($roleName, 'student')) $alias = 'student';
        elseif (str_contains($roleName, 'admin')) $alias = 'administrator';
        elseif (str_contains($roleName, 'master')) $alias = 'administrator';

        if (!$alias) {
            abort(403, 'Role akun tidak dikenali oleh sistem.');
        }

        if ($alias !== 'administrator' && \Illuminate\Support\Facades\Route::has('dashboard.' . $alias)) {
            return redirect()->route('dashboard.' . $alias);
        }

        // Cache handled inside AdminDashboardService (key: dashboard_admin, TTL: 300s)
        try {
            $dashboardData = $this->adminDashboardService->getDashboardData();
        } catch (\Exception $e) {
            $dashboardData = ['api_error' => true, 'error_message' => $this->safeExceptionMessage($e)];
        }
        
        return view('dashboard.index', $dashboardData);
    }
}
