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
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function index(Request $request)
    {
        $users = $this->userService->getAllUsers();
        $roles = $this->roleService->getAllRoles();

        $search = $request->input('search');
        if (!empty($search)) {
            $users = \App\Helpers\CollectionHelper::search($users, $search, ['User_ID', 'Username', 'Full_Name', 'Email']);
        }

        if ($request->filled('role')) {
            $users = $users->where('Role_ID', $request->input('role'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $users = $users->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }

        $users = \App\Helpers\CollectionHelper::paginate($users, 15)->withQueryString();

        return view('users.index', compact('users', 'roles'));
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

            return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus (Hard Delete).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus data di Spreadsheet: ' . $e->getMessage()]);
        }
    }

    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {
        $users = $this->userService->getAllUsers();
        $roles = $this->roleService->getAllRoles()->keyBy('Role_ID');
        
        $search = $request->input('search');
        if (!empty($search)) {
            $users = \App\Helpers\CollectionHelper::search($users, $search, ['User_ID', 'Username', 'Full_Name', 'Email']);
        }

        if ($request->filled('role')) {
            $users = $users->where('Role_ID', $request->input('role'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $users = $users->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }

        $activeCount = $users->where('Is_Active', 'TRUE')->count();
        $inactiveCount = $users->where('Is_Active', 'FALSE')->count();
        $summary = "<tr><td>Total Users</td><td>: {$users->count()}</td><td width='20px'></td><td>Active Users</td><td>: {$activeCount}</td><td width='20px'></td><td>Inactive Users</td><td>: {$inactiveCount}</td></tr>";

        $headers = ['User ID', 'Username', 'Email', 'Role', 'Status'];
        $mapRow = function($user) use ($roles) {
            $roleName = isset($roles[$user['Role_ID']]) ? $roles[$user['Role_ID']]['Role_Name'] : 'Unknown';
            $isActive = ($user['Is_Active'] ?? 'FALSE') === 'TRUE' ? 'Active' : 'Inactive';
            return [
                $user['User_ID'] ?? '-',
                $user['Username'] ?? '-',
                $user['Email'] ?? '-',
                $roleName,
                $isActive
            ];
        };

        return [
            'moduleName' => 'USERS',
            'data' => $users,
            'pdfView' => 'users.pdf',
            'headers' => $headers,
            'mapRow' => $mapRow,
            'isLandscape' => false,
            'metadata' => ['roles' => $roles],
            'summary' => $summary
        ];
    }
}
