<?php
$dirCtrl = 'app/Http/Controllers/Hr';
if(!is_dir($dirCtrl)) mkdir($dirCtrl, 0755, true);

// 1. Payroll Controller
$payrollCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\PayrollService;
use App\Services\Core\ActivityLogService;

class PayrollController extends Controller
{
    protected $payrollService, $activityLogService;

    public function __construct(PayrollService $payrollService, ActivityLogService $activityLogService)
    {
        $this->payrollService = $payrollService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $payrolls = $this->payrollService->getAll();
        return view('hr.payroll.index', compact('payrolls'));
    }

    public function create(\App\Repositories\GoogleSheets\EmployeeRepository $employeeRepo)
    {
        $employees = $employeeRepo->fetchAll();
        return view('hr.payroll.create', compact('employees'));
    }

    public function store(Request $request)
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

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user()->email ?? 'HR Admin';
            if ($request->has('Status')) {
                $this->payrollService->updateStatus($id, $request->Status, $user);
                $this->activityLogService->log(auth()->id(), 'UPDATE_PAYROLL_STATUS', 'HR', "Updated payroll {$id} status to {$request->Status}");
            }
            return redirect()->route('payrolls.index')->with('success', 'Payroll updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        // Handled via updating status to Cancelled (already implemented in repo)
    }
}
EOT;
file_put_contents("$dirCtrl/PayrollController.php", $payrollCtrl);

// 2. Payroll Document Controller
$docCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\HR\PayrollService;

class PayrollDocumentController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function showSlip($id)
    {
        $payroll = $this->payrollService->getById($id);
        if (!$payroll) abort(404);
        
        $slipNumber = $this->payrollService->GenerateSalarySlip($id);
        // Note: For now, it will return a preview page (stub), not a real PDF download.
        return view('hr.payroll.slip', compact('payroll', 'slipNumber'));
    }
}
EOT;
file_put_contents("$dirCtrl/PayrollDocumentController.php", $docCtrl);

echo "HR Controllers created.\n";
?>
