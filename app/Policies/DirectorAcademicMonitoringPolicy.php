<?php

namespace App\Policies;

use App\Services\Core\RoleService;
use Illuminate\Contracts\Auth\Authenticatable;

final class DirectorAcademicMonitoringPolicy
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * This boundary is intentionally narrower than the existing executive
     * dashboard. It never grants MASTER, ADMINISTRATOR, ACADEMIC or TEACHER.
     */
    public function view(Authenticatable $user): bool
    {
        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return false;
        }

        $role = $this->roles->getRoleById($roleId);

        return is_array($role)
            && strtoupper(trim((string) ($role['Role_Name'] ?? ''))) === 'DIRECTOR'
            && strtoupper(trim((string) ($role['Is_Active'] ?? 'TRUE'))) === 'TRUE';
    }
}
