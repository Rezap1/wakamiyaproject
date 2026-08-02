<?php
$dir = 'app/Http/Controllers/Finance';
if(!is_dir($dir)) mkdir($dir, 0755, true);

// 1. Invoice Controller
$invoiceCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Services\Core\ActivityLogService;

class InvoiceController extends Controller
{
    protected $invoiceService, $activityLogService;

    public function __construct(InvoiceService $invoiceService, ActivityLogService $activityLogService)
    {
        $this->invoiceService = $invoiceService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $invoices = $this->invoiceService->getAll();
        return view('finance.invoices.index', compact('invoices'));
    }

    public function create(\App\Repositories\GoogleSheets\StudentRepository $studentRepo)
    {
        $students = $studentRepo->fetchAll();
        return view('finance.invoices.create', compact('students'));
    }

    public function store(Request $request)
    {
        try {
            $data = $request->except('_token');
            $data['Reference_Type'] = $data['Reference_Type'] ?? 'General';
            $data['Reference_ID'] = $data['Reference_ID'] ?? 'GEN-000';
            $this->invoiceService->create($data);
            $this->activityLogService->log(auth()->id(), 'CREATE_INVOICE', 'FINANCE', 'Created new invoice for ' . $request->Student_ID);
            return redirect()->route('invoices.index')->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $invoice = $this->invoiceService->getById($id);
        return view('finance.invoices.show', compact('invoice'));
    }

    public function edit($id, \App\Repositories\GoogleSheets\StudentRepository $studentRepo)
    {
        $invoice = $this->invoiceService->getById($id);
        $students = $studentRepo->fetchAll();
        return view('finance.invoices.edit', compact('invoice', 'students'));
    }

    public function update(Request $request, $id)
    {
        try {
            $this->invoiceService->update($id, $request->except(['_token', '_method']));
            $this->activityLogService->log(auth()->id(), 'UPDATE_INVOICE', 'FINANCE', 'Updated invoice ' . $id);
            return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
EOT;
file_put_contents("$dir/InvoiceController.php", $invoiceCtrl);

// 2. Payment Controller
$paymentCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\PaymentService;
use App\Services\Core\ActivityLogService;

class PaymentController extends Controller
{
    protected $paymentService, $activityLogService;

    public function __construct(PaymentService $paymentService, ActivityLogService $activityLogService)
    {
        $this->paymentService = $paymentService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $payments = $this->paymentService->getAll();
        return view('finance.payments.index', compact('payments'));
    }

    public function show($id)
    {
        $payment = $this->paymentService->getById($id);
        return view('finance.payments.show', compact('payment'));
    }

    public function verify(Request $request, $id)
    {
        try {
            $status = $request->input('Status', 'Verified');
            $notes = $request->input('Notes', '');
            $this->paymentService->verifyPayment($id, auth()->user()->email ?? 'Finance Officer', $status, $notes);
            $this->activityLogService->log(auth()->id(), 'VERIFY_PAYMENT', 'FINANCE', "Verified payment {$id} as {$status}");
            return redirect()->route('payments.index')->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
EOT;
file_put_contents("$dir/PaymentController.php", $paymentCtrl);

// 3. Student Billing Controller
$billingCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Support\Facades\Cache;

class StudentBillingController extends Controller
{
    protected $invoiceService, $paymentService;

    public function __construct(InvoiceService $invoiceService, PaymentService $paymentService)
    {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $studentId = auth()->user()->id ?? 'STU-001';
        
        $data = Cache::remember("student_billing_{$studentId}", 60, function() use ($studentId) {
            $allInvoices = $this->invoiceService->getAll();
            $myInvoices = $allInvoices->filter(function($inv) use ($studentId) {
                return ($inv['Student_ID'] ?? '') == $studentId;
            })->values();

            $allPayments = $this->paymentService->getAll();
            $myPayments = $allPayments->filter(function($pay) use ($studentId) {
                return ($pay['Student_ID'] ?? '') == $studentId;
            })->values();

            $totalOutstanding = $myInvoices->where('Status', 'Waiting Payment')->sum('Amount');
            $totalPaid = $myPayments->where('Status', 'Verified')->sum('Amount_Paid');

            return compact('myInvoices', 'myPayments', 'totalOutstanding', 'totalPaid');
        });

        return view('student.billing.index', $data);
    }

    public function show($id)
    {
        $invoice = $this->invoiceService->getById($id);
        
        $allPayments = $this->paymentService->getAll();
        $relatedPayments = $allPayments->where('Invoice_ID', $id)->values();

        return view('student.billing.show', compact('invoice', 'relatedPayments'));
    }

    public function pay(Request $request, $id)
    {
        try {
            $studentId = auth()->user()->id ?? 'STU-001';
            
            // Simulating file upload (since actual storage might not be set up)
            $proofFile = 'proof_placeholder.jpg';
            if ($request->hasFile('Proof_File')) {
                // $proofFile = $request->file('Proof_File')->store('payments');
            }

            $paymentData = [
                'Invoice_ID' => $id,
                'Student_ID' => $studentId,
                'Amount_Paid' => $request->input('Amount_Paid', 0),
                'Proof_File' => $proofFile
            ];

            $this->paymentService->submitPayment($paymentData);
            
            return redirect()->route('student.billing.show', $id)->with('success', 'Payment submitted and waiting for verification.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
EOT;
file_put_contents("$dir/StudentBillingController.php", $billingCtrl);

echo "Controllers created.\n";
?>
