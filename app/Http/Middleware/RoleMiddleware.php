<?php

namespace App\Http\Middleware;

use App\Services\Core\RoleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

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

        if (! $user || ! isset($user->Role_ID)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($this->isStudentAttendanceApi($request)) {
                return response()->json([
                    'success' => false,
                    'status' => 'rejected',
                    'reason_code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Presensi ditolak: sesi Anda sudah berakhir. Silakan login kembali.',
                    'data' => null,
                ], 401);
            }

            return redirect()->route('login')->withErrors(['login' => 'Sesi tidak valid atau Role tidak ditemukan.']);
        }

        $role = $this->roleService->getRoleById($user->Role_ID);

        // Security Validation: If Role is unknown or status is inactive, reject access
        if (! $role || (isset($role['Is_Active']) && strtoupper(trim($role['Is_Active'])) === 'FALSE')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($this->isStudentAttendanceApi($request)) {
                return response()->json([
                    'success' => false,
                    'status' => 'rejected',
                    'reason_code' => 'ACCESS_DENIED',
                    'message' => 'Presensi ditolak: role akun Anda tidak aktif atau tidak valid.',
                    'data' => null,
                ], 403);
            }

            return redirect()->route('login')->withErrors(['login' => 'Akun Anda menggunakan Role yang tidak valid atau sedang dinonaktifkan.']);
        }

        $roleName = strtoupper(trim($role['Role_Name'] ?? ''));

        if ($roleName === 'MASTER') {
            if ($request->routeIs('dashboard.teacher', 'dashboard.student', 'dashboard.director')) {
                abort(403, 'Master Account tidak memiliki hak akses untuk dashboard spesifik ini.');
            }

            $masterAllowed = ['MASTER', 'ADMINISTRATOR', 'HR', 'ACADEMIC', 'FINANCE', 'MARKETING'];
            if (! empty(array_intersect($masterAllowed, array_map('strtoupper', $roles)))) {
                return $next($request);
            }

            $masterDenied = ['TEACHER', 'STUDENT', 'DIRECTOR'];
            if (! empty(array_intersect($masterDenied, array_map('strtoupper', $roles)))) {
                abort(403, 'Master Account tidak memiliki hak akses untuk halaman ini (Khusus Teacher/Student/Director ditolak).');
            }

            abort(403, 'Master Account tidak memiliki hak akses untuk halaman ini.');
        }

        if (! in_array($roleName, $roles)) {
            if ($this->isStudentAttendanceApi($request)) {
                return response()->json([
                    'success' => false,
                    'status' => 'rejected',
                    'reason_code' => 'ACCESS_DENIED',
                    'message' => 'Presensi ditolak: akun Anda tidak memiliki akses untuk presensi siswa.',
                    'data' => null,
                ], 403);
            }
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }

    private function isStudentAttendanceApi(Request $request): bool
    {
        return $request->is('attendance/student/scan') && $request->expectsJson();
    }
}
