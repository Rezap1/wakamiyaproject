<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\Core\RoleService;
use Illuminate\Support\Facades\View;

class ShareUserRole
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (isset($user->Role_ID)) {
                $role = $this->roleService->getRoleById($user->Role_ID);
                if ($role && (!isset($role['Is_Active']) || strtoupper(trim($role['Is_Active'])) !== 'FALSE')) {
                    $roleName = strtoupper(trim($role['Role_Name'] ?? ''));
                    View::share('userRole', $roleName);
                } else {
                    View::share('userRole', 'UNKNOWN');
                }
            } else {
                View::share('userRole', 'UNKNOWN');
            }
        }

        return $next($request);
    }
}
