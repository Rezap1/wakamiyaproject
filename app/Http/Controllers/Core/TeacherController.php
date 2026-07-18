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

class TeacherController extends Controller
{
    protected $teacherService;
    protected $employeeService;
    protected $departmentService;
    protected $positionService;
    protected $activityLogService;

    public function __construct(
        TeacherService $teacherService,
        EmployeeService $employeeService,
        DepartmentService $departmentService,
        PositionService $positionService,
        ActivityLogService $activityLogService
    ) {
        $this->teacherService = $teacherService;
        $this->employeeService = $employeeService;
        $this->departmentService = $departmentService;
        $this->positionService = $positionService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $teachers = $this->teacherService->getAllTeachers();
            $employees = $this->employeeService->getAllEmployees();
            $departments = $this->departmentService->getAllDepartments();
            $positions = $this->positionService->getAllPositions();
            
            // Map names for display
            $teachers = $teachers->map(function ($teacher) use ($employees, $departments, $positions) {
                $emp = $employees->firstWhere('Employee_ID', $teacher['Employee_ID']);
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

            // Pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $teachers->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $teachersPaginated = new LengthAwarePaginator($currentItems, count($teachers), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
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
            $allEmployees = $this->employeeService->getAllEmployees()->where('Is_Active', 'TRUE');
            $allTeachers = $this->teacherService->getAllTeachers();
            
            // Filter out employees who are already teachers
            $teacherEmployeeIds = $allTeachers->pluck('Employee_ID')->toArray();
            $employees = $allEmployees->filter(function($emp) use ($teacherEmployeeIds) {
                return !in_array($emp['Employee_ID'], $teacherEmployeeIds);
            });
            
            // Prepare JSON data for Autofill
            $employeeData = $employees->mapWithKeys(function($emp) {
                return [$emp['Employee_ID'] => [
                    'Full_Name' => $emp['Full_Name'] ?? '',
                    'Gender' => $emp['Gender'] ?? '',
                    'Phone_Number' => $emp['Phone_Number'] ?? '',
                    'Email' => $emp['Email'] ?? ''
                ]];
            })->toJson();
            
            return view('teachers.create', compact('employees', 'employeeData'));
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
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_TEACHER',
                "Mendaftarkan guru baru: {$teacher['Teacher_ID']}",
                $request->ip(),
                null,
                $teacher,
                $request->userAgent()
            );

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
            
            $emp = $this->employeeService->getEmployeeById($teacher['Employee_ID']);
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

            return view('teachers.show', compact('teacher'));
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
            
            $allEmployees = $this->employeeService->getAllEmployees()->where('Is_Active', 'TRUE');
            $allTeachers = $this->teacherService->getAllTeachers();
            
            // Filter out employees who are already teachers, EXCEPT the current teacher's employee
            $teacherEmployeeIds = $allTeachers->where('Teacher_ID', '!=', $id)->pluck('Employee_ID')->toArray();
            
            $employees = collect();
            
            // Include current employee even if inactive or anything
            $currentEmp = $this->employeeService->getEmployeeById($teacher['Employee_ID']);
            if ($currentEmp) {
                $employees->push($currentEmp);
            }
            
            foreach ($allEmployees as $emp) {
                if (!in_array($emp['Employee_ID'], $teacherEmployeeIds) && $emp['Employee_ID'] !== $teacher['Employee_ID']) {
                    $employees->push($emp);
                }
            }
            
            // Prepare JSON data for Autofill
            $employeeData = $employees->mapWithKeys(function($emp) {
                return [$emp['Employee_ID'] => [
                    'Full_Name' => $emp['Full_Name'] ?? '',
                    'Gender' => $emp['Gender'] ?? '',
                    'Phone_Number' => $emp['Phone_Number'] ?? '',
                    'Email' => $emp['Email'] ?? ''
                ]];
            })->toJson();

            return view('teachers.edit', compact('teacher', 'employees', 'employeeData'));
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
            
            // Re-fetch to get updated mapping (readonly fields updated)
            $updatedTeacher = $this->teacherService->getTeacherById($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_TEACHER',
                "Memperbarui data guru: {$id}",
                $request->ip(),
                $teacher,
                $updatedTeacher,
                $request->userAgent()
            );

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
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_TEACHER',
                "Menonaktifkan guru (Soft Delete): {$id}",
                request()->ip(),
                $teacher,
                array_merge($teacher, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('teachers.index')->with('success', 'Data tenaga pendidik berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting teacher: ' . $e->getMessage());
            return redirect()->route('teachers.index')->with('error', 'Terjadi kesalahan saat menghapus data tenaga pendidik.');
        }
    }
}
