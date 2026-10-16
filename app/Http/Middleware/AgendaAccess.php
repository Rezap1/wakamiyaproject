<?php

namespace App\Http\Middleware;

use App\Services\Core\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AgendaAccess
{
    public function __construct(private RoleService $roles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->Role_ID ? $this->roles->getRoleById($user->Role_ID) : null;
        $roleName = strtoupper(trim((string) ($role['Role_Name'] ?? '')));
        $inactive = isset($role['Is_Active'])
            && in_array(strtoupper(trim((string) $role['Is_Active'])), ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);

        if ($role === null || $roleName === '' || $inactive || $roleName === 'STUDENT') {
            abort(403, 'Anda tidak memiliki hak akses ke Agenda Management.');
        }

        return $next($request);
    }
}
