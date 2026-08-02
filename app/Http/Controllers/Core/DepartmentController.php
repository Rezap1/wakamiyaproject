<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Services\Core\DepartmentService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $departments = $this->departmentService->getAllDepartments();
        
        $search = $request->input('search');
        if (!empty($search)) {
            $departments = \App\Helpers\CollectionHelper::search($departments, $search, ['Department_ID', 'Department_Name']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $departments = $departments->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'DEPARTMENTS',
            'data' => $departments,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID', 'Nama Departemen', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Department_ID'] ?? '-', 
                    $row['Department_Name'] ?? '-', 
                    ($row['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif'
                ];
            },
            'isLandscape' => false,
            'summary' => '<tr><td>Total Departemen</td><td>: '.$departments->count().'</td></tr>'
        ];
    }
    protected $departmentService;
    protected $activityLogService;

    public function __construct(DepartmentService $departmentService, ActivityLogService $activityLogService)
    {
        $this->departmentService = $departmentService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $departments = $this->departmentService->getAllDepartments();
            
            // Custom collection pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $departments->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $departmentsPaginated = new LengthAwarePaginator($currentItems, count($departments), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('departments.index', ['departments' => $departmentsPaginated]);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data departemen dari Google Sheets.');
        }
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $data = $request->validated();
            $department = $this->departmentService->createDepartment($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_DEPARTMENT',
                "Mendaftarkan departemen baru: {$department['Department_ID']}",
                $request->ip(),
                null,
                $department,
                $request->userAgent()
            );

            return redirect()->route('departments.index')->with('success', 'Departemen berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating department: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);
            if (!$department) {
                return redirect()->route('departments.index')->with('error', 'Departemen tidak ditemukan.');
            }
            return view('departments.show', compact('department'));
        } catch (\Exception $e) {
            Log::error('Error showing department: ' . $e->getMessage());
            return redirect()->route('departments.index')->with('error', 'Terjadi kesalahan saat memuat data departemen.');
        }
    }

    public function edit($id)
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);
            if (!$department) {
                return redirect()->route('departments.index')->with('error', 'Departemen tidak ditemukan.');
            }
            return view('departments.edit', compact('department'));
        } catch (\Exception $e) {
            Log::error('Error editing department: ' . $e->getMessage());
            return redirect()->route('departments.index')->with('error', 'Terjadi kesalahan saat memuat data departemen.');
        }
    }

    public function update(UpdateDepartmentRequest $request, $id)
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);
            if (!$department) {
                return redirect()->route('departments.index')->with('error', 'Departemen tidak ditemukan.');
            }

            $data = $request->validated();
            $this->departmentService->updateDepartment($id, $data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_DEPARTMENT',
                "Memperbarui departemen: {$id}",
                $request->ip(),
                $department,
                array_merge($department, $data),
                $request->userAgent()
            );

            return redirect()->route('departments.index')->with('success', 'Departemen berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating department: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);
            if (!$department) {
                return redirect()->route('departments.index')->with('error', 'Departemen tidak ditemukan.');
            }

            $this->departmentService->deleteDepartment($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_DEPARTMENT',
                "Menonaktifkan departemen (Soft Delete): {$id}",
                request()->ip(),
                $department,
                array_merge($department, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('departments.index')->with('success', 'Departemen berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting department: ' . $e->getMessage());
            return redirect()->route('departments.index')->with('error', 'Terjadi kesalahan saat menghapus data di Google Sheets.');
        }
    }
}
