<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Services\Core\ModuleService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ModuleController extends Controller
{
    protected $moduleService;
    protected $activityLogService;

    public function __construct(ModuleService $moduleService, ActivityLogService $activityLogService)
    {
        $this->moduleService = $moduleService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $modules = $this->moduleService->getAllModules();
            
            // Custom collection pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $modules->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $modulesPaginated = new LengthAwarePaginator($currentItems, count($modules), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('modules.index', ['modules' => $modulesPaginated]);
        } catch (\Exception $e) {
            Log::error('Error fetching modules: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data modul dari Google Sheets.');
        }
    }

    public function create()
    {
        return view('modules.create');
    }

    public function store(StoreModuleRequest $request)
    {
        try {
            $data = $request->validated();
            $module = $this->moduleService->createModule($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_MODULE',
                "Mendaftarkan modul baru: {$module['Module_ID']}",
                $request->ip(),
                null,
                $module,
                $request->userAgent()
            );

            return redirect()->route('modules.index')->with('success', 'Modul berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating module: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (!$module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }
            return view('modules.show', compact('module'));
        } catch (\Exception $e) {
            Log::error('Error showing module: ' . $e->getMessage());
            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat memuat data modul.');
        }
    }

    public function edit($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (!$module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }
            return view('modules.edit', compact('module'));
        } catch (\Exception $e) {
            Log::error('Error editing module: ' . $e->getMessage());
            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat memuat data modul.');
        }
    }

    public function update(UpdateModuleRequest $request, $id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (!$module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            $data = $request->validated();
            $this->moduleService->updateModule($id, $data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_MODULE',
                "Memperbarui modul: {$id}",
                $request->ip(),
                $module,
                array_merge($module, $data),
                $request->userAgent()
            );

            return redirect()->route('modules.index')->with('success', 'Modul berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating module: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (!$module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            $this->moduleService->deleteModule($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_MODULE',
                "Menonaktifkan modul (Soft Delete): {$id}",
                request()->ip(),
                $module,
                array_merge($module, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('modules.index')->with('success', 'Modul berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting module: ' . $e->getMessage());
            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat menghapus data di Google Sheets.');
        }
    }
}
