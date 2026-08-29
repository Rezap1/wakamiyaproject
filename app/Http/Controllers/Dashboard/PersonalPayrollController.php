<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Helpers\StoragePathHelper;

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
        $employee = $this->getAuthenticatedEmployee();
        
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

    public function downloadProof(Request $request, $id)
    {
        $employee = $this->getAuthenticatedEmployee();
        $payroll = collect($this->payrollRepo->getAll())->first(function ($payroll) use ($id, $employee) {
            return ($payroll['Payroll_ID'] ?? '') === $id
                && ($payroll['Employee_ID'] ?? '') === ($employee['Employee_ID'] ?? '')
                && !empty($payroll['Payment_Proof']);
        });

        if (!$payroll) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        $path = StoragePathHelper::privateFileResponsePath($payroll['Payment_Proof']);
        if (!$path) {
            abort(404, 'File bukti pembayaran tidak ditemukan di server.');
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $id);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return response()->download($path, 'bukti-pembayaran-payroll-' . $safeId . ($extension ? '.' . $extension : ''));
    }

    private function getAuthenticatedEmployee(): array
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Profil pegawai tidak ditemukan.');
        }

        $userId = $user->User_ID ?? auth()->id();
        $employees = $this->employeeRepo->fetchAll();
        $employee = collect($employees)->firstWhere('User_ID', $userId);
        if (!$employee) {
            abort(403, 'Profil pegawai tidak ditemukan.');
        }

        return (array) $employee;
    }
}
