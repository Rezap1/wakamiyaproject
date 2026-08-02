<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;

class PersonalPayrollController extends Controller
{
    protected $payrollRepo;
    protected $employeeRepo;

    public function __construct(PayrollRepositoryInterface $payrollRepo, EmployeeRepositoryInterface $employeeRepo)
    {
        $this->payrollRepo = $payrollRepo;
        $this->employeeRepo = $employeeRepo;
    }

    public function index()
    {
        $userId = auth()->id() ?? 'U-001';
        $employees = $this->employeeRepo->fetchAll();
        $employee = collect($employees)->firstWhere('User_ID', $userId);
        if (!$employee) {
            $user = auth()->user();
            if ($user) {
                $employee = collect($employees)->firstWhere('Full_Name', $user->Full_Name);
            }
        }
        
        $payrolls = [];
        if ($employee) {
            $employeeId = $employee['Employee_ID'];
            $allPayrolls = $this->payrollRepo->getAll();
            $payrolls = collect($allPayrolls)->filter(function($p) use ($employeeId) {
                return ($p['Employee_ID'] ?? '') === $employeeId && ($p['Status'] ?? '') === 'Paid';
            })->sortByDesc(function($p) {
                return $p['Created_At'] ?? $p['Generated_At'] ?? '';
            })->values()->toArray();
        }

        return view('dashboard.personal-payroll', compact('payrolls', 'employee'));
    }
}
