<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Core\EnterpriseEventService;
use App\Support\LoginRateLimiter;

class AuthController extends Controller
{
    protected $enterpriseEvent;

    public function __construct(EnterpriseEventService $enterpriseEvent)
    {
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            LoginRateLimiter::clearIdentifier($request);
            
            // Log successful login
            $this->enterpriseEvent->dispatch(
                'UNAUTHENTICATED:' . strtolower($credentials['login']),
                'LOGIN',
                'AUTH',
                'LOGIN',
                Auth::id(),
                [],
                [],
                ['description' => 'Pengguna berhasil login via ' . $credentials['login']]
            );

            // Get role to redirect
            $user = Auth::user();
            $roleService = app(\App\Services\Core\RoleService::class);
            $role = $roleService->getRoleById($user->Role_ID);
            
            if (!$role || (isset($role['Is_Active']) && strtoupper(trim($role['Is_Active'])) === 'FALSE')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors(['login' => 'Akun Anda menggunakan Role yang tidak valid atau sedang dihapus.']);
            }
            
            $roleName = strtolower(trim($role['Role_Name'] ?? ''));
            
            // Map roles like "HR Staff" to "hr"
            $alias = 'administrator';
            if (str_contains($roleName, 'hr')) $alias = 'hr';
            elseif (str_contains($roleName, 'academic')) $alias = 'academic';
            elseif (str_contains($roleName, 'marketing')) $alias = 'marketing';
            elseif (str_contains($roleName, 'finance')) $alias = 'finance';
            elseif (str_contains($roleName, 'director')) $alias = 'director';
            elseif (str_contains($roleName, 'teacher')) $alias = 'teacher';
            elseif (str_contains($roleName, 'student')) $alias = 'student';
            elseif (str_contains($roleName, 'admin')) $alias = 'administrator';
            
            $dashboardRoute = 'dashboard.' . $alias;

            if (\Illuminate\Support\Facades\Route::has($dashboardRoute)) {
                return redirect()->intended(route($dashboardRoute));
            }

            return redirect()->intended(route('dashboard'));
        }

        // FAILED LOGIN (can add event here but wait to see if requested, only LOGIN LOGOUT specified, actually requested FAILED LOGIN too)
        $request->attributes->set('login_failed', true);
        try {
            $this->enterpriseEvent->dispatch(
                'SYSTEM',
                'FAILED_LOGIN',
                'AUTH',
                'FAILED_LOGIN',
                'UNAUTHENTICATED:' . strtolower($credentials['login']),
                [],
                [],
                ['login' => $credentials['login']]
            );
        } catch (\Exception $e) {}

        return back()->withErrors([
            'login' => __('auth.failed'),
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        // Log logout
        if (Auth::check()) {
            $this->enterpriseEvent->dispatch(
                'SYSTEM',
                'LOGOUT',
                'AUTH',
                'LOGOUT',
                Auth::id(),
                [],
                [],
                ['description' => 'Pengguna berhasil logout']
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
