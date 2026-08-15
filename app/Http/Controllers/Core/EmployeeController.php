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
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeeController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $employees = $this->employeeService->getAllEmployees();
        $departments = $this->departmentService->getAllDepartments();
        $positions = $this->positionService->getAllPositions();
        
        $employees = $employees->map(function ($employee) use ($departments, $positions) {
            $dept = $departments->firstWhere('Department_ID', $employee['Department_ID']);
            $pos = $positions->firstWhere('Position_ID', $employee['Position_ID']);
            $employee['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
            $employee['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';
            return $employee;
        });

        $search = $request->input('search');
        if (!empty($search)) {
            $employees = \App\Helpers\CollectionHelper::search($employees, $search, ['Employee_ID', 'First_Name', 'Last_Name', 'Email', 'Phone', 'Department_Name', 'Position_Name']);
        }
        if ($request->filled('department')) {
            $employees = $employees->where('Department_ID', $request->input('department'));
        }
        if ($request->filled('position')) {
            $employees = $employees->where('Position_ID', $request->input('position'));
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $employees = $employees->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }

        $activeCount = $employees->where('Is_Active', 'TRUE')->count();
        $summary = "<tr><td>Total Karyawan</td><td>: {$employees->count()}</td><td width='20px'></td><td>Karyawan Aktif</td><td>: {$activeCount}</td></tr>";

        return [
            'moduleName' => 'EMPLOYEES',
            'data' => $employees,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['NIP', 'Nama Lengkap', 'Email', 'Departemen', 'Jabatan', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Employee_Number'] ?? '-', 
                    $row['Full_Name'] ?? '-',
                    $row['Email'] ?? '-',
                    $row['Department_Name'] ?? '-',
                    $row['Position_Name'] ?? '-',
                    ($row['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif'
                ];
            },
            'isLandscape' => true,
            'summary' => $summary
        ];
    }

    protected $employeeService;
    protected $departmentService;
    protected $positionService;
    protected $userService;

    public function __construct(
        EmployeeService $employeeService, 
        DepartmentService $departmentService, 
        PositionService $positionService,
        \App\Services\Core\UserService $userService
    ) {
        $this->employeeService = $employeeService;
        $this->departmentService = $departmentService;
        $this->positionService = $positionService;
        $this->userService = $userService;
    }

    public function index(\Illuminate\Http\Request $request)
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

            // Search
            $search = $request->input('search');
            if (!empty($search)) {
                $employees = \App\Helpers\CollectionHelper::search($employees, $search, ['Employee_ID', 'First_Name', 'Last_Name', 'Email', 'Phone', 'Department_Name', 'Position_Name']);
            }

            // Filter
            if ($request->filled('department')) {
                $employees = $employees->where('Department_ID', $request->input('department'));
            }

            if ($request->filled('position')) {
                $employees = $employees->where('Position_ID', $request->input('position'));
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $employees = $employees->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Date Filter
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
                $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
                
                $employees = $employees->filter(function ($item) use ($dateFrom, $dateTo) {
                    $dateStr = $item['Join_Date'] ?? $item['Created_At'] ?? null;
                    if ($dateStr) {
                        $itemDate = \Carbon\Carbon::parse($dateStr);
                        return $itemDate->between($dateFrom, $dateTo);
                    }
                    return false;
                });
            }

            // Pagination
            $employeesPaginated = \App\Helpers\CollectionHelper::paginate($employees, 10)->withQueryString();
            
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
            
            // Fetch users that do not have an employee record yet
            $allUsers = $this->userService->getAllUsers();
            $allEmployees = $this->employeeService->getAllEmployees();
            $usedUserIds = $allEmployees->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds) {
                return !in_array($user['User_ID'], $usedUserIds) && ($user['Is_Active'] ?? 'TRUE') === 'TRUE';
            })->values();
            
            return view('employees.create', compact('departments', 'positions', 'users'));
        } catch (\Exception $e) {
            Log::error('Error loading create employee form: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Gagal memuat referensi departemen/posisi.');
        }
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('Profile_Photo')) {
                $data['Profile_Photo'] = $request->file('Profile_Photo');
            }
            $employee = $this->employeeService->createEmployee($data);

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
            
            $dept = $this->departmentService->getDepartmentById($employee['Department_ID'] ?? '');
            $pos = $this->positionService->getPositionById($employee['Position_ID'] ?? '');
            
            $employee['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
            $employee['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';

            $isAuthorized = $this->employeeService->isAuthorizedForSensitiveData(auth()->user());
            $sanitizedEmployee = $this->employeeService->maskSensitiveFields($employee, $isAuthorized);

            return view('employees.show', [
                'employee' => $sanitizedEmployee,
                'isAuthorized' => $isAuthorized
            ]);
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
            
            $allUsers = $this->userService->getAllUsers();
            $allEmployees = $this->employeeService->getAllEmployees();
            $usedUserIds = $allEmployees->where('Employee_ID', '!=', $id)->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds, $employee) {
                return !in_array($user['User_ID'], $usedUserIds);
            })->values();

            return view('employees.edit', compact('employee', 'departments', 'positions', 'users'));
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
            if ($request->hasFile('Profile_Photo')) {
                $data['Profile_Photo'] = $request->file('Profile_Photo');
            }

            $this->employeeService->updateEmployee($id, $data);

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

            return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting employee: ' . $e->getMessage());
            return redirect()->route('employees.index')->with('error', 'Terjadi kesalahan saat menghapus data karyawan.');
        }
    }

    public function sendEmail(\Illuminate\Http\Request $request, $id)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            $this->employeeService->sendEmployeeDataEmail($id, $request->input('email'), auth()->user());

            return back()->with('success', 'Data karyawan berhasil dikirim via email ke: ' . $request->input('email'));
        } catch (\Exception $e) {
            Log::error('Error sending employee email: ' . $e->getMessage());
            return back()->with('error', 'Gagal mengirim email: ' . $e->getMessage());
        }
    }

    public function lookup($id)
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            if (!$employee) {
                return response()->json(['error' => 'Data karyawan tidak ditemukan.'], 404);
            }

            $dept = $this->departmentService->getDepartmentById($employee['Department_ID']);
            $pos = $this->positionService->getPositionById($employee['Position_ID']);

            return response()->json([
                'Employee_ID' => $employee['Employee_ID'] ?? '',
                'Employee_Number' => $employee['Employee_Number'] ?? '',
                'Full_Name' => $employee['Full_Name'] ?? '',
                'Email' => $employee['Email'] ?? '',
                'Phone_Number' => $employee['Phone_Number'] ?? '',
                'Employment_Status' => $employee['Employment_Status'] ?? '',
                'Department_Name' => $dept ? $dept['Department_Name'] : '-',
                'Position_Name' => $pos ? $pos['Position_Name'] : '-',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan internal.'], 500);
        }
    }
}
