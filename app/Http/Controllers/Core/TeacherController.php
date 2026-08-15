<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Services\Core\TeacherService;
use App\Services\Core\EmployeeService;
use App\Services\Core\DepartmentService;
use App\Services\Core\PositionService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class TeacherController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $teachers = $this->teacherService->getAllTeachers();
        $employees = $this->employeeService->getAllEmployees();
        $departments = $this->departmentService->getAllDepartments();
        $positions = $this->positionService->getAllPositions();
        $users = $this->userService->getAllUsers();
        
        $teachers = $teachers->map(function ($teacher) use ($users, $employees, $departments, $positions) {
            $user = collect($users)->firstWhere('User_ID', $teacher['User_ID'] ?? '');
            if ($user) {
                $teacher['Full_Name'] = $user['Full_Name'] ?? '-';
                $teacher['Email'] = $user['Email'] ?? '-';
                $empId = $user['Employee_ID'] ?? $teacher['Employee_ID'] ?? null;
                $emp = collect($employees)->firstWhere('Employee_ID', $empId);
            } else { $emp = null; }
            
            if ($emp) {
                $dept = collect($departments)->firstWhere('Department_ID', $emp['Department_ID']);
                $pos = collect($positions)->firstWhere('Position_ID', $emp['Position_ID']);
                $teacher['Department_Name'] = $dept ? $dept['Department_Name'] : '-';
                $teacher['Position_Name'] = $pos ? $pos['Position_Name'] : '-';
            } else {
                $teacher['Department_Name'] = '-';
                $teacher['Position_Name'] = '-';
            }
            return $teacher;
        });

        $search = $request->input('search');
        if (!empty($search)) {
            $teachers = \App\Helpers\CollectionHelper::search($teachers, $search, ['NUPTK', 'Full_Name', 'Department_Name', 'Position_Name']);
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $teachers = $teachers->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'Pengajar (Teacher)',
            'data' => collect(array_values($teachers->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['NUPTK', 'Nama Pengajar', 'Email', 'Departemen', 'Jabatan', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['NUPTK'] ?? '-',
                    $row['Full_Name'] ?? ($row['Teacher_ID'] ?? '-'),
                    $row['Email'] ?? '-',
                    $row['Department_Name'] ?? '-',
                    $row['Position_Name'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$teachers->count().'</td></tr>'
        ];
    }

    protected $teacherService;
    protected $employeeService;
    protected $departmentService;
    protected $positionService;
    protected $userService;

    public function __construct(
        TeacherService $teacherService,
        EmployeeService $employeeService,
        DepartmentService $departmentService,
        PositionService $positionService,
        \App\Services\Core\UserService $userService
    ) {
        $this->teacherService = $teacherService;
        $this->employeeService = $employeeService;
        $this->departmentService = $departmentService;
        $this->positionService = $positionService;
        $this->userService = $userService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $teachers = $this->teacherService->getAllTeachers();
            $employees = $this->employeeService->getAllEmployees();
            $departments = $this->departmentService->getAllDepartments();
            $positions = $this->positionService->getAllPositions();
            
            $users = $this->userService->getAllUsers();
            
            // Map names for display
            $teachers = $teachers->map(function ($teacher) use ($users, $employees, $departments, $positions) {
                $user = collect($users)->firstWhere('User_ID', $teacher['User_ID'] ?? '');
                
                if ($user) {
                    $teacher['Full_Name'] = $user['Full_Name'] ?? '-';
                    $teacher['Email'] = $user['Email'] ?? '-';
                    $teacher['Phone_Number'] = $user['Phone_Number'] ?? '-';
                    
                    // Try to find Employee if User has Employee_ID, or just fallback
                    $empId = $user['Employee_ID'] ?? $teacher['Employee_ID'] ?? null;
                    $emp = $employees->firstWhere('Employee_ID', $empId);
                } else {
                    $emp = null;
                }
                
                if ($emp) {
                    $teacher['Employee_Number'] = $emp['Employee_Number'];
                    
                    $dept = $departments->firstWhere('Department_ID', $emp['Department_ID']);
                    $pos = $positions->firstWhere('Position_ID', $emp['Position_ID']);
                    
                    $teacher['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
                    $teacher['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';
                } else {
                    $teacher['Employee_Number'] = '-';
                    $teacher['Department_Name'] = '-';
                    $teacher['Position_Name'] = '-';
                }
                
                return $teacher;
            });

            $search = $request->input('search');
            if (!empty($search)) {
                $teachers = \App\Helpers\CollectionHelper::search($teachers, $search, ['Teacher_ID', 'Employee_ID', 'NUPTK', 'Full_Name', 'Email', 'Phone_Number', 'Employee_Number', 'Department_Name', 'Position_Name']);
            }

            if ($request->filled('teaching')) {
                $teaching = $request->input('teaching');
                if ($teaching !== 'all') {
                    $teachers = $teachers->where('Teaching_Status', $teaching);
                }
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $teachers = $teachers->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Pagination
            $teachersPaginated = \App\Helpers\CollectionHelper::paginate($teachers, 10)->withQueryString();
            
            return view('teachers.index', [
                'teachers' => $teachersPaginated
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching teachers: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data tenaga pendidik dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $allUsers = $this->userService->getAllUsers();
            $allTeachers = $this->teacherService->getAllTeachers();
            $usedUserIds = $allTeachers->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds) {
                return !in_array($user['User_ID'], $usedUserIds) && ($user['Is_Active'] ?? 'TRUE') === 'TRUE';
            })->values();
            
            return view('teachers.create', compact('users'));
        } catch (\Exception $e) {
            Log::error('Error loading create teacher form: ' . $e->getMessage());
            return redirect()->route('teachers.index')->with('error', 'Gagal memuat referensi data pegawai.');
        }
    }

    public function store(StoreTeacherRequest $request)
    {
        try {
            $data = $request->validated();
            $teacher = $this->teacherService->createTeacher($data);

            return redirect()->route('teachers.index')->with('success', 'Tenaga Pendidik berhasil didaftarkan.');
        } catch (\Exception $e) {
            Log::error('Error creating teacher: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);
            if (!$teacher) {
                return redirect()->route('teachers.index')->with('error', 'Data tenaga pendidik tidak ditemukan.');
            }
            
            $user = collect($this->userService->getAllUsers())->firstWhere('User_ID', $teacher['User_ID'] ?? '');
            $empId = $user ? ($user['Employee_ID'] ?? $teacher['Employee_ID'] ?? null) : null;
            $emp = $empId ? $this->employeeService->getEmployeeById($empId) : null;
            
            if ($emp) {
                $teacher['Employee_Number'] = $emp['Employee_Number'];
                $dept = $this->departmentService->getDepartmentById($emp['Department_ID']);
                $pos = $this->positionService->getPositionById($emp['Position_ID']);
                
                $teacher['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
                $teacher['Position_Name'] = $pos ? $pos['Position_Name'] : 'Tidak Diketahui';
            } else {
                $teacher['Employee_Number'] = '-';
                $teacher['Department_Name'] = '-';
                $teacher['Position_Name'] = '-';
            }

            return view('teachers.show', compact('teacher', 'user', 'emp'));
        } catch (\Exception $e) {
            Log::error('Error showing teacher: ' . $e->getMessage());
            return redirect()->route('teachers.index')->with('error', 'Terjadi kesalahan saat memuat data tenaga pendidik.');
        }
    }

    public function edit($id)
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);
            if (!$teacher) {
                return redirect()->route('teachers.index')->with('error', 'Data tenaga pendidik tidak ditemukan.');
            }
            $allUsers = $this->userService->getAllUsers();
            $allTeachers = $this->teacherService->getAllTeachers();
            $usedUserIds = $allTeachers->where('Teacher_ID', '!=', $id)->pluck('User_ID')->filter()->toArray();
            
            $users = collect($allUsers)->filter(function($user) use ($usedUserIds) {
                return !in_array($user['User_ID'], $usedUserIds);
            })->values();

            return view('teachers.edit', compact('teacher', 'users'));
        } catch (\Exception $e) {
            Log::error('Error editing teacher: ' . $e->getMessage());
            return redirect()->route('teachers.index')->with('error', 'Terjadi kesalahan saat memuat form edit.');
        }
    }

    public function update(UpdateTeacherRequest $request, $id)
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);
            if (!$teacher) {
                return redirect()->route('teachers.index')->with('error', 'Data tenaga pendidik tidak ditemukan.');
            }

            $data = $request->validated();
            $this->teacherService->updateTeacher($id, $data);

            return redirect()->route('teachers.index')->with('success', 'Data tenaga pendidik berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating teacher: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);
            if (!$teacher) {
                return redirect()->route('teachers.index')->with('error', 'Data tenaga pendidik tidak ditemukan.');
            }

            $this->teacherService->deleteTeacher($id);

            return redirect()->route('teachers.index')->with('success', 'Data tenaga pendidik berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting teacher: ' . $e->getMessage());
            return redirect()->route('teachers.index')->with('error', 'Terjadi kesalahan saat menghapus data tenaga pendidik.');
        }
    }
}
