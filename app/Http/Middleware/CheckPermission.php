<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Interfaces\GoogleSheets\PermissionRepositoryInterface;

class CheckPermission
{
    protected $moduleRepository;
    protected $permissionRepository;

    public function __construct(
        ModuleRepositoryInterface $moduleRepository,
        PermissionRepositoryInterface $permissionRepository
    ) {
        $this->moduleRepository = $moduleRepository;
        $this->permissionRepository = $permissionRepository;
    }

    public function handle(Request $request, Closure $next, $moduleCode, $action = 'view')
    {
        $user = Auth::user();
        
        if (!$user || !isset($user->Role_ID)) {
            abort(403, 'Unauthorized access.');
        }

        // Get Module ID by Code
        $module = $this->moduleRepository->fetchAll()->firstWhere('Module_Code', $moduleCode);
        
        if (!$module) {
            abort(403, "Konfigurasi Modul [{$moduleCode}] tidak ditemukan di sistem.");
        }

        $moduleId = $module['Module_ID'];
        $roleId = $user->Role_ID;

        // Check if role is Super Admin (you might want to hardcode this or fetch it)
        // For strict RBAC, even Super Admin must have entries in MASTER_PERMISSION.
        // We will stick to strict RBAC from MASTER_PERMISSION.

        $permission = $this->permissionRepository->findByRoleAndModule($roleId, $moduleId);

        if (!$permission || ($permission['Is_Active'] ?? 'TRUE') === 'FALSE') {
            abort(403, 'Anda tidak memiliki hak akses sama sekali untuk modul ini.');
        }

        // Map action to column name
        $actionMap = [
            'view' => 'Can_View',
            'create' => 'Can_Create',
            'edit' => 'Can_Edit',
            'delete' => 'Can_Delete',
            'print' => 'Can_Print',
            'export' => 'Can_Export_PDF',
        ];

        $column = $actionMap[$action] ?? 'Can_View';

        if (($permission[$column] ?? 'FALSE') !== 'TRUE') {
            abort(403, "Anda tidak memiliki izin untuk melakukan tindakan '{$action}' pada modul ini.");
        }

        return $next($request);
    }
}
