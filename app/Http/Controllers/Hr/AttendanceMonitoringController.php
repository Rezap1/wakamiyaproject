<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use Carbon\Carbon;
use App\Helpers\CollectionHelper;
use App\Helpers\AttendanceStatusHelper;

class AttendanceMonitoringController extends Controller
{
    protected $attendanceRepository;
    protected $employeeRepository;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        EmployeeRepositoryInterface $employeeRepository
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->employeeRepository = $employeeRepository;
    }

    public function index(Request $request)
    {
        // 1. Fetch all attendances and employees
        $allAttendances = collect($this->attendanceRepository->fetchAll());
        $allEmployees = collect($this->employeeRepository->fetchAll());

        // Employee Mapping for N+1 prevention
        $employeesMap = $allEmployees->keyBy('Employee_ID');

        // Apply filters
        $dateFilter = $request->input('date', date('Y-m-d'));
        $startDateFilter = $request->input('start_date');
        $endDateFilter = $request->input('end_date');
        $nameFilter = $request->input('search');
        $employeeIdFilter = $request->input('employee_id');
        $statusFilter = $request->input('status');

        // Initial mapping: merge attendance with employee data where Employee_ID exists
        $employeeAttendances = $allAttendances->filter(function($att) use ($employeesMap) {
            $eId = trim($att['Employee_ID'] ?? '');
            if (empty($eId)) return false;
            // Validate it belongs to an employee
            return $employeesMap->has($eId);
        });

        if ($dateFilter && !$startDateFilter && !$endDateFilter) {
            $employeeAttendances = $employeeAttendances->filter(function($a) use ($dateFilter) {
                try {
                    if (empty($a['Attendance_Date'])) return false;
                    $aDate = Carbon::parse(str_replace('/', '-', $a['Attendance_Date']))->format('Y-m-d');
                    return $aDate === $dateFilter;
                } catch (\Exception $e) { return false; }
            });
        }

        if ($startDateFilter && $endDateFilter) {
            $employeeAttendances = $employeeAttendances->filter(function($a) use ($startDateFilter, $endDateFilter) {
                try {
                    if (empty($a['Attendance_Date'])) return false;
                    $aDate = Carbon::parse(str_replace('/', '-', $a['Attendance_Date']))->format('Y-m-d');
                    return $aDate >= $startDateFilter && $aDate <= $endDateFilter;
                } catch (\Exception $e) { return false; }
            });
        }

        if ($nameFilter) {
            $searchTerm = strtolower($nameFilter);
            $employeeAttendances = $employeeAttendances->filter(function($a) use ($searchTerm, $employeesMap) {
                $eId = $a['Employee_ID'] ?? '';
                $name = strtolower($employeesMap[$eId]['Full_Name'] ?? '');
                return str_contains($name, $searchTerm);
            });
        }

        if ($employeeIdFilter) {
            $employeeAttendances = $employeeAttendances->filter(function($a) use ($employeeIdFilter) {
                return stripos($a['Employee_ID'] ?? '', $employeeIdFilter) !== false;
            });
        }

        if ($statusFilter) {
            $employeeAttendances = $employeeAttendances->filter(function($a) use ($statusFilter) {
                return AttendanceStatusHelper::normalize($a['Status'] ?? '') === AttendanceStatusHelper::normalize($statusFilter);
            });
        }

        // Sort by date DESC, then by time DESC
        $employeeAttendances = $employeeAttendances->sortByDesc(function($a) {
            $date = $a['Attendance_Date'] ?? '1970-01-01';
            $time = $a['Check_In_Time'] ?? '00:00:00';
            return $date . ' ' . $time;
        })->values();

        // Calculate KPIs based on the filtered data
        $totalHadir = $employeeAttendances->filter(fn($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'PRESENT')->count();
        $totalTerlambat = $employeeAttendances->filter(fn($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'LATE')->count();
        $totalIzinSakit = $employeeAttendances->filter(fn($a) => in_array(AttendanceStatusHelper::normalize($a['Status'] ?? ''), ['SICK', 'PERMITTED'], true))->count();
        $totalAlpha = $employeeAttendances->filter(fn($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'ABSENT')->count();
        $totalAll = $employeeAttendances->count();

        // Paginate
        $paginated = CollectionHelper::paginate($employeeAttendances, 25)->withQueryString();

        return view('hr.attendance.monitoring', compact(
            'paginated', 'employeesMap', 'totalHadir', 'totalTerlambat', 'totalIzinSakit', 'totalAlpha', 'totalAll'
        ));
    }
}
