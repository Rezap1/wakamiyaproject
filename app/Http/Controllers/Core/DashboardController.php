<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\UserService;

class DashboardController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        // Fetch real data from Google Sheets for Employees (Users)
        $users = $this->userService->getAllUsers();
        $activeEmployeesCount = collect($users)->where('status', 'Active')->count();

        // Dummy KPI Data for other modules (Will be dynamic once their modules are built)
        $kpi = [
            'total_students' => 150, // To be implemented in Student Module
            'total_employees' => $activeEmployeesCount,

            'total_company' => 10,
            'total_alumni' => 300,
            'cash' => 500000000,
            'bank' => 1500000000,
            'profit' => 250000000,
            'outstanding_payment' => 50000000,
            'upcoming_departure' => 15,
            'upcoming_interview' => 5,
        ];

        return view('dashboard.index', compact('kpi'));
    }
}
