<?php
namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\PayrollService;
use App\Services\Core\ActivityLogService;

class PayrollController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Payroll_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $payrolls = $this->payrollService->getAll()->filter(function ($item) {
            return ($item['Status'] ?? '') !== 'Cancelled';
        });

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
            
            $payrolls = $payrolls->filter(function ($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Created_At'] ?? null;
                if ($dateStr) {
                    $itemDate = \Carbon\Carbon::parse($dateStr);
                    return $itemDate->between($dateFrom, $dateTo);
                }
                return false;
            });
        }
        
                $employeeRepo = app(\App\Repositories\GoogleSheets\EmployeeRepository::class);
        $employees = $employeeRepo->fetchAll()->keyBy('Employee_ID');
        
                $employeeRepo = app(\App\Repositories\GoogleSheets\EmployeeRepository::class);
        $employees = $employeeRepo->fetchAll()->keyBy('Employee_ID');
        
        $positionRepo = app(\App\Repositories\GoogleSheets\PositionRepository::class);
        $positions = $positionRepo->fetchAll()->keyBy('Position_ID');
        
        return [
            'moduleName' => 'Penggajian (Payroll)',
            'data' => collect(array_values($payrolls->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Payroll', 'Karyawan', 'Role', 'Periode', 'Gaji Bersih', 'Status'],
            'mapRow' => function($row) use ($employees, $positions) {
                $base = (float)($row['Base_Salary'] ?? 0);
                $allowances = (float)($row['Total_Allowances'] ?? 0);
                $deductions = (float)($row['Total_Deductions'] ?? 0);
                $net = (float)($row['Net_Salary'] ?? ($base + $allowances - $deductions));
                
                $empId = $row['Employee_ID'] ?? null;
                $empName = $empId && isset($employees[$empId]) ? $employees[$empId]['Full_Name'] : 'Tidak Diketahui';
                $posId = $empId && isset($employees[$empId]) ? $employees[$empId]['Position_ID'] : null;
                $roleName = $posId && isset($positions[$posId]) ? $positions[$posId]['Position_Name'] : 'Karyawan';
                
                $employeeDisplay = $empName . ' (' . $empId . ')';
                $period = $row['Payroll_Period'] ?? $row['Period'] ?? '-';
                
                return [
                    $row['Payroll_ID'] ?? '-', 
                    $employeeDisplay,
                    $roleName,
                    $period, 
                    'Rp ' . number_format($net, 0, ',', '.'),
                    $row['Status'] ?? 'Draft'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$payrolls->count().'</td></tr>'
        ];

    }

    protected $payrollService, $activityLogService;

        public function __construct(PayrollService $payrollService, ActivityLogService $activityLogService)
    {
        $this->payrollService = $payrollService;
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        $payrolls = $this->payrollService->getAll()->filter(function ($item) {
            return ($item['Status'] ?? '') !== 'Cancelled';
        });

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
            
            $payrolls = $payrolls->filter(function ($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Created_At'] ?? null;
                if ($dateStr) {
                    $itemDate = \Carbon\Carbon::parse($dateStr);
                    return $itemDate->between($dateFrom, $dateTo);
                }
                return false;
            });
        }
        
        $employeeRepo = app(\App\Repositories\GoogleSheets\EmployeeRepository::class);
        $employees = $employeeRepo->fetchAll()->keyBy('Employee_ID');
        
        $positionRepo = app(\App\Repositories\GoogleSheets\PositionRepository::class);
        $positions = $positionRepo->fetchAll()->keyBy('Position_ID');
        
        $payrolls = $payrolls->map(function($item) use ($employees, $positions) {
            $empId = $item['Employee_ID'] ?? null;
            $empName = $empId && isset($employees[$empId]) ? $employees[$empId]['Full_Name'] : 'Tidak Diketahui';
            $posId = $empId && isset($employees[$empId]) ? $employees[$empId]['Position_ID'] : null;
            $roleName = $posId && isset($positions[$posId]) ? $positions[$posId]['Position_Name'] : 'Karyawan';
            
            $item['Payroll_Number'] = $item['Payroll_ID'] ?? '';
            $item['Payroll_Period'] = $item['Payroll_Period'] ?? $item['Period'] ?? '';
            $item['Employee_ID'] = $empName . ' (' . $empId . ')';
            $item['Role'] = $roleName;
            
            $base = (float)($item['Base_Salary'] ?? 0);
            $allowances = (float)($item['Total_Allowances'] ?? 0);
            $deductions = (float)($item['Total_Deductions'] ?? 0);
            $item['Net_Salary'] = (float)($item['Net_Salary'] ?? ($base + $allowances - $deductions));
            return $item;
        });
        
        $payrolls = \App\Helpers\CollectionHelper::paginate($payrolls, 10)->withQueryString();

        return view('hr.payroll.index', compact('payrolls'));
    }

    public function create(\App\Repositories\GoogleSheets\EmployeeRepository $employeeRepo)
    {
        $employees = $employeeRepo->fetchAll();
        return view('hr.payroll.create', compact('employees'));
    }

    public function store(\App\Http\Requests\StorePayrollRequest $request)
    {
        try {
            $data = $request->except('_token');
            $this->payrollService->processPayroll($data);
            $this->activityLogService->log(auth()->id(), 'CREATE_PAYROLL', 'HR', 'Generated payroll for ' . $request->Employee_ID);
            return redirect()->route('payrolls.index')->with('success', 'Payroll generated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $payroll = $this->payrollService->getById($id);
        return view('hr.payroll.show', compact('payroll'));
    }

    public function edit($id, \App\Repositories\GoogleSheets\EmployeeRepository $employeeRepo)
    {
        $payroll = $this->payrollService->getById($id);
        $employees = $employeeRepo->fetchAll();
        return view('hr.payroll.edit', compact('payroll', 'employees'));
    }

    public function update(\App\Http\Requests\UpdatePayrollRequest $request, $id)
    {
        try {
            // Manual edit of Payroll is blocked if not Draft, handled by service ideally
            // But we only allow updating actual payroll data (not status)
            throw new \Exception("Edit data form secara langsung telah dihapus sesuai EPS. Gunakan tombol aksi yang tersedia.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function submit($id)
    {
        try {
            $user = auth()->user()->email ?? 'HR Admin';
            $this->payrollService->updateStatus($id, 'Waiting Approval', $user);
            $this->activityLogService->log(auth()->id(), 'SUBMIT_PAYROLL', 'HR', "Submitted payroll {$id} for approval");
            return redirect()->route('payrolls.index')->with('success', 'Payroll submitted for approval.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve($id)
    {
        try {
            $user = auth()->user()->email ?? 'Director';
            $this->payrollService->updateStatus($id, 'Approved', $user);
            $this->activityLogService->log(auth()->id(), 'APPROVE_PAYROLL', 'DIRECTOR', "Approved payroll {$id}");
            return redirect()->route('payrolls.index')->with('success', 'Payroll approved.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject($id)
    {
        try {
            $user = auth()->user()->email ?? 'Director';
            $this->payrollService->updateStatus($id, 'Rejected', $user);
            $this->activityLogService->log(auth()->id(), 'REJECT_PAYROLL', 'DIRECTOR', "Rejected payroll {$id}");
            return redirect()->route('payrolls.index')->with('success', 'Payroll rejected.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function pay(Request $request, $id)
    {
        try {
            $user = auth()->user()->email ?? 'HR';
            $paymentProofPath = null;
            if ($request->hasFile('Payment_Proof')) {
                $file = $request->file('Payment_Proof');
                $filename = 'proof_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('proofs', $filename, 'public');
                $paymentProofPath = 'storage/proofs/' . $filename;
            } elseif ($request->filled('Payment_Proof_Url')) {
                $paymentProofPath = $request->input('Payment_Proof_Url');
            }
            
            $notes = $request->input('Notes');
            $this->payrollService->updateStatus($id, 'Paid', $user, $paymentProofPath, $notes);
            $this->activityLogService->log(auth()->id(), 'PAY_PAYROLL', 'HR', "Paid payroll {$id} and uploaded proof");
            return redirect()->route('payrolls.index')->with('success', 'Payroll paid and proof uploaded.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        try {
            $this->payrollService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETE_PAYROLL', 'HR', "Deleted payroll {$id}");
            return redirect()->route('payrolls.index')->with('success', 'Payroll deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
