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
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $modules = $this->moduleService->getAllModules();
            
        $search = $request->input('search');
        if (!empty($search)) {
            $modules = \App\Helpers\CollectionHelper::search($modules, $search, ['Module_Code', 'Module_Name', 'Module_Group']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $modules = $modules->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'Modul Sistem (Module)',
            'data' => collect(array_values($modules->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Modul', 'Grup Modul', 'Nama Modul', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['Module_Code'] ?? '-',
                    $row['Module_Group'] ?? '-',
                    $row['Module_Name'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$modules->count().'</td></tr>'
        ];
    }

    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $modules = $this->moduleService->getAllModules();
            
            $search = $request->input('search');
            if (!empty($search)) {
                $modules = \App\Helpers\CollectionHelper::search($modules, $search, ['Module_ID', 'Module_Code', 'Module_Name', 'Module_Group']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $modules = $modules->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Pagination
            $modulesPaginated = \App\Helpers\CollectionHelper::paginate($modules, 10)->withQueryString();
            
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
            
            return redirect()->route('modules.index')->with('success', 'Modul berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting module: ' . $e->getMessage());
            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat menghapus data di Google Sheets.');
        }
    }
}
