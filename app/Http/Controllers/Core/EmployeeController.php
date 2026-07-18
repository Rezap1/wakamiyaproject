<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Services\Core\EmployeeService;
use App\Services\Core\DepartmentService;
use App\Services\Core\PositionService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    protected $employeeService;
    protected $departmentService;
    protected $positionService;
    protected $activityLogService;

    public function __construct(
        EmployeeService $employeeService, 
        DepartmentService $departmentService, 
        PositionService $positionService,
        ActivityLogService $activityLogService
    ) {
        $this->employeeService = $employeeService;
        $this->departmentService = $departmentService;
        $this->positionService = $positionService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $employees = $this->employeeService->getAllEmployees();
            $departments = $this->departmentService->getAllDepartments();
            $positions = $this->positionService->getAllPositions();
            
            // Map names for display
            $employees = $employees->map(function ($employee) use ($departments, $positions) {
                $dept = $departments->firstWhere('Department_ID', $employee['Department_ID']);
                $pos = $positions->firstWhere('Position_ID', $employee['Position_ID']);
                
                $employee['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
                $employee['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';
                return $employee;
            });

            // Pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $employees->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $employeesPaginated = new LengthAwarePaginator($currentItems, count($employees), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('employees.index', [
                'employees' => $employeesPaginated,
                'departments' => $departments->where('Is_Active', 'TRUE'),
                'positions' => $positions->where('Is_Active', 'TRUE')
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employees: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data karyawan dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $departments = $this->departmentService->getAllDepartments()->where('Is_Active', 'TRUE');
            $positions = $this->positionService->getAllPositions()->where('Is_Active', 'TRUE');
            
            return view('employees.create', compact('departments', 'positions'));
        } catch (\Exception $e) {
            Log::error('Error loading create employee form: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Gagal memuat referensi departemen/posisi.');
        }
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $data = $request->validated();
            $employee = $this->employeeService->createEmployee($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_EMPLOYEE',
                "Mendaftarkan karyawan baru: {$employee['Employee_ID']}",
                $request->ip(),
                null,
                $employee,
                $request->userAgent()
            );

            return redirect()->route('employees.index')->with('success', 'Karyawan berhasil didaftarkan.');
        } catch (\Exception $e) {
            Log::error('Error creating employee: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Data karyawan tidak ditemukan.');
            }
            
            $dept = $this->departmentService->getDepartmentById($employee['Department_ID']);
            $pos = $this->positionService->getPositionById($employee['Position_ID']);
            
            $employee['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
            $employee['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';

            return view('employees.show', compact('employee'));
        } catch (\Exception $e) {
            Log::error('Error showing employee: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Terjadi kesalahan saat memuat data karyawan.');
        }
    }

    public function edit($id)
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Data karyawan tidak ditemukan.');
            }
            
            $departments = $this->departmentService->getAllDepartments()->where('Is_Active', 'TRUE');
            $positions = $this->positionService->getAllPositions()->where('Is_Active', 'TRUE');
            
            // Include current department if inactive
            if (!collect($departments)->contains('Department_ID', $employee['Department_ID'])) {
                $currentDept = $this->departmentService->getDepartmentById($employee['Department_ID']);
                if ($currentDept) $departments->push($currentDept);
            }
            
            // Include current position if inactive
            if (!collect($positions)->contains('Position_ID', $employee['Position_ID'])) {
                $currentPos = $this->positionService->getPositionById($employee['Position_ID']);
                if ($currentPos) $positions->push($currentPos);
            }

            return view('employees.edit', compact('employee', 'departments', 'positions'));
        } catch (\Exception $e) {
            Log::error('Error editing employee: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Terjadi kesalahan saat memuat form edit.');
        }
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Data karyawan tidak ditemukan.');
            }

            $data = $request->validated();
            $this->employeeService->updateEmployee($id, $data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_EMPLOYEE',
                "Memperbarui data karyawan: {$id}",
                $request->ip(),
                $employee,
                array_merge($employee, $data),
                $request->userAgent()
            );

            return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating employee: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Data karyawan tidak ditemukan.');
            }

            $this->employeeService->deleteEmployee($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_EMPLOYEE',
                "Menonaktifkan karyawan (Soft Delete): {$id}",
                request()->ip(),
                $employee,
                array_merge($employee, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting employee: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Terjadi kesalahan saat menghapus data karyawan.');
        }
    }
}
