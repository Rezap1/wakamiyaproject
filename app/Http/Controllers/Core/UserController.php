<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\UserService;
use App\Services\Core\RoleService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected $userService;
    protected $roleService;
    protected $activityLogService;

    public function __construct(UserService $userService, RoleService $roleService, ActivityLogService $activityLogService)
    {
        $this->userService = $userService;
        $this->roleService = $roleService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $users = $this->userService->getAllUsers();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = $this->roleService->getAllRoles();
        $nextUserId = $this->userService->getNextUserId();
        return view('users.create', compact('roles', 'nextUserId'));
    }

    public function store(StoreUserRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Cek email unique
            $existing = $this->userService->getUserByEmail($data['Email']);
            if ($existing) {
                return back()->withErrors(['Email' => 'Email already exists.'])->withInput();
            }

            // Cek username unique
            $existingUsername = $this->userService->getUserByUsername($data['Username'] ?? '');
            if ($existingUsername) {
                return back()->withErrors(['Username' => 'Username already exists.'])->withInput();
            }

            $newUser = $this->userService->createUser($data);

            $this->activityLogService->logAction(
                Auth::user()->User_ID ?? 'SYSTEM', 
                'CREATE', 
                'MASTER_USER', 
                'Created user ' . ($data['Username'] ?? $data['Email']),
                null,
                null,
                $newUser
            );

            return redirect()->route('users.index')->with('success', 'Data pengguna berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan data ke Spreadsheet: ' . $e->getMessage()])->withInput();
        }
    }

    public function edit($id)
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return redirect()->route('users.index')->withErrors(['error' => 'User not found.']);
        }
        $roles = $this->roleService->getAllRoles();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                return redirect()->route('users.index')->withErrors(['error' => 'User not found.']);
            }

            $data = $request->validated();
            
            // Cek email unique jika berubah
            if ($data['Email'] !== $user['Email']) {
                $existing = $this->userService->getUserByEmail($data['Email']);
                if ($existing && $existing['User_ID'] !== $id) {
                    return back()->withErrors(['Email' => 'Email already exists.'])->withInput();
                }
            }

            // Cek username unique jika berubah
            if (($data['Username'] ?? '') !== ($user['Username'] ?? '')) {
                $existingUsername = $this->userService->getUserByUsername($data['Username']);
                if ($existingUsername && $existingUsername['User_ID'] !== $id) {
                    return back()->withErrors(['Username' => 'Username already exists.'])->withInput();
                }
            }

            $this->userService->updateUser($id, $data);

            $this->activityLogService->logAction(
                Auth::user()->User_ID ?? 'SYSTEM', 
                'UPDATE', 
                'MASTER_USER', 
                'Updated user ' . ($data['Username'] ?? $data['Email']),
                null,
                $user,
                $data
            );

            return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate data di Spreadsheet: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                return back()->withErrors(['error' => 'Pengguna tidak ditemukan.']);
            }

            // Soft delete
            $this->userService->deleteUser($id);

            $this->activityLogService->logAction(
                Auth::user()->User_ID ?? 'SYSTEM', 
                'DELETE', 
                'MASTER_USER', 
                'Soft deleted user ' . ($user['Username'] ?? $id),
                null,
                $user,
                ['Is_Active' => 'FALSE']
            );

            return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus (Soft Delete).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus data di Spreadsheet: ' . $e->getMessage()]);
        }
    }
}
