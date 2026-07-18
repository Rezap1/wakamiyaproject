<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Services\Core\PermissionService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    protected $permissionService;
    protected $activityLogService;

    public function __construct(
        PermissionService $permissionService,
        ActivityLogService $activityLogService
    ) {
        $this->permissionService = $permissionService;
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        try {
            $permissions = $this->permissionService->getAllPermissions();
            $roles = $this->permissionService->getAllRoles();
            $modules = $this->permissionService->getAllModules();

            // Mapping for display
            $rolesMap = $roles->keyBy('Role_ID');
            $modulesMap = $modules->keyBy('Module_ID');

            // Attach role and module info to permissions for view
            $permissions = $permissions->map(function ($item) use ($rolesMap, $modulesMap) {
                $role = $rolesMap->get($item['Role_ID'] ?? '');
                $module = $modulesMap->get($item['Module_ID'] ?? '');
                
                $item['Role_Name'] = $role ? ($role['Role_Name'] ?? $item['Role_ID']) : $item['Role_ID'];
                $item['Module_Name'] = $module ? ($module['Module_Name'] ?? $item['Module_ID']) : $item['Module_ID'];
                
                return $item;
            });

            // Pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $permissions->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $permissionsPaginated = new LengthAwarePaginator($currentItems, count($permissions), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);

            return view('permissions.index', [
                'permissions' => $permissionsPaginated,
                'roles' => $roles,
                'modules' => $modules
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching permissions: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data hak akses dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $roles = $this->permissionService->getAllRoles();
            $modules = $this->permissionService->getAllModules();
            return view('permissions.create', compact('roles', 'modules'));
        } catch (\Exception $e) {
            Log::error('Error loading permission create form: ' . $e->getMessage());
            return redirect()->route('permissions.index')->with('error', 'Gagal memuat form tambah hak akses.');
        }
    }

    public function store(StorePermissionRequest $request)
    {
        try {
            $data = $request->validated();
            $permissions = $this->permissionService->createPermission($data);
            
            foreach ($permissions as $permission) {
                $this->activityLogService->logAction(
                    Auth::id() ?? 'SYSTEM',
                    'CREATE',
                    'MASTER_PERMISSION',
                    "Menambahkan hak akses baru: {$permission['Permission_ID']} (Role: {$permission['Role_ID']}, Modul: {$permission['Module_ID']})",
                    $request->ip(),
                    null,
                    $permission,
                    $request->userAgent()
                );
            }

            return redirect()->route('permissions.index')->with('success', count($permissions) . ' Konfigurasi Hak Akses berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating permission: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $permission = $this->permissionService->getPermissionById($id);
            if (!$permission) {
                return redirect()->route('permissions.index')->with('error', 'Data hak akses tidak ditemukan.');
            }
            
            $roles = $this->permissionService->getAllRoles();
            $modules = $this->permissionService->getAllModules();
            
            $role = $roles->firstWhere('Role_ID', $permission['Role_ID']);
            $module = $modules->firstWhere('Module_ID', $permission['Module_ID']);
            
            $permission['Role_Name'] = $role ? ($role['Role_Name'] ?? $permission['Role_ID']) : $permission['Role_ID'];
            $permission['Module_Name'] = $module ? ($module['Module_Name'] ?? $permission['Module_ID']) : $permission['Module_ID'];

            return view('permissions.show', compact('permission'));
        } catch (\Exception $e) {
            Log::error('Error showing permission: ' . $e->getMessage());
            return redirect()->route('permissions.index')->with('error', 'Terjadi kesalahan saat memuat profil hak akses.');
        }
    }

    public function edit($id)
    {
        try {
            $permission = $this->permissionService->getPermissionById($id);
            if (!$permission) {
                return redirect()->route('permissions.index')->with('error', 'Data hak akses tidak ditemukan.');
            }

            $roles = $this->permissionService->getAllRoles();
            $modules = $this->permissionService->getAllModules();

            return view('permissions.edit', compact('permission', 'roles', 'modules'));
        } catch (\Exception $e) {
            Log::error('Error editing permission: ' . $e->getMessage());
            return redirect()->route('permissions.index')->with('error', 'Terjadi kesalahan saat memuat form edit hak akses.');
        }
    }

    public function update(UpdatePermissionRequest $request, $id)
    {
        try {
            $permission = $this->permissionService->getPermissionById($id);
            if (!$permission) {
                return redirect()->route('permissions.index')->with('error', 'Data hak akses tidak ditemukan.');
            }

            $data = $request->validated();
            $this->permissionService->updatePermission($id, $data);
            
            $updatedPermission = $this->permissionService->getPermissionById($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_PERMISSION',
                "Memperbarui konfigurasi hak akses: {$id}",
                $request->ip(),
                $permission,
                $updatedPermission,
                $request->userAgent()
            );

            return redirect()->route('permissions.index')->with('success', 'Konfigurasi hak akses berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating permission: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $permission = $this->permissionService->getPermissionById($id);
            if (!$permission) {
                return redirect()->route('permissions.index')->with('error', 'Data hak akses tidak ditemukan.');
            }

            $this->permissionService->deletePermission($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_PERMISSION',
                "Menonaktifkan hak akses (Soft Delete): {$id}",
                request()->ip(),
                $permission,
                array_merge($permission, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('permissions.index')->with('success', 'Data hak akses berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting permission: ' . $e->getMessage());
            return redirect()->route('permissions.index')->with('error', 'Terjadi kesalahan saat menghapus data hak akses.');
        }
    }
}
