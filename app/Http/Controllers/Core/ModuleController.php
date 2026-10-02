<?php

namespace App\Http\Controllers\Core;

use App\Helpers\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Services\Core\ModuleService;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModuleController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {

        $modules = $this->moduleService->getAllModules();

        $search = $request->input('search');
        if (! empty($search)) {
            $modules = CollectionHelper::search($modules, $search, ['Module_Code', 'Module_Name', 'Module_Group']);
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
            'mapRow' => function ($row) {

                return [
                    $row['Module_Code'] ?? '-',
                    $row['Module_Group'] ?? '-',
                    $row['Module_Name'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif',
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$modules->count().'</td></tr>',
        ];
    }

    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    public function index(Request $request)
    {
        try {
            $modules = $this->moduleService->getAllModules();

            $search = $request->input('search');
            if (! empty($search)) {
                $modules = CollectionHelper::search($modules, $search, ['Module_ID', 'Module_Code', 'Module_Name', 'Module_Group']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $modules = $modules->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Pagination
            $modulesPaginated = CollectionHelper::paginate($modules, 10)->withQueryString();

            return view('modules.index', ['modules' => $modulesPaginated]);
        } catch (\Exception $e) {
            Log::error('Error fetching modules: '.$e->getMessage());

            return redirect()->route('dashboard')->with('error', 'Gagal memuat data modul dari database.');
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
            Log::error('Error creating module: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke database.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (! $module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            return view('modules.show', compact('module'));
        } catch (\Exception $e) {
            Log::error('Error showing module: '.$e->getMessage());

            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat memuat data modul.');
        }
    }

    public function edit($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (! $module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            return view('modules.edit', compact('module'));
        } catch (\Exception $e) {
            Log::error('Error editing module: '.$e->getMessage());

            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat memuat data modul.');
        }
    }

    public function update(UpdateModuleRequest $request, $id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (! $module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            $data = $request->validated();
            $this->moduleService->updateModule($id, $data);

            return redirect()->route('modules.index')->with('success', 'Modul berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating module: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di database.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $module = $this->moduleService->getModuleById($id);
            if (! $module) {
                return redirect()->route('modules.index')->with('error', 'Modul tidak ditemukan.');
            }

            $this->moduleService->deleteModule($id);

            return redirect()->route('modules.index')->with('success', 'Modul berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting module: '.$e->getMessage());

            return redirect()->route('modules.index')->with('error', 'Terjadi kesalahan saat menghapus data di database.');
        }
    }
}
