<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Core\RoleService;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();

        if (!$user || !isset($user->Role_ID)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['login' => 'Sesi tidak valid atau Role tidak ditemukan.']);
        }

        $role = $this->roleService->getRoleById($user->Role_ID);

        // Security Validation: If Role is unknown or status is inactive, reject access
        if (!$role || (isset($role['Is_Active']) && strtoupper(trim($role['Is_Active'])) === 'FALSE')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['login' => 'Akun Anda menggunakan Role yang tidak valid atau sedang dinonaktifkan.']);
        }

        $roleName = strtoupper(trim($role['Role_Name'] ?? ''));

        if ($roleName === 'MASTER') {
            if ($request->routeIs('dashboard.teacher', 'dashboard.student', 'dashboard.director')) {
                abort(403, 'Master Account tidak memiliki hak akses untuk dashboard spesifik ini.');
            }

            $masterAllowed = ['MASTER', 'ADMINISTRATOR', 'HR', 'ACADEMIC', 'FINANCE', 'MARKETING'];
            if (!empty(array_intersect($masterAllowed, array_map('strtoupper', $roles)))) {
                return $next($request);
            }

            $masterDenied = ['TEACHER', 'STUDENT', 'DIRECTOR'];
            if (!empty(array_intersect($masterDenied, array_map('strtoupper', $roles)))) {
                abort(403, 'Master Account tidak memiliki hak akses untuk halaman ini (Khusus Teacher/Student/Director ditolak).');
            }

            abort(403, 'Master Account tidak memiliki hak akses untuk halaman ini.');
        }

        if (!in_array($roleName, $roles)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
