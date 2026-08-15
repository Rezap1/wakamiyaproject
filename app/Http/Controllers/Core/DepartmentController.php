<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Services\Core\DepartmentService;
use App\Helpers\UserResolverHelper;
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
            'headers' => ['ID', 'Nama Departemen', 'Manajer', 'Status'],
            'mapRow' => function($row) {
                $mgr = UserResolverHelper::getName($row['Manager_Employee_ID'] ?? '');
                return [
                    $row['Department_ID'] ?? '-', 
                    $row['Department_Name'] ?? '-', 
                    $mgr ?: '-',
                    ($row['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif'
                ];
            },
            'isLandscape' => false,
            'summary' => '<tr><td>Total Departemen</td><td>: '.$departments->count().'</td></tr>'
        ];
    }

    protected $departmentService;

    public function __construct(DepartmentService $departmentService)
    {
        $this->departmentService = $departmentService;
    }

    public function index()
    {
        try {
            $departments = $this->departmentService->getAllDepartments()->map(function ($dept) {
                $dept['Manager_Name'] = UserResolverHelper::getName($dept['Manager_Employee_ID'] ?? '');
                $dept['Created_By_Name'] = UserResolverHelper::getName($dept['Created_By'] ?? '');
                return $dept;
            });
            
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $departments->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $departmentsPaginated = new LengthAwarePaginator($currentItems, count($departments), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('departments.index', ['departments' => $departmentsPaginated]);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data departemen.');
        }
    }

    public function create()
    {
        $employees = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll();
        return view('departments.create', compact('employees'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $data = $request->validated();
            $department = $this->departmentService->createDepartment($data);

            return redirect()->route('departments.index')->with('success', 'Departemen berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating department: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);
            if (!$department) {
                return redirect()->route('departments.index')->with('error', 'Departemen tidak ditemukan.');
            }
            $department['Manager_Name'] = UserResolverHelper::getName($department['Manager_Employee_ID'] ?? '');
            $department['Created_By_Name'] = UserResolverHelper::getName($department['Created_By'] ?? '');
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
            $employees = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll();
            return view('departments.edit', compact('department', 'employees'));
        } catch (\Exception $e) {
            Log::error('Error editing department: ' . $e->getMessage());
            return redirect()->route('departments.index')->with('error', 'Terjadi kesalahan.');
        }
    }

    public function update(UpdateDepartmentRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $this->departmentService->updateDepartment($id, $data);
            return redirect()->route('departments.index')->with('success', 'Departemen berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating department: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->departmentService->deleteDepartment($id);
            return redirect()->route('departments.index')->with('success', 'Departemen berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting department: ' . $e->getMessage());
            return redirect()->route('departments.index')->with('error', 'Terjadi kesalahan saat menghapus data.');
        }
    }
}
