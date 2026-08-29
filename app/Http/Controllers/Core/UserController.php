<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\UserService;
use App\Services\Core\RoleService;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    protected $userService;
    protected $roleService;

    public function __construct(UserService $userService, RoleService $roleService)
    {
        $this->userService = $userService;
        $this->roleService = $roleService;
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

        $users = \App\Helpers\CollectionHelper::paginate($users, 10)->withQueryString();

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
            $existingEmail = $this->userService->getUserByEmail($data['Email']);
            if ($existingEmail) {
                return back()->withErrors(['Email' => 'Email already exists.'])->withInput();
            }

            // Cek username unique
            $existingUsername = $this->userService->getUserByUsername($data['Username'] ?? '');
            if ($existingUsername) {
                return back()->withErrors(['Username' => 'Username already exists.'])->withInput();
            }

            $newUser = $this->userService->createUser($data);

            return redirect()->route('users.index')->with('success', 'Data pengguna berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan data ke Spreadsheet: ' . $this->safeExceptionMessage($e)])->withInput();
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

            return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate data di Spreadsheet: ' . $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                return back()->withErrors(['error' => 'Pengguna tidak ditemukan.']);
            }

            // Cascade hard delete all owned user data before removing the account.
            $this->userService->deleteUser($id);

            return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus (Hard Delete).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus data di Spreadsheet: ' . $this->safeExceptionMessage($e)]);
        }
    }

    use \App\Traits\Exportable;

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
